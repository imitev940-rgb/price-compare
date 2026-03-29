<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Store;
use App\Services\AutoProductSearchService;
use Illuminate\Console\Command;

class AutoSearchMissingZora extends Command
{
    protected $signature = 'products:search-missing-zora';
    protected $description = 'Auto search Zora links for products that do not have one';

    public function handle(): int
    {
        $zora = Store::where('name', 'Zora')->first();
        if (!$zora) { $this->error('Zora store not found.'); return Command::FAILURE; }

        $products = Product::where('is_active', 1)
            ->whereDoesntHave('competitorLinks', fn($q) => $q->where('store_id', $zora->id))
            ->get();

        $this->info('Products without Zora link: ' . $products->count());
        $service = app(AutoProductSearchService::class);

        foreach ($products as $product) {
            $this->info('Searching: ' . $product->name);
            try { $service->handle($product, false, 'Zora'); } catch (\Throwable $e) { $this->error($e->getMessage()); }
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
