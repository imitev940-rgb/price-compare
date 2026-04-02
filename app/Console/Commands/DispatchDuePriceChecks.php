<?php

namespace App\Console\Commands;

use App\Jobs\PriceCheckLinkJob;
use App\Models\CompetitorLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DispatchDuePriceChecks extends Command
{
    protected $signature = 'prices:dispatch-due';
    protected $description = 'Dispatch due competitor link price checks by priority and store interval';

    public function handle(): int
    {
        $now = now();
        $limit = 150;
        $dispatched = 0;

        CompetitorLink::query()
            ->with([
                'product:id,is_active,scan_priority',
                'store:id,name',
            ])
            ->where('is_active', 1)
            ->whereHas('product', function ($q) {
                $q->where('is_active', 1);
            })
            ->orderByRaw('last_checked_at IS NULL DESC')
            ->orderBy('last_checked_at', 'asc')
            ->orderBy('id', 'asc')
            ->chunkById(200, function ($links) use (&$dispatched, $limit, $now) {
                foreach ($links as $link) {
                    if ($dispatched >= $limit) {
                        return false;
                    }

                    $product = $link->product;
                    $store = $link->store;
                    $storeName = strtolower(trim((string) ($store->name ?? '')));

                    if (!$product || !$store || $storeName === '') {
                        continue;
                    }

                    $priority = strtolower(trim((string) ($product->scan_priority ?? 'normal')));
                    $hours = $this->resolveHours($priority, $storeName);

                    if ($hours === null) {
                        continue;
                    }

                    $lastCheckedAt = $link->last_checked_at;

                    $isDue = $lastCheckedAt === null
                        || $lastCheckedAt->copy()->addHours($hours)->lte($now);

                    if (!$isDue) {
                        continue;
                    }

                    $queueName = $priority === 'top' ? 'price_top' : 'price';

                    /*
                     * Prevent duplicate dispatches in close scheduler runs
                     * while keeping the TTL short enough not to block future due scans.
                     */
                    $dispatchKey = 'price_check_dispatching:' . $link->id;

                    if (!Cache::add($dispatchKey, 1, now()->addMinutes(15))) {
                        continue;
                    }

                    dispatch(
                        (new PriceCheckLinkJob($link->id))
                            ->onQueue($queueName)
                    );

                    $dispatched++;
                }

                return true;
            }, 'id');

        $this->info("Dispatched {$dispatched} link checks (limit: {$limit}).");

        Log::info('prices:dispatch-due finished', [
            'count' => $dispatched,
            'limit' => $limit,
        ]);

        return self::SUCCESS;
    }

    private function resolveHours(string $priority, string $storeName): ?int
    {
        if ($priority === 'top') {
            return 1;
        }

        return match ($storeName) {
            'pazaruvaj' => 3,
            'technopolis' => 6,
            'technomarket' => 12,
            'techmart', 'tehnomix' => 24,
            default => 24,
        };
    }
}