<?php

namespace App\Console\Commands;

use App\Models\CompetitorLink;
use App\Models\Product;
use App\Models\Store;
use App\Services\AutoProductSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoSearchProductLinks extends Command
{
    protected $signature = 'products:auto-search
                            {product_id?            : ID на конкретен продукт}
                            {--store=               : Само за конкретен магазин (напр. Techmart)}
                            {--overwrite            : Презапиши съществуващи линкове}
                            {--inactive             : Включи и неактивни продукти}
                            {--missing              : Търси само в магазини без съществуващ линк}';

    protected $description = 'Auto search competitor links for products';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        gc_enable();

        $service   = app(AutoProductSearchService::class);
        $productId = $this->argument('product_id');
        $store     = $this->option('store')    ?: null;
        $overwrite = (bool) $this->option('overwrite');
        $inactive  = (bool) $this->option('inactive');
        $missing   = (bool) $this->option('missing');

        // ── Единичен продукт ─────────────────────────────────────────────────
        if ($productId) {
            $query = Product::query();
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
                Log::info('Auto search product start', [
                    'product_id' => $product->id,
                    'name'       => $product->name,
                ]);

                if ($missing) {
                    $this->searchMissingStores($product, $service, $store);
                } else {
                    $service->handle($product, $overwrite, $store);
                }

                $elapsed = round(microtime(true) - $start, 2);
                $this->info("Done in {$elapsed}s — product ID {$product->id}");
            } catch (\Throwable $e) {
                $this->error("Error on #{$product->id}: " . $e->getMessage());
                Log::error('Auto search product failed', [
                    'product_id' => $product->id,
                    'message'    => $e->getMessage(),
                ]);
                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        // ── Всички продукти ──────────────────────────────────────────────────
        $query = Product::query()->orderBy('id');
        if (! $inactive) {
            $query->where('is_active', 1);
        }

        // При --missing → само продукти с липсващи линкове (< брой магазини)
        if ($missing) {
            $storeCount = Store::count();
            $query->whereHas('competitorLinks', function ($q) {}, '<', $storeCount);
        }

        $total     = $query->count();
        $processed = 0;
        $failed    = 0;

        $this->info("Starting auto search for {$total} products" . ($store ? " [store: {$store}]" : '') . ($missing ? ' [missing only]' : '') . '...');

        $query->chunkById(1, function ($products) use ($service, $store, $overwrite, $missing, $total, &$processed, &$failed) {
            foreach ($products as $product) {
                $processed++;
                $this->line("[{$processed}/{$total}] {$product->id} — {$product->name}");

                Log::info('Auto search product start', [
                    'product_id' => $product->id,
                    'name'       => $product->name,
                ]);

                $start = microtime(true);

                try {
                    if ($missing) {
                        $this->searchMissingStores($product, $service, $store);
                    } else {
                        $service->handle($product, $overwrite, $store);
                    }
                    $elapsed = round(microtime(true) - $start, 2);
                    $this->info("  ✓ Done in {$elapsed}s");
                } catch (\Throwable $e) {
                    $failed++;
                    $elapsed = round(microtime(true) - $start, 2);
                    $this->error("  ✗ Error in {$elapsed}s: " . $e->getMessage());
                    Log::error('Auto search product failed', [
                        'product_id' => $product->id,
                        'message'    => $e->getMessage(),
                    ]);
                }

                gc_collect_cycles();
            }
        });

        $this->info("Finished. Processed: {$processed}, Failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * Търси само в магазини без съществуващ линк за продукта.
     */
    protected function searchMissingStores(Product $product, AutoProductSearchService $service, ?string $onlyStore): void
    {
        // Намери store_id-та с вече намерени линкове
        $existingStoreIds = CompetitorLink::where('product_id', $product->id)
            ->where('is_active', 1)
            ->pluck('store_id')
            ->toArray();

        // Намери магазини без линк
        $missingStores = Store::whereNotIn('id', $existingStoreIds)
            ->when($onlyStore, fn ($q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower($onlyStore)]))
            ->pluck('name')
            ->toArray();

        if (empty($missingStores)) {
            $this->line("  → All stores already have links.");
            return;
        }

        $this->line("  → Missing stores: " . implode(', ', $missingStores));

        foreach ($missingStores as $storeName) {
            $service->handle($product, false, $storeName);
        }
    }
}