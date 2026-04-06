<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // ── Проверка на цени (dispatch jobs на опашката) ─────────────────────
        $schedule->command('prices:dispatch-due')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // ── Почистване на стара история (по-стара от 90 дни) ────────────────
        $schedule->command('price-history:cleanup 90')
            ->daily()
            ->at('03:00')
            ->withoutOverlapping();

        // ── Auto search за продукти без линкове (всяка нощ) ─────────────────
        $schedule->command('products:auto-search --store=Techmart --missing')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('products:auto-search --store=Technopolis --missing')
            ->dailyAt('01:30')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('products:auto-search --store=Technomarket --missing')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('products:auto-search --store=Tehnomix --missing')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('products:auto-search --store=Zora --missing')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}