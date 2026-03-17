<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\CompetitorLinkController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\PriceCheckController;

Route::get('/', function () {
    return redirect('/comparison');
});

Route::middleware(['auth'])->group(function () {

    Route::get('/comparison', [ComparisonController::class, 'index'])->name('comparison');

    Route::resource('products', ProductController::class);
    Route::resource('stores', StoreController::class);
    Route::resource('links', CompetitorLinkController::class);
    Route::get('/comparison', [ComparisonController::class, 'index'])->name('comparison');
Route::post('/prices/check', [PriceCheckController::class, 'run'])->name('prices.check');

});

require __DIR__.'/auth.php';