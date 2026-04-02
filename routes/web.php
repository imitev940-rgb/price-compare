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
use App\Http\Controllers\ScanDashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

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

Route::middleware(['auth', 'active.user'])->group(function () {

    // ===============================
    // DASHBOARD
    // ===============================
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/comparison', [ComparisonController::class, 'index'])->name('comparison');

    // ===============================
    // PROFILE
    // ===============================
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    // ===============================
    // SETTINGS
    // ===============================
    Route::get('/settings', [SettingsController::class, 'edit'])
        ->name('settings.edit');

    Route::put('/settings', [SettingsController::class, 'update'])
        ->name('settings.update');

    // ===============================
    // IMPORT ROUTES
    // ===============================
    Route::get('/products/import', [ProductImportController::class, 'create'])
        ->name('products.import.create');

    Route::post('/products/import', [ProductImportController::class, 'store'])
        ->name('products.import.store');

    Route::get('/products/import/{importJob}', [ProductImportController::class, 'show'])
        ->name('products.import.show');

    Route::get('/products/import/{importJob}/status', [ProductImportController::class, 'status'])
        ->name('products.import.status');

    Route::get('/products/import/template', [ProductImportController::class, 'downloadTemplate'])
        ->name('products.import.template');

    // ===============================
    // PRODUCTS
    // ===============================
    Route::resource('products', ProductController::class);

    Route::post('/products/fetch-price', [ProductController::class, 'fetchPriceAjax'])
        ->name('products.fetch-price');

    Route::post('/products/{product}/auto-search', [ProductController::class, 'autoSearch'])
        ->name('products.auto-search');

    // ===============================
    // STORES & LINKS
    // ===============================
    Route::resource('stores', StoreController::class);
    Route::resource('links', CompetitorLinkController::class);

    // ===============================
    // PRICE CHECK
    // ===============================
    Route::post('/prices/check', [PriceCheckController::class, 'run'])
        ->name('prices.check');

    Route::get('/price-history', [PriceHistoryController::class, 'index'])
        ->name('price-history.index');

    // ===============================
    // NOTIFICATIONS
    // ===============================
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('/notifications/clear', [NotificationController::class, 'clearAll'])
        ->name('notifications.clear');

    // ===============================
    // EXPORTS
    // ===============================
    Route::get('/comparison/export/csv', [ComparisonController::class, 'exportCsv'])
        ->name('comparison.export.csv');

    Route::get('/comparison/export/excel', [ComparisonController::class, 'exportExcel'])
        ->name('comparison.export.excel');

    Route::get('/comparison/export/pdf', [ComparisonController::class, 'exportPdf'])
        ->name('comparison.export.pdf');

    // ===============================
    // TV DASHBOARD
    // ===============================
    Route::get('/tv-dashboard', [ComparisonController::class, 'tvDashboard'])
        ->name('tv.dashboard');

    Route::get('/tv-dashboard-data', [ComparisonController::class, 'tvDashboardData'])
        ->name('tv.dashboard.data');

    // ===============================
    // SCAN DASHBOARD
    // ===============================
    Route::get('/scan-dashboard', [ScanDashboardController::class, 'index'])
        ->name('scan.dashboard');

    // ===============================
    // ADMIN MANAGEMENT
    // ===============================
    Route::prefix('admin')->name('admin.')->group(function () {

        // Only Super Admin
        Route::middleware(['role:super_admin'])->group(function () {
            Route::get('/system-settings', [SystemSettingsController::class, 'edit'])
                ->name('system-settings.edit');

            Route::put('/system-settings', [SystemSettingsController::class, 'update'])
                ->name('system-settings.update');
        });

        // Super Admin + Admin
        Route::middleware(['role:super_admin,admin'])->group(function () {
            Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
                ->name('users.toggle-status');

            Route::resource('users', UserController::class);
        });
    });

    // ===============================
    // TEST ROLE ROUTES
    // ===============================
    Route::middleware(['role:super_admin,admin'])->group(function () {
        Route::get('/admin-only', function () {
            return 'Only Super Admin and Admin';
        });
    });

    Route::middleware(['role:super_admin,admin,manager'])->group(function () {
        Route::get('/manager-area', function () {
            return 'Manager level access';
        });
    });

    Route::middleware(['role:super_admin'])->group(function () {
        Route::get('/super-admin-only', function () {
            return 'Only Super Admin';
        });
    });
});

require __DIR__ . '/auth.php';