<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AutoProductSearchService;
use Illuminate\Console\Command;

class AutoSearchAllProducts extends Command
{
    protected $signature = 'products:auto-search {product_id?}';
    protected $description = 'Auto search competitor links for products';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        gc_enable();

        $service = app(AutoProductSearchService::class);
        $productId = $this->argument('product_id');

        if ($productId) {
            $product = Product::find($productId);

            if (!$product) {
                $this->error('Product not found.');
                return self::FAILURE;
            }

            $this->info("Searching links for: {$product->name} (ID: {$product->id})");

            try {
                $service->handle($product, true);
                gc_collect_cycles();
                $this->info("Done: product ID {$product->id}");
            } catch (\Throwable $e) {
                $this->error("Error on product ID {$product->id}: " . $e->getMessage());
                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        Product::query()
            ->where('is_active', 1)
            ->orderBy('id')
            ->chunkById(1, function ($products) use ($service) {
                foreach ($products as $product) {
                    $this->info("Searching links for: {$product->name} (ID: {$product->id})");

                    try {
                        $service->handle($product, true);
                        $this->info("Done: product ID {$product->id}");
                    } catch (\Throwable $e) {
                        $this->error("Error on product ID {$product->id}: " . $e->getMessage());
                    }

                    gc_collect_cycles();
                    usleep(500000); // 0.5 сек пауза между продуктите
                }

                gc_collect_cycles();
            });

        $this->info('Finished all products.');

        return self::SUCCESS;
    }
}