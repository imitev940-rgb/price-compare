<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
*/

Schedule::command('prices:dispatch-due')
    ->name('prices.dispatch_due')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('price-history:cleanup 90')
    ->name('price_history.cleanup')
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();