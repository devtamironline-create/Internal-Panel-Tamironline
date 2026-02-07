<?php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\Http\Controllers\OrderController;
use Modules\Warehouse\Http\Controllers\SettingsController;

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

        // Orders Management
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/{order}', [OrderController::class, 'show'])->name('show');
            Route::get('/{order}/print', [OrderController::class, 'print'])->name('print');

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
        });
    });
