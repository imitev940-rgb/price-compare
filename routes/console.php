<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Проверка на цени (dispatch jobs на опашката) ─────────────────────
Schedule::command('prices:dispatch-due')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// ── Our Price - обнови всеки час ─────────────────────────────────────
Schedule::command('prices:update-own --price')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// ── ПЦД - обнови веднъж дневно ───────────────────────────────────────
Schedule::command('prices:update-own --pcd')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground();

// ── Почистване на стара история (по-стара от 90 дни) ────────────────
Schedule::command('price-history:cleanup 90')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping();

// ── Auto search за продукти без линкове (всяка нощ) ─────────────────
Schedule::command('products:auto-search --store=Techmart --missing')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('products:auto-search --store=Technopolis --missing')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('products:auto-search --store=Technomarket --missing')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('products:auto-search --store=Tehnomix --missing')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('products:auto-search --store=Zora --missing')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();