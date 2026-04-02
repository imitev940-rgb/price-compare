<?php

namespace App\Console\Commands;

use App\Models\PriceHistory;
use Illuminate\Console\Command;

class CleanupPriceHistory extends Command
{
    protected $signature = 'price-history:cleanup {days=90}';
    protected $description = 'Delete price history records older than given number of days';

    public function handle(): int
    {
        $days = (int) $this->argument('days');

        if ($days < 1) {
            $this->error('Days must be at least 1.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $count = PriceHistory::where('checked_at', '<', $cutoff)->delete();

        $this->info("Deleted {$count} price history records older than {$days} days.");

        return self::SUCCESS;
    }
}