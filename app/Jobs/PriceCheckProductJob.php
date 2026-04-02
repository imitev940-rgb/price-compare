<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PriceCheckProductJob implements ShouldQueue
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

    public function handle(): void
    {
        $lock = Cache::lock('price-check-product-' . $this->productId, $this->timeout + 60);

        if (!$lock->get()) {
            Log::info('PriceCheckProductJob skipped because lock is already held', [
                'product_id' => $this->productId,
            ]);

            return;
        }

        try {
            Log::info('PriceCheckProductJob started', [
                'product_id' => $this->productId,
            ]);

            Artisan::call('prices:check', [
                'product_id' => $this->productId,
            ]);

            Log::info('PriceCheckProductJob finished', [
                'product_id' => $this->productId,
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('PriceCheckProductJob failed', [
            'product_id' => $this->productId,
            'message' => $e->getMessage(),
        ]);
    }
}