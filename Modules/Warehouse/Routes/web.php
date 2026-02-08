<?php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\Http\Controllers\WarehouseController;
use Modules\Warehouse\Http\Controllers\WooCommerceController;
use Modules\Warehouse\Http\Controllers\AmadestController;

/*
|--------------------------------------------------------------------------
| Warehouse Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('warehouse')->group(function () {
    // Order Journey
    Route::get('/', [WarehouseController::class, 'index'])->name('warehouse.index');
    Route::get('/create', [WarehouseController::class, 'create'])->name('warehouse.create');
    Route::post('/', [WarehouseController::class, 'store'])->name('warehouse.store');
    Route::get('/{order}', [WarehouseController::class, 'show'])->name('warehouse.show')
        ->where('order', '[0-9]+');
    Route::get('/{order}/edit', [WarehouseController::class, 'edit'])->name('warehouse.edit')
        ->where('order', '[0-9]+');
    Route::put('/{order}', [WarehouseController::class, 'update'])->name('warehouse.update')
        ->where('order', '[0-9]+');
    Route::patch('/{order}/status', [WarehouseController::class, 'updateStatus'])->name('warehouse.status')
        ->where('order', '[0-9]+');
    Route::delete('/{order}', [WarehouseController::class, 'destroy'])->name('warehouse.destroy')
        ->where('order', '[0-9]+');

    // WooCommerce Integration
    Route::prefix('woocommerce')->group(function () {
        Route::get('/', [WooCommerceController::class, 'index'])->name('warehouse.woocommerce.index');
        Route::post('/save', [WooCommerceController::class, 'saveSettings'])->name('warehouse.woocommerce.save');
        Route::post('/test', [WooCommerceController::class, 'testConnection'])->name('warehouse.woocommerce.test');
        Route::post('/sync', [WooCommerceController::class, 'sync'])->name('warehouse.woocommerce.sync');
    });

    // Amadest Integration
    Route::prefix('amadest')->group(function () {
        Route::get('/', [AmadestController::class, 'index'])->name('warehouse.amadest.index');
        Route::post('/save', [AmadestController::class, 'saveSettings'])->name('warehouse.amadest.save');
        Route::post('/test', [AmadestController::class, 'testConnection'])->name('warehouse.amadest.test');
        Route::post('/track', [AmadestController::class, 'track'])->name('warehouse.amadest.track');
    });
});
