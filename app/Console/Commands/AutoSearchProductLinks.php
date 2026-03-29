<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AutoProductSearchService;
use Illuminate\Console\Command;

class AutoSearchProductLinks extends Command
{
    protected $signature = 'products:auto-search {product_id?}';
    protected $description = 'Auto search competitor links for one product or all active products';

    public function handle(): int
    {
        $service = app(AutoProductSearchService::class);

        $productId = $this->argument('product_id');

        if ($productId) {
            $product = Product::where('id', $productId)
                ->where('is_active', 1)
                ->first();

            if (!$product) {
                $this->error("Product with ID {$productId} not found or inactive.");
                return self::FAILURE;
            }

            $this->info("Searching: {$product->name}");

            try {
                $service->handle($product, true);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }

            $this->info('Done.');
            return self::SUCCESS;
        }

        $products = Product::where('is_active', 1)->get();

        foreach ($products as $product) {
            $this->info("Searching: {$product->name}");

            try {
                $service->handle($product, true);
            } catch (\Throwable $e) {
                $this->error("Product #{$product->id}: " . $e->getMessage());
            }

            sleep(1); // важно за Zora
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}