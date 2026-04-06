<?php

namespace App\Imports;

use App\Jobs\AutoSearchProductJob;
use App\Models\Product;
use App\Services\OwnProductPriceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProductsImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) return;

        $hasModel        = Schema::hasColumn('products', 'model');
        $hasScanPriority = Schema::hasColumn('products', 'scan_priority');

        $header = $rows->first()->map(fn ($h) => strtolower(trim((string) $h)))->toArray();
        $dataRows = $rows->skip(1);

        foreach ($dataRows as $row) {
            try {
                $data = array_combine($header, $row->toArray());
                if (!$data || empty(trim((string) ($data['name'] ?? '')))) continue;

                $name       = trim((string) ($data['name'] ?? ''));
                $sku        = trim((string) ($data['sku']   ?? ''));
                $ean        = trim((string) ($data['ean']   ?? ''));
                $model      = trim((string) ($data['model'] ?? ''));
                $productUrl = trim((string) ($data['product_url'] ?? ''));

                // Намери съществуващ продукт
                $product = null;
                if ($sku !== '')   $product = Product::where('sku', $sku)->first();
                if (!$product && $ean !== '')   $product = Product::where('ean', $ean)->first();
                if (!$product && $model !== '' && $hasModel) $product = Product::where('model', $model)->first();

                // Вземи цена
                $price = null;
                if ($productUrl !== '') {
                    $price = $this->getPriceWithRetry($productUrl);
                }
                if ($price === null && !empty($data['our_price'])) {
                    $price = round((float) str_replace(',', '.', (string) $data['our_price']), 2);
                    if ($price <= 0) $price = null;
                }
                if ($price === null) {
                    Log::warning('Import: no price', ['name' => $name]);
                    continue;
                }

                // Построй данните
                $productData = [
                    'name'        => $name,
                    'sku'         => $sku ?: null,
                    'ean'         => $ean ?: null,
                    'brand'       => trim((string) ($data['brand'] ?? '')) ?: null,
                    'product_url' => $productUrl ?: null,
                    'our_price'   => number_format($price, 2, '.', ''),
                    'is_active'   => in_array(
                        strtolower(trim((string) ($data['is_active'] ?? '1'))),
                        ['1', 'true', 'yes', 'on'], true
                    ) ? 1 : 0,
                ];

                if ($hasModel) {
                    $productData['model'] = $model ?: null;
                }

                if ($hasScanPriority) {
                    $priority = strtolower(trim((string) ($data['scan_priority'] ?? 'normal')));
                    $productData['scan_priority'] = in_array($priority, ['top', 'normal']) ? $priority : 'normal';
                }

                // Create или Update
                if ($product) {
                    $product->update($productData);
                } else {
                    $product = Product::create($productData);
                }

                // Само AutoSearch — той пуска PriceCheck след като приключи
                AutoSearchProductJob::dispatch($product->id)
                    ->onQueue('search');

            } catch (\Throwable $e) {
                Log::error('Import row error', [
                    'name'  => $data['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getPriceWithRetry(?string $url, int $tries = 2): ?float
    {
        if (!$url) return null;

        for ($i = 0; $i < $tries; $i++) {
            try {
                $price = app(OwnProductPriceService::class)->getPrice($url);
                if ($price !== null && $price > 0) return round($price, 2);
            } catch (\Throwable $e) {
                Log::warning('getPriceWithRetry failed', ['url' => $url, 'attempt' => $i + 1]);
            }
            if ($i < $tries - 1) sleep(2);
        }

        return null;
    }
}