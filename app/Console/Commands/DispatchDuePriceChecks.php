<?php

namespace App\Console\Commands;

use App\Jobs\PriceCheckLinkJob;
use App\Models\CompetitorLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DispatchDuePriceChecks extends Command
{
    protected $signature = 'prices:dispatch-due
                            {--limit=1200  : Максимален брой jobs за dispatch}
                            {--store=     : Само за конкретен магазин (напр. Techmart)}
                            {--force      : Dispatch без проверка дали е due}';

    protected $description = 'Dispatch due competitor link price checks by priority and store interval';

    public function handle(): int
    {
        $now        = now();
        $limit      = (int) $this->option('limit');
        $storeFilter = $this->option('store')
            ? mb_strtolower(trim($this->option('store')))
            : null;
        $force      = (bool) $this->option('force');
        $dispatched = 0;

        $query = CompetitorLink::query()
            ->with([
                'product:id,is_active,scan_priority',
                'store:id,name',
            ])
            ->where('is_active', 1)
            ->whereHas('product', fn ($q) => $q->where('is_active', 1))
            ->orderByRaw('last_checked_at IS NULL DESC')
            ->orderBy('last_checked_at', 'asc')
            ->orderBy('id', 'asc');

        if ($storeFilter) {
            $query->whereHas('store', fn ($q) => $q->whereRaw('LOWER(name) = ?', [$storeFilter]));
        }

        $storeCounters = [];

        $query->chunkById($limit, function ($links) use (&$dispatched, &$storeCounters, $limit, $now, $force) {
            foreach ($links as $link) {
                if ($dispatched >= $limit) {
                    return false;
                }

                $product   = $link->product;
                $store     = $link->store;
                $storeName = mb_strtolower(trim((string) ($store->name ?? '')));

                if (! $product || ! $store || $storeName === '') {
                    continue;
                }

                $priority = mb_strtolower(trim((string) ($product->scan_priority ?? 'normal')));
                $hours    = $this->resolveHours($priority, $storeName);

                if ($hours === null) {
                    continue;
                }

                if (! $force) {
                    $lastCheckedAt = $link->last_checked_at;
                    $isDue         = $lastCheckedAt === null
                        || $lastCheckedAt->copy()->addHours($hours)->lte($now);

                    if (! $isDue) {
                        continue;
                    }
                }

                $dispatchKey = 'price_check_dispatching:' . $link->id;
                if (! $force && ! Cache::add($dispatchKey, 1, now()->addMinutes(10))) {
                    continue;
                }

                $queueName = $priority === 'top' ? 'price_top' : 'price';

                $storeCounters[$storeName] = ($storeCounters[$storeName] ?? 0) + 1;
                $delayMultiplier = in_array($storeName, ['zora', 'tehnomix']) ? 5 
                    : ($storeName === 'pazaruvaj' ? 3 : 1);
                $delaySeconds = ($storeCounters[$storeName] - 1) * $delayMultiplier;

                dispatch(
                    (new PriceCheckLinkJob($link->id))
                        ->onQueue($queueName)
                        ->delay(now()->addSeconds($delaySeconds))
                );

                $dispatched++;

                $this->line("  Dispatched [{$queueName}] link #{$link->id} — {$storeName}");
            }

            return true;
        }, 'id');

        $this->info("Dispatched {$dispatched} link checks (limit: {$limit}).");

        Log::info('prices:dispatch-due finished', [
            'dispatched' => $dispatched,
            'limit'      => $limit,
            'store'      => $storeFilter ?? 'all',
            'force'      => $force,
        ]);

        return self::SUCCESS;
    }

    private function resolveHours(string $priority, string $storeName): ?int
    {
        if ($priority === 'top') {
            return match ($storeName) {
                'pazaruvaj'    => 2,
                'technopolis'  => 3,
                'technomarket' => 6,
                'zora'         => 9,
                'techmart'     => 12,
                'tehnomix'     => 12,
                default        => 12,
            };
        }

        return match ($storeName) {
            'pazaruvaj'    => 3,
            'technopolis'  => 6,
            'technomarket' => 8,
            'zora'     => 10,
            'techmart'     => 12,
            'tehnomix'     => 12,
            default        => 24,
        };
    }
}
