<?php

namespace App\Jobs;

use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\Product;
use App\Services\OwnProductPriceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 3600;

    public function __construct(protected int $importJobId)
    {
        $this->onQueue('import');
    }

    public function handle(OwnProductPriceService $priceService): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if (! $importJob) {
            return;
        }

        $hasModel        = Schema::hasColumn('products', 'model');
        $hasScanPriority = Schema::hasColumn('products', 'scan_priority');

        try {
            Log::info('Import job started', [
                'import_job_id' => $importJob->id,
                'file_path'     => $importJob->file_path,
            ]);

            $importJob->update([
                'status'     => 'processing',
                'started_at' => now(),
                'last_error' => null,
            ]);

            $fullPath = storage_path('app/' . $importJob->file_path);

            if (! file_exists($fullPath)) {
                throw new \RuntimeException('Import file not found: ' . $fullPath);
            }

            // ── Чети XLS/XLSX ────────────────────────────────────────────────
            $sheetRows = $this->readSpreadsheetRows($fullPath);

            if (count($sheetRows) < 2) {
                $importJob->update([
                    'status'      => 'failed',
                    'last_error'  => 'Файлът е празен или няма данни.',
                    'finished_at' => now(),
                ]);
                return;
            }

            $header = array_map(
                fn ($h) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $h))),
                $sheetRows[0]
            );

            $dataRows = array_slice($sheetRows, 1);

            $importJob->update(['total_rows' => count($dataRows)]);

            Log::info('Import file parsed', [
                'import_job_id' => $importJob->id,
                'rows_count'    => count($dataRows),
            ]);

            foreach ($dataRows as $index => $row) {
                $rowNumber = $index + 2;

                try {
                    if (! is_array($row)) {
                        $this->logError($importJob, $rowNumber, $row, 'Невалиден ред');
                        continue;
                    }

                    if (count($row) < count($header)) {
                        $row = array_pad($row, count($header), null);
                    }

                    $data = array_combine($header, array_slice($row, 0, count($header)));

                    if (! $data) {
                        $this->logError($importJob, $rowNumber, $row, 'Header mismatch');
                        continue;
                    }

                    $name = trim((string) ($data['name'] ?? ''));

                    if ($name === '') {
                        $this->logError($importJob, $rowNumber, $data, 'Липсва име на продукт');
                        continue;
                    }

                    $sku        = trim((string) ($data['sku']         ?? ''));
                    $ean        = trim((string) ($data['ean']         ?? ''));
                    $model      = trim((string) ($data['model']       ?? ''));
                    $productUrl = trim((string) ($data['product_url'] ?? ''));

                    // Намери съществуващ продукт
                    $product = null;
                    if ($sku !== '')                        $product = Product::where('sku', $sku)->first();
                    if (! $product && $ean !== '')          $product = Product::where('ean', $ean)->first();
                    if (! $product && $model !== '' && $hasModel) {
                        $product = Product::where('model', $model)->first();
                    }

                    // Вземи цена
                    $price = null;
                    if ($productUrl !== '') {
                        $price = $this->getPriceWithRetry($priceService, $productUrl);
                    }
                    if ($price === null && ! empty($data['our_price'])) {
                        $price = round((float) str_replace(',', '.', (string) $data['our_price']), 2);
                        if ($price <= 0) $price = null;
                    }

                    if ($price === null) {
                        $this->logError($importJob, $rowNumber, $data, 'Не може да се вземе цена');
                        continue;
                    }

                    // Построй данните
                    $productData = [
                        'name'        => $name,
                        'sku'         => $sku        !== '' ? $sku        : null,
                        'ean'         => $ean        !== '' ? $ean        : null,
                        'brand'       => trim((string) ($data['brand'] ?? '')) ?: null,
                        'product_url' => $productUrl !== '' ? $productUrl : null,
                        'our_price'   => number_format($price, 2, '.', ''),
                        'is_active'   => in_array(
                            strtolower(trim((string) ($data['is_active'] ?? '1'))),
                            ['1', 'true', 'yes', 'on'],
                            true
                        ) ? 1 : 0,
                    ];

                    if ($hasModel) {
                        $productData['model'] = $model !== '' ? $model : null;
                    }

                    if ($hasScanPriority) {
                        $priority = strtolower(trim((string) ($data['scan_priority'] ?? 'normal')));
                        $productData['scan_priority'] = in_array($priority, ['top', 'normal']) ? $priority : 'normal';
                    }

                    if ($product) {
                        $product->update($productData);
                        $importJob->increment('updated_count');
                    } else {
                        $product = Product::create($productData);
                        $importJob->increment('imported_count');
                    }

                    // Само AutoSearch — той пуска PriceCheck след като приключи
                    dispatch(
                        (new AutoSearchProductJob($product->id))
                            ->onQueue('search')
                    );

                } catch (\Throwable $e) {
                    $this->logError($importJob, $rowNumber, $row ?? [], $e->getMessage());

                    Log::error('Import row error', [
                        'import_job_id' => $importJob->id,
                        'row_number'    => $rowNumber,
                        'error'         => $e->getMessage(),
                    ]);

                    $importJob->update(['last_error' => $e->getMessage()]);
                }

                $importJob->increment('processed_rows');
            }

            // Изтрий файла след успех
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            $importJob->update([
                'status'      => 'completed',
                'finished_at' => now(),
            ]);

            Log::info('Import job finished', [
                'import_job_id'  => $importJob->id,
                'imported_count' => $importJob->imported_count,
                'updated_count'  => $importJob->updated_count,
                'error_count'    => $importJob->error_count,
            ]);

        } catch (\Throwable $e) {
            Log::error('Import failed', [
                'import_job_id' => $this->importJobId,
                'error'         => $e->getMessage(),
            ]);

            $importJob->update([
                'status'      => 'failed',
                'last_error'  => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function readSpreadsheetRows(string $fullPath): array
    {
        $spreadsheet = IOFactory::load($fullPath);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getFormattedValue();
            }

            // Пропусни напълно празни редове
            if (count(array_filter($rowData, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $rows[] = $rowData;
        }

        return $rows;
    }

    private function getPriceWithRetry(OwnProductPriceService $priceService, string $url, int $tries = 2): ?float
    {
        for ($i = 0; $i < $tries; $i++) {
            try {
                $price = $priceService->getPrice($url);
                if ($price !== null && $price > 0) {
                    return round($price, 2);
                }
            } catch (\Throwable $e) {
                Log::warning('getPriceWithRetry failed', [
                    'url'     => $url,
                    'attempt' => $i + 1,
                    'error'   => $e->getMessage(),
                ]);
            }

            if ($i < $tries - 1) {
                sleep(2);
            }
        }

        return null;
    }

    private function logError(ImportJob $importJob, int $rowNumber, mixed $data, string $message): void
    {
        ImportError::create([
            'import_job_id' => $importJob->id,
            'row_number'    => $rowNumber,
            'row_data'      => json_encode($data),
            'error_message' => $message,
        ]);

        $importJob->increment('error_count');
        $importJob->increment('processed_rows');
    }
}