<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PriceCheckLinkJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $linkId;
    public int $tries = 2;
    public int $timeout = 900;

    public function __construct(int $linkId)
    {
        $this->linkId = $linkId;
    }

    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(): void
    {
        $dispatchKey = 'price_check_dispatching:' . $this->linkId;

        // Lock must live longer than the job runtime window
        $lock = Cache::lock('price-check-link-' . $this->linkId, $this->timeout + 60);

        if (!$lock->get()) {
            Log::info('PriceCheckLinkJob skipped because lock is already held', [
                'link_id' => $this->linkId,
            ]);

            Cache::forget($dispatchKey);
            return;
        }

        try {
            Log::info('PriceCheckLinkJob started', [
                'link_id' => $this->linkId,
            ]);

            Artisan::call('prices:check', [
                '--link_id' => $this->linkId,
            ]);

            Log::info('PriceCheckLinkJob finished', [
                'link_id' => $this->linkId,
            ]);
        } finally {
            optional($lock)->release();
            Cache::forget($dispatchKey);
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::forget('price_check_dispatching:' . $this->linkId);

        Log::error('PriceCheckLinkJob failed', [
            'link_id' => $this->linkId,
            'message' => $e->getMessage(),
        ]);
    }
}