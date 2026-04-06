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

class PriceCheckProductJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public int $productId)
    {
        $this->onQueue('price');
    }

    public function backoff(): array
    {
        return [15, 30];
    }

    public function handle(
        PriceExtractorService   $extractor,
        PazaruvajScraperService $pazaruvajScraper
    ): void {
        $lock = Cache::lock('price-check-product-' . $this->productId, $this->timeout + 30);

        if (! $lock->get()) {
            Log::info('PriceCheckProductJob skipped — lock held', [
                'product_id' => $this->productId,
            ]);
            return;
        }

        try {
            Log::info('PriceCheckProductJob started', [
                'product_id' => $this->productId,
            ]);

            $links = CompetitorLink::with(['product', 'store'])
                ->where('product_id', $this->productId)
                ->where('is_active', 1)
                ->get();

            if ($links->isEmpty()) {
                Log::info('PriceCheckProductJob: no active links', [
                    'product_id' => $this->productId,
                ]);
                return;
            }

            $command = new \App\Console\Commands\CheckCompetitorPrices();
            $command->setLaravel(app());
            $command->setOutput(new \Illuminate\Console\OutputStyle(
                new \Symfony\Component\Console\Input\ArrayInput([]),
                new \Symfony\Component\Console\Output\NullOutput()
            ));

            foreach ($links as $link) {
                try {
                    $command->handleLink($link, $extractor, $pazaruvajScraper);
                } catch (\Throwable $e) {
                    Log::error('PriceCheckProductJob link failed', [
                        'product_id' => $this->productId,
                        'link_id'    => $link->id,
                        'message'    => $e->getMessage(),
                    ]);
                }
            }

            Log::info('PriceCheckProductJob finished', [
                'product_id' => $this->productId,
                'links'      => $links->count(),
            ]);

        } finally {
            optional($lock)->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('PriceCheckProductJob failed', [
            'product_id' => $this->productId,
            'message'    => $e->getMessage(),
        ]);
    }
}