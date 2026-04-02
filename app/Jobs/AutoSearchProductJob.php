<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\AutoProductSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutoSearchProductJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $productId;
    public int $tries = 2;
    public int $timeout = 900;

    public function __construct(int $productId)
    {
        $this->productId = $productId;
    }

    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(AutoProductSearchService $service): void
    {
        $lock = Cache::lock('auto-search-product-' . $this->productId, $this->timeout + 60);

        if (!$lock->get()) {
            Log::info('AutoSearchProductJob skipped because lock is already held', [
                'product_id' => $this->productId,
            ]);

            return;
        }

        try {
            $product = Product::where('id', $this->productId)
                ->where('is_active', 1)
                ->first();

            if (!$product) {
                Log::warning('AutoSearchProductJob: product not found or inactive', [
                    'product_id' => $this->productId,
                ]);
                return;
            }

            Log::info('AutoSearchProductJob started', [
                'product_id' => $product->id,
                'name' => $product->name,
            ]);

            $service->handle($product, true);

            Log::info('AutoSearchProductJob finished', [
                'product_id' => $product->id,
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AutoSearchProductJob failed', [
            'product_id' => $this->productId,
            'message' => $e->getMessage(),
        ]);
    }
}