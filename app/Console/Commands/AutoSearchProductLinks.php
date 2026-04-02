<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AutoProductSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoSearchProductLinks extends Command
{
    protected $signature = 'products:auto-search {product_id?}';
    protected $description = 'Auto search competitor links for one product or all active products';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $service = app(AutoProductSearchService::class);

        $productId = $this->argument('product_id');

        if ($productId) {
            $product = Product::where('id', $productId)
                ->where('is_active', 1)
                ->first();

            if (! $product) {
                $this->error("Product with ID {$productId} not found or inactive.");
                return self::FAILURE;
            }

            $this->info("Searching: {$product->name}");

            try {
                Log::info('Auto search product start', [
                    'product_id' => $product->id,
                    'name' => $product->name,
                ]);

                $service->handle($product, true);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                Log::error('Auto search product failed', [
                    'product_id' => $product->id,
                    'message' => $e->getMessage(),
                ]);
                return self::FAILURE;
            }

            $this->info('Done.');
            return self::SUCCESS;
        }

        $total = Product::where('is_active', 1)->count();
        $this->info("Starting auto search for {$total} products...");

        $processed = 0;

        Product::where('is_active', 1)
            ->orderBy('id')
            ->chunkById(5, function ($products) use ($service, &$processed) {
                foreach ($products as $product) {
                    $this->line("Searching: {$product->id} - {$product->name}");

                    Log::info('Auto search product start', [
                        'product_id' => $product->id,
                        'name' => $product->name,
                    ]);

                    try {
                        $service->handle($product, true);
                        $processed++;

                        // по-голяма пауза, за да не rate-limit-ва Techmart
                        usleep(900000);
                    } catch (\Throwable $e) {
                        $this->error("Product #{$product->id}: " . $e->getMessage());

                        Log::error('Auto search product failed', [
                            'product_id' => $product->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }

                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            });

        $this->info("Done. Processed {$processed} products.");

        return self::SUCCESS;
    }
}