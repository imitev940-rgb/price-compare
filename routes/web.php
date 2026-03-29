<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\CompetitorLinkController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\PriceCheckController;
use App\Http\Controllers\PriceHistoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductImportController;


Route::get('/', function () {
    return redirect('/comparison');
});

Route::get('/lang/{locale}', function ($locale) {
    $allowedLocales = ['bg', 'en', 'de', 'fr', 'es', 'ro', 'tr'];

    if (in_array($locale, $allowedLocales)) {
        Session::put('locale', $locale);
    }

    return Redirect::back();
})->name('lang.switch');

Route::middleware(['auth'])->group(function () {
    Route::get('/comparison', [ComparisonController::class, 'index'])->name('comparison');

    // IMPORT ROUTES ТРЯБВА ДА СА ПРЕДИ resource('products')
    Route::get('/products/import', [ProductImportController::class, 'create'])->name('products.import.create');
    Route::post('/products/import', [ProductImportController::class, 'store'])->name('products.import.store');

    Route::resource('products', ProductController::class);
    Route::post('/products/fetch-price', [ProductController::class, 'fetchPriceAjax'])->name('products.fetch-price');
    Route::post('/products/{product}/auto-search', [ProductController::class, 'autoSearch'])->name('products.auto-search');

    Route::resource('stores', StoreController::class);
    Route::resource('links', CompetitorLinkController::class);

    Route::post('/prices/check', [PriceCheckController::class, 'run'])->name('prices.check');
    Route::get('/price-history', [PriceHistoryController::class, 'index'])->name('price-history.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/clear', [NotificationController::class, 'clearAll'])->name('notifications.clear');

    Route::get('/comparison/export/csv', [ComparisonController::class, 'exportCsv'])->name('comparison.export.csv');
    Route::get('/comparison/export/excel', [ComparisonController::class, 'exportExcel'])->name('comparison.export.excel');
    Route::get('/comparison/export/pdf', [ComparisonController::class, 'exportPdf'])->name('comparison.export.pdf');

Route::get('/products/import/template', [ProductImportController::class, 'downloadTemplate'])
    ->name('products.import.template');

    });

require __DIR__ . '/auth.php';

