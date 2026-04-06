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

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public int $productId)
    {
        $this->onQueue('search');
    }

    public function backoff(): array
    {
        return [15, 30];
    }

    public function handle(AutoProductSearchService $service): void
    {
        $lock = Cache::lock('auto-search-product-' . $this->productId, $this->timeout + 30);

        if (! $lock->get()) {
            Log::info('AutoSearchProductJob skipped — lock held', [
                'product_id' => $this->productId,
            ]);
            return;
        }

        try {
            $product = Product::where('id', $this->productId)
                ->where('is_active', 1)
                ->first();

            if (! $product) {
                Log::warning('AutoSearchProductJob: product not found or inactive', [
                    'product_id' => $this->productId,
                ]);
                return;
            }

            Log::info('AutoSearchProductJob started', [
                'product_id' => $product->id,
                'name'       => $product->name,
            ]);

            $service->handle($product, true);

            Log::info('AutoSearchProductJob finished', [
                'product_id' => $product->id,
            ]);

            // ── Пусни PriceCheck СЛЕД като всички линкове са намерени ────────
            $priceQueue = ($product->scan_priority ?? 'normal') === 'top'
                ? 'price_top'
                : 'price';

            PriceCheckProductJob::dispatch($product->id)
                ->onQueue($priceQueue)
                ->delay(now()->addSeconds(5));

            Log::info('AutoSearchProductJob dispatched PriceCheck', [
                'product_id' => $product->id,
                'queue'      => $priceQueue,
            ]);

        } finally {
            optional($lock)->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AutoSearchProductJob failed', [
            'product_id' => $this->productId,
            'message'    => $e->getMessage(),
        ]);
    }
}