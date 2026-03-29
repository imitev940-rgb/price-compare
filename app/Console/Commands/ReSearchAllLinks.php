<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Store;
use App\Models\CompetitorLink;
use App\Services\AutoProductSearchService;
use Illuminate\Console\Command;

class ReSearchAllLinks extends Command
{
    protected $signature = 'products:re-search {product_id? : ID на конкретен продукт} {--store= : Само за конкретен магазин (Zora, Technopolis, Technomarket, Pazaruvaj)}';
    protected $description = 'Изтрива стари линкове и търси наново';

    public function handle(): int
    {
        $productId = $this->argument('product_id');
        $onlyStore = $this->option('store');

        $query = Product::where('is_active', 1);
        if ($productId) $query->where('id', $productId);
        $products = $query->get();

        $this->info('Продукти за обработка: ' . $products->count());

        foreach ($products as $product) {
            $deleteQuery = CompetitorLink::where('product_id', $product->id);
            if ($onlyStore) {
                $store = Store::where('name', $onlyStore)->first();
                if ($store) $deleteQuery->where('store_id', $store->id);
            }
            $deleted = $deleteQuery->delete();
            $this->line("  [{$product->id}] {$product->name} → изтрити {$deleted} линка");
        }

        $this->info('Стари линкове изтрити. Започвам търсене...');
        $this->newLine();

        $service = app(AutoProductSearchService::class);

        foreach ($products as $product) {
            $this->info("[{$product->id}] Търся: {$product->name} (SKU: {$product->sku}, Model: {$product->model})");

            try {
                // Подаваме onlyStore за да не презаписваме другите магазини
                $service->handle($product, true, $onlyStore);

                $linksQuery = CompetitorLink::with('store')->where('product_id', $product->id);
                if ($onlyStore) {
                    $store = Store::where('name', $onlyStore)->first();
                    if ($store) $linksQuery->where('store_id', $store->id);
                }
                $links = $linksQuery->get();

                if ($links->isEmpty()) {
                    $this->warn('  → Не са намерени линкове');
                } else {
                    foreach ($links as $link) {
                        $this->line('  → [' . $link->store->name . '] ' . $link->product_url);
                    }
                }
            } catch (\Throwable $e) {
                $this->error('  ГРЕШКА: ' . $e->getMessage());
            }

            $this->newLine();
        }

        $this->info('Готово!');
        return Command::SUCCESS;
    }
}