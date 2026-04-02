<?php

namespace App\Imports;

use App\Jobs\AutoSearchProductJob;
use App\Jobs\PriceCheckProductJob;
use App\Models\Product;
use App\Services\OwnProductPriceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProductsImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            return;
        }

        $header = $rows->first()->map(fn ($h) => trim((string) $h))->toArray();
        $rows = $rows->skip(1);

        foreach ($rows as $row) {
            try {
                $data = array_combine($header, $row->toArray());

                if (! $data || empty($data['name'])) {
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

                if (! $product && $model !== '') {
                    $product = Product::where('model', $model)->first();
                }

                $productData = [
                    'name' => trim((string) ($data['name'] ?? '')),
                    'sku' => $sku ?: null,
                    'ean' => $ean ?: null,
                    'brand' => trim((string) ($data['brand'] ?? '')) ?: null,
                    'product_url' => trim((string) ($data['product_url'] ?? '')) ?: null,
                    'is_active' => isset($data['is_active'])
                        ? (int) $data['is_active']
                        : 1,
                ];

                if ($model !== '') {
                    $productData['model'] = $model;
                }

                $price = $this->getPriceWithRetry($productData['product_url']);

                if ($price === null) {
                    continue;
                }

                $productData['our_price'] = number_format($price, 2, '.', '');

                if ($product) {
                    $product->update($productData);
                } else {
                    $product = Product::create($productData);
                }

                AutoSearchProductJob::dispatch($product->id)
                    ->onQueue('search')
                    ->delay(now()->addSeconds(1));

                PriceCheckProductJob::dispatch($product->id)
                    ->onQueue('price')
                    ->delay(now()->addSeconds(5));
            } catch (\Throwable $e) {
                Log::error('Import error', [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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