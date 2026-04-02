<?php

namespace App\Jobs;

use App\Jobs\AutoSearchProductJob;
use App\Jobs\PriceCheckProductJob;
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
use Illuminate\Support\Facades\Storage;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $importJobId;

    public function __construct(int $importJobId)
    {
        $this->importJobId = $importJobId;
    }

    public function handle(): void
    {
        $importJob = ImportJob::find($this->importJobId);

        if (! $importJob) {
            return;
        }

        try {
            Log::info('Import job started', [
                'import_job_id' => $importJob->id,
                'file_path' => $importJob->file_path,
            ]);

            $importJob->update([
                'status' => 'processing',
                'started_at' => now(),
                'last_error' => null,
            ]);

            $fullPath = storage_path('app/' . $importJob->file_path);

            if (! file_exists($fullPath)) {
                throw new \RuntimeException('Import file not found: ' . $fullPath);
            }

            $sheetRows = $this->readCsvRows($fullPath);

            Log::info('Import file parsed', [
                'import_job_id' => $importJob->id,
                'rows_count' => count($sheetRows),
            ]);

            if (count($sheetRows) < 2) {
                $importJob->update([
                    'status' => 'failed',
                    'last_error' => 'Файлът е празен или няма данни.',
                    'finished_at' => now(),
                ]);
                return;
            }

            $header = array_map(function ($h) {
                return strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $h)));
            }, $sheetRows[0]);

            $dataRows = array_slice($sheetRows, 1);

            $importJob->update([
                'total_rows' => count($dataRows),
            ]);

            foreach ($dataRows as $index => $row) {
                $rowNumber = $index + 2;

                try {
                    if (! is_array($row)) {
                        ImportError::create([
                            'import_job_id' => $importJob->id,
                            'row_number' => $rowNumber,
                            'row_data' => json_encode($row),
                            'error_message' => 'Невалиден ред',
                        ]);

                        $importJob->increment('error_count');
                        $importJob->increment('processed_rows');
                        continue;
                    }

                    if (count($row) < count($header)) {
                        $row = array_pad($row, count($header), null);
                    }

                    $data = array_combine($header, array_slice($row, 0, count($header)));

                    if (! $data) {
                        ImportError::create([
                            'import_job_id' => $importJob->id,
                            'row_number' => $rowNumber,
                            'row_data' => json_encode($row),
                            'error_message' => 'Header mismatch',
                        ]);

                        $importJob->increment('error_count');
                        $importJob->increment('processed_rows');
                        continue;
                    }

                    $name = trim((string) ($data['name'] ?? ''));

                    if ($name === '') {
                        ImportError::create([
                            'import_job_id' => $importJob->id,
                            'row_number' => $rowNumber,
                            'row_data' => json_encode($data),
                            'error_message' => 'Липсва име на продукт',
                        ]);

                        $importJob->increment('error_count');
                        $importJob->increment('processed_rows');
                        continue;
                    }

                    $sku = trim((string) ($data['sku'] ?? ''));
                    $ean = trim((string) ($data['ean'] ?? ''));
                    $model = trim((string) ($data['model'] ?? ''));

                    $product = null;

                    if ($sku !== '') {
                        $product = Product::where('sku', $sku)->first();
                    }

                    if (! $product && $ean !== '') {
                        $product = Product::where('ean', $ean)->first();
                    }

                    if (! $product && $model !== '' && Schema::hasColumn('products', 'model')) {
                        $product = Product::where('model', $model)->first();
                    }

                    $productData = [
                        'name' => $name,
                        'sku' => $sku !== '' ? $sku : null,
                        'ean' => $ean !== '' ? $ean : null,
                        'brand' => trim((string) ($data['brand'] ?? '')) ?: null,
                        'product_url' => trim((string) ($data['product_url'] ?? '')) ?: null,
                        'is_active' => in_array(
                            strtolower(trim((string) ($data['is_active'] ?? '1'))),
                            ['1', 'true', 'yes', 'on'],
                            true
                        ) ? 1 : 0,
                    ];

                    if (Schema::hasColumn('products', 'model')) {
                        $productData['model'] = $model !== '' ? $model : null;
                    }

                    $price = $this->getPriceWithRetry($productData['product_url']);

                    if ($price !== null) {
                        $productData['our_price'] = number_format($price, 2, '.', '');
                    } elseif (! empty($data['our_price'])) {
                        $productData['our_price'] = number_format(
                            (float) str_replace(',', '.', (string) $data['our_price']),
                            2,
                            '.',
                            ''
                        );
                    } else {
                        ImportError::create([
                            'import_job_id' => $importJob->id,
                            'row_number' => $rowNumber,
                            'row_data' => json_encode($data),
                            'error_message' => 'Не може да се вземе цена',
                        ]);

                        $importJob->increment('error_count');
                        $importJob->increment('processed_rows');
                        continue;
                    }

                    if ($product) {
                        $product->update($productData);
                        $importJob->increment('updated_count');
                    } else {
                        $product = Product::create($productData);
                        $importJob->increment('imported_count');
                    }

                    dispatch(
                        (new AutoSearchProductJob($product->id))
                            ->onQueue('search')
                            ->delay(now()->addSeconds(1))
                    );

                    dispatch(
                        (new PriceCheckProductJob($product->id))
                            ->onQueue('price')
                            ->delay(now()->addSeconds(5))
                    );
                } catch (\Throwable $e) {
                    ImportError::create([
                        'import_job_id' => $importJob->id,
                        'row_number' => $rowNumber,
                        'row_data' => json_encode($row),
                        'error_message' => $e->getMessage(),
                    ]);

                    Log::error('Import row error', [
                        'import_job_id' => $importJob->id,
                        'row' => $row,
                        'error' => $e->getMessage(),
                    ]);

                    $importJob->update([
                        'last_error' => $e->getMessage(),
                    ]);

                    $importJob->increment('error_count');
                }

                $importJob->increment('processed_rows');
            }

            Storage::delete($importJob->file_path);

            $importJob->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);

            Log::info('Import job finished', [
                'import_job_id' => $importJob->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Import failed', [
                'import_job_id' => $this->importJobId,
                'error' => $e->getMessage(),
            ]);

            $importJob->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }

    private function readCsvRows(string $fullPath): array
    {
        $content = file_get_contents($fullPath);

        if ($content === false) {
            throw new \RuntimeException('Cannot read CSV file.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $delimiter = substr_count($content, ';') > substr_count($content, ',') ? ';' : ',';

        $rows = [];
        $handle = fopen($fullPath, 'r');

        if (! $handle) {
            throw new \RuntimeException('Cannot open CSV file.');
        }

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) === 1 && isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]);
            }

            $rows[] = $data;
        }

        fclose($handle);

        return $rows;
    }

    private function getPriceWithRetry(?string $url): ?float
    {
        if (! $url) {
            return null;
        }

        for ($i = 0; $i < 3; $i++) {
            try {
                $price = app(OwnProductPriceService::class)->getPrice($url);

                if ($price !== null) {
                    return $price;
                }

                sleep(1);
            } catch (\Throwable $e) {
                sleep(1);
            }
        }

        return null;
    }
}