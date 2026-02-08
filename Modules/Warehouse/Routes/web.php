<?php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\Http\Controllers\OrderController;
use Modules\Warehouse\Http\Controllers\SettingsController;
use Modules\Warehouse\Http\Controllers\PackingController;
use Modules\Warehouse\Http\Controllers\CourierController;

/*
|--------------------------------------------------------------------------
| Warehouse Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth', 'verified.mobile'])
    ->prefix('admin/warehouse')
    ->name('warehouse.')
    ->group(function () {

        // Dashboard
        Route::get('/', [OrderController::class, 'dashboard'])->name('dashboard');

        // Floating Orders Panel API
        Route::get('/floating-orders', [OrderController::class, 'floatingOrders'])->name('floating-orders');

        // Preparation Queue
        Route::get('/queue', [PackingController::class, 'queue'])->name('queue');

        // Packing Station
        Route::prefix('packing')->name('packing.')->group(function () {
            Route::get('/', [PackingController::class, 'index'])->name('index');
            Route::post('/scan', [PackingController::class, 'scan'])->name('scan');
            Route::post('/complete', [PackingController::class, 'complete'])->name('complete');
        });

        // Courier Management
        Route::prefix('courier')->name('courier.')->group(function () {
            Route::get('/', [CourierController::class, 'index'])->name('index');
            Route::post('/store', [CourierController::class, 'store'])->name('store');
            Route::delete('/destroy', [CourierController::class, 'destroy'])->name('destroy');
            Route::post('/{order}/delivered', [CourierController::class, 'markDelivered'])->name('delivered');
        });

        // Orders Management
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/{order}', [OrderController::class, 'show'])->name('show');
            Route::get('/{order}/print', [OrderController::class, 'print'])->name('print');
            Route::get('/{order}/print-amadast', [OrderController::class, 'printAmadast'])->name('print-amadast');

            // Sync actions
            Route::post('/sync', [OrderController::class, 'sync'])->name('sync');
            Route::post('/sync-recent', [OrderController::class, 'syncRecent'])->name('sync-recent');
            Route::post('/{order}/sync', [OrderController::class, 'syncOrder'])->name('sync-order');

            // Order actions
            Route::patch('/{order}/status', [OrderController::class, 'updateStatus'])->name('update-status');
            Route::patch('/{order}/internal-status', [OrderController::class, 'updateInternalStatus'])->name('update-internal-status');
            Route::patch('/{order}/assign', [OrderController::class, 'assign'])->name('assign');
            Route::patch('/{order}/note', [OrderController::class, 'updateNote'])->name('update-note');
            Route::post('/{order}/mark-packed', [OrderController::class, 'markPacked'])->name('mark-packed');
            Route::post('/{order}/mark-shipped', [OrderController::class, 'markShipped'])->name('mark-shipped');
            Route::post('/{order}/mark-printed', [OrderController::class, 'markPrinted'])->name('mark-printed');

            // Weight management
            Route::patch('/{order}/weight', [OrderController::class, 'updateWeight'])->name('update-weight');

            // Courier management
            Route::post('/{order}/assign-courier', [OrderController::class, 'assignCourier'])->name('assign-courier');

            // Print management
            Route::get('/{order}/check-print-status', [OrderController::class, 'checkPrintStatus'])->name('check-print-status');
            Route::get('/{order}/print-logs', [OrderController::class, 'getPrintLogs'])->name('print-logs');

            // Bulk actions
            Route::post('/bulk-update', [OrderController::class, 'bulkUpdate'])->name('bulk-update');
        });

        // Sync Logs
        Route::get('/sync-logs', [OrderController::class, 'syncLogs'])->name('sync-logs');

        // Test Connection
        Route::post('/test-connection', [OrderController::class, 'testConnection'])->name('test-connection');

        // Settings (Admin only)
        Route::middleware('can:manage-warehouse')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::post('/', [SettingsController::class, 'update'])->name('update');
            Route::post('/test-connection', [SettingsController::class, 'testConnection'])->name('test-connection');
            Route::post('/warehouse', [SettingsController::class, 'updateWarehouseSettings'])->name('warehouse.update');

            // Amadast settings
            Route::post('/amadast', [SettingsController::class, 'updateAmadast'])->name('amadast.update');
            Route::post('/amadast/setup', [SettingsController::class, 'setupAmadast'])->name('amadast.setup');
            Route::post('/amadast/test', [SettingsController::class, 'testAmadastConnection'])->name('amadast.test');
            Route::get('/amadast/provinces', [SettingsController::class, 'getAmadastProvinces'])->name('amadast.provinces');
            Route::get('/amadast/cities', [SettingsController::class, 'getAmadastCities'])->name('amadast.cities');
        });

        // Amadast actions on orders
        Route::post('/orders/{order}/send-to-amadast', [OrderController::class, 'sendToAmadast'])->name('orders.send-to-amadast');
        Route::post('/orders/{order}/update-tracking', [OrderController::class, 'updateAmadastTracking'])->name('orders.update-tracking');
    });
