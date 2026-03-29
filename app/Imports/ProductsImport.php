<?php

namespace App\Imports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProductsImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $header = $rows->first()->map(fn($h) => trim($h))->toArray();
        $rows = $rows->skip(1);

        foreach ($rows as $row) {
            try {
                $data = array_combine($header, $row->toArray());

                if (empty($data['name'])) {
                    continue;
                }

                $sku = trim((string) ($data['sku'] ?? ''));
                $ean = trim((string) ($data['ean'] ?? ''));

                // 🔥 SMART MATCHING
                $product = null;

                if ($sku !== '') {
                    $product = Product::where('sku', $sku)->first();
                }

                if (!$product && $ean !== '') {
                    $product = Product::where('ean', $ean)->first();
                }

                if (!$product && !empty($data['model'])) {
                    $product = Product::where('model', $data['model'])->first();
                }

                $productData = [
                    'name' => $data['name'] ?? '',
                    'sku' => $sku ?: null,
                    'ean' => $ean ?: null,
                    'brand' => $data['brand'] ?? null,
                    'product_url' => $data['product_url'] ?? null,
                    'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
                ];

                // 🔥 PRICE + RETRY
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

                // 🔥 AUTO FLOW
                Artisan::call('products:auto-search', [
                    'product_id' => $product->id,
                ]);

                Artisan::call('prices:check', [
                    'product_id' => $product->id,
                ]);

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
        if (!$url) return null;

        for ($i = 0; $i < 3; $i++) {
            try {
                $price = app(\App\Http\Controllers\ProductController::class)
                    ->fetchOwnPriceFromUrl($url);

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