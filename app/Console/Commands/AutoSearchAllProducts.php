<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AutoProductSearchService;
use Illuminate\Console\Command;

class AutoSearchAllProducts extends Command
{
    protected $signature = 'products:auto-search
                            {product_id?            : ID на конкретен продукт}
                            {--store=               : Само за конкретен магазин (напр. Techmart)}
                            {--overwrite            : Презапиши съществуващи линкове}
                            {--inactive             : Включи и неактивни продукти}';

    protected $description = 'Auto search competitor links for products';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        gc_enable();

        $service   = app(AutoProductSearchService::class);
        $productId = $this->argument('product_id');
        $store     = $this->option('store')     ?: null;
        $overwrite = (bool) $this->option('overwrite');
        $inactive  = (bool) $this->option('inactive');

        // ── Единичен продукт ─────────────────────────────────────────────────
        if ($productId) {
            $query   = Product::query();
            if (! $inactive) {
                $query->where('is_active', 1);
            }
            $product = $query->find($productId);

            if (! $product) {
                $this->error("Product #{$productId} not found or inactive.");
                return self::FAILURE;
            }

            $this->info("Searching: [{$product->id}] {$product->name}");

            $start = microtime(true);

            try {
                $service->handle($product, $overwrite, $store);
                $elapsed = round(microtime(true) - $start, 2);
                $this->info("Done in {$elapsed}s — product ID {$product->id}");
            } catch (\Throwable $e) {
                $this->error("Error on #{$product->id}: " . $e->getMessage());
                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        // ── Всички продукти ──────────────────────────────────────────────────
        $query = Product::query()->orderBy('id');
        if (! $inactive) {
            $query->where('is_active', 1);
        }

        $total     = $query->count();
        $processed = 0;
        $failed    = 0;

        $this->info("Starting auto search for {$total} products" . ($store ? " [store: {$store}]" : '') . '...');

        $query->chunkById(1, function ($products) use ($service, $store, $overwrite, $total, &$processed, &$failed) {
            foreach ($products as $product) {
                $processed++;
                $this->line("[{$processed}/{$total}] {$product->id} — {$product->name}");

                $start = microtime(true);

                try {
                    $service->handle($product, $overwrite, $store);
                    $elapsed = round(microtime(true) - $start, 2);
                    $this->info("  ✓ Done in {$elapsed}s");
                } catch (\Throwable $e) {
                    $failed++;
                    $elapsed = round(microtime(true) - $start, 2);
                    $this->error("  ✗ Error in {$elapsed}s: " . $e->getMessage());
                }

                gc_collect_cycles();
            }
        });

        $this->info("Finished. Processed: {$processed}, Failed: {$failed}.");

        return self::SUCCESS;
    }
}