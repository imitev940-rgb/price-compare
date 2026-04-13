<?php

namespace App\Jobs;

use App\Models\CompetitorLink;
use App\Services\PazaruvajScraperService;
use App\Services\PriceExtractorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PriceCheckLinkJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;   // ↓ от 900 — Playwright: 5-10 сек, HTTP: 20-30 сек

    public function __construct(public int $linkId)
    {
        $this->onQueue('price');
    }

    public function backoff(): array
    {
        return [15, 30];   // ↓ от [15, 60]
    }

    public function handle(
        PriceExtractorService   $extractor,
        PazaruvajScraperService $pazaruvajScraper
    ): void {
        $dispatchKey = 'price_check_dispatching:' . $this->linkId;
        $lock        = Cache::lock('price-check-link-' . $this->linkId, $this->timeout + 30);

        if (! $lock->get()) {
            Log::info('PriceCheckLinkJob skipped — lock held', [
                'link_id' => $this->linkId,
            ]);
            Cache::forget($dispatchKey);
            return;
        }

        try {
            $link = CompetitorLink::with(['product', 'store'])
                ->find($this->linkId);

            if (! $link) {
                Log::warning('PriceCheckLinkJob: link not found', [
                    'link_id' => $this->linkId,
                ]);
                return;
            }

            if (! $link->is_active) {
                Log::info('PriceCheckLinkJob: link inactive, skipping', [
                    'link_id' => $this->linkId,
                ]);
                return;
            }

            Log::info('PriceCheckLinkJob started', [
                'link_id'   => $this->linkId,
                'store'     => $link->store?->name,
                'url'       => $link->product_url,
            ]);

            // Вика директно командата като сервис — без Artisan::call()
            app(\App\Console\Commands\CheckCompetitorPrices::class)->handleLink(
                $link,
                $extractor,
                $pazaruvajScraper
            );

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