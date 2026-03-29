<?php

namespace App\Observers;

use App\Models\CompetitorLink;
use App\Models\Product;
use App\Models\Store;
use App\Services\PazaruvajSearchService;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->syncPazaruvajLink($product);
    }

    public function updated(Product $product): void
    {
        if (
            $product->wasChanged('name') ||
            $product->wasChanged('sku') ||
            $product->wasChanged('ean')
        ) {
            $this->syncPazaruvajLink($product);
        }
    }

    protected function syncPazaruvajLink(Product $product): void
    {
        $store = Store::where('name', 'Pazaruvaj')->first();

        if (! $store) {
            Log::warning('Pazaruvaj store not found');
            return;
        }

        $service = app(PazaruvajSearchService::class);
        $url = $service->findProductUrl($product);

        if (! $url) {
            Log::warning('No Pazaruvaj URL found for product', [
                'product_id' => $product->id,
                'product_name' => $product->name,
            ]);
            return;
        }

        CompetitorLink::updateOrCreate(
            [
                'product_id' => $product->id,
                'store_id' => $store->id,
            ],
            [
                'product_url' => $url,
                'is_active' => 1,
            ]
        );

        Log::info('Pazaruvaj competitor link synced', [
            'product_id' => $product->id,
            'url' => $url,
        ]);
    }
}