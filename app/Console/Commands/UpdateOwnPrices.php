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
                            {--limit=0 : Максимален брой продукти (0 = без лимит)}';

    protected $description = 'Обновява Our Price и/или ПЦД от technika.bg';

    public function handle(OwnProductPriceService $priceService): int
    {
        $updatePrice = $this->option('price') || $this->option('all');
        $updatePcd   = $this->option('pcd')   || $this->option('all');
        $limit       = (int) $this->option('limit');

        if (!$updatePrice && !$updatePcd) {
            $updatePrice = true;
        }

        $updated   = 0;
        $failed    = 0;
        $processed = 0;

        $query = Product::where('is_active', 1)
            ->whereNotNull('product_url')
            ->where('product_url', 'like', '%technika.bg%')
            ->orderBy('id');

        $query->chunkById(20, function ($products) use ($priceService, $updatePrice, $updatePcd, $limit, &$updated, &$failed, &$processed) {

            foreach ($products as $product) {

                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }

                $processed++;

                try {
                    $data    = $priceService->getPriceData($product->product_url);
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
                    }

                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('UpdateOwnPrices failed', [
                        'product_id' => $product->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }, 'id');

        Log::info('prices:update-own finished', [
            'updated'   => $updated,
            'failed'    => $failed,
            'processed' => $processed,
        ]);

        $this->info("Done. Updated: {$updated}, Failed: {$failed}, Processed: {$processed}");

        return self::SUCCESS;
    }
}