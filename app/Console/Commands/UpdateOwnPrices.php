<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\OwnProductPriceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateOwnPrices extends Command
{
    protected $signature = 'prices:update-own
                            {--pcd    : Обнови само ПЦД цените}
                            {--price  : Обнови само Our Price}
                            {--all    : Обнови и двете}
                            {--limit= : Максимален брой продукти (default: 999)}';

    protected $description = 'Обновява Our Price и/или ПЦД от technika.bg';

    public function handle(OwnProductPriceService $priceService): int
    {
        $updatePrice = $this->option('price') || $this->option('all');
        $updatePcd   = $this->option('pcd')   || $this->option('all');
        $limit       = (int) ($this->option('limit') ?? 999);

        if (!$updatePrice && !$updatePcd) {
            // По подразбиране обнови само Our Price
            $updatePrice = true;
        }

        $query = Product::where('is_active', 1)
            ->whereNotNull('product_url')
            ->where('product_url', 'like', '%technika.bg%')
            ->orderBy('id');

        $updated = 0;
        $failed  = 0;

        $query->limit($limit)->chunk(10, function ($products) use ($priceService, $updatePrice, $updatePcd, &$updated, &$failed) {
            foreach ($products as $product) {
                try {
                    $data = $priceService->getPriceData($product->product_url);

                    $changes = [];

                    if ($updatePrice && isset($data['price']) && $data['price'] > 0) {
                        $changes['our_price'] = number_format($data['price'], 2, '.', '');
                    }

                    if ($updatePcd) {
                        $changes['pcd_price'] = $data['pcd_price'] !== null
                            ? number_format($data['pcd_price'], 2, '.', '')
                            : null;
                    }

                    if (!empty($changes)) {
                        $product->update($changes);
                        $updated++;

                        Log::info('UpdateOwnPrices updated', [
                            'product_id' => $product->id,
                            'changes'    => $changes,
                        ]);
                    }

                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('UpdateOwnPrices failed', [
                        'product_id' => $product->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('prices:update-own finished', [
            'updated' => $updated,
            'failed'  => $failed,
        ]);

        $this->info("Done. Updated: {$updated}, Failed: {$failed}");

        return self::SUCCESS;
    }
}