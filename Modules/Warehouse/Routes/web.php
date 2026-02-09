<?php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\Http\Controllers\WarehouseController;
use Modules\Warehouse\Http\Controllers\WooCommerceController;
use Modules\Warehouse\Http\Controllers\AmadestController;
use Modules\Warehouse\Http\Controllers\PackingController;
use Modules\Warehouse\Http\Controllers\PrintController;
use Modules\Warehouse\Http\Controllers\DispatchController;
use Modules\Warehouse\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| Warehouse Module Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->prefix('warehouse')->group(function () {
    // Order Journey
    Route::get('/journey', [WarehouseController::class, 'journey'])->name('warehouse.journey');
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
    Route::post('/{order}/supply-wait', [WarehouseController::class, 'markSupplyWait'])->name('warehouse.supply-wait')
        ->where('order', '[0-9]+');
    Route::delete('/{order}', [WarehouseController::class, 'destroy'])->name('warehouse.destroy')
        ->where('order', '[0-9]+');

    // Packing Station
    Route::prefix('packing')->group(function () {
        Route::get('/', [PackingController::class, 'index'])->name('warehouse.packing.index');
        Route::post('/scan-order', [PackingController::class, 'scanOrder'])->name('warehouse.packing.scan-order');
        Route::post('/verify-order-barcode', [PackingController::class, 'verifyOrderBarcode'])->name('warehouse.packing.verify-order-barcode');
        Route::post('/scan-product', [PackingController::class, 'scanProduct'])->name('warehouse.packing.scan-product');
        Route::post('/verify-weight', [PackingController::class, 'verifyWeight'])->name('warehouse.packing.verify-weight');
        Route::post('/force-verify', [PackingController::class, 'forceVerify'])->name('warehouse.packing.force-verify');
    });

    // Printing
    Route::get('/{order}/print/invoice', [PrintController::class, 'invoice'])->name('warehouse.print.invoice')
        ->where('order', '[0-9]+');
    Route::post('/{order}/print/mark-printed', [PrintController::class, 'markPrinted'])->name('warehouse.print.mark-printed')
        ->where('order', '[0-9]+');
    Route::get('/{order}/print/label', [PrintController::class, 'label'])->name('warehouse.print.label')
        ->where('order', '[0-9]+');

    // Dispatch
    Route::prefix('dispatch')->group(function () {
        Route::get('/', [DispatchController::class, 'index'])->name('warehouse.dispatch.index');
    });
    Route::post('/{order}/ship-post', [DispatchController::class, 'shipViaPost'])->name('warehouse.dispatch.ship-post')
        ->where('order', '[0-9]+');
    Route::post('/{order}/ship-courier', [DispatchController::class, 'shipViaCourier'])->name('warehouse.dispatch.ship-courier')
        ->where('order', '[0-9]+');
    Route::post('/{order}/delivered', [DispatchController::class, 'markDelivered'])->name('warehouse.dispatch.delivered')
        ->where('order', '[0-9]+');
    Route::post('/{order}/returned', [DispatchController::class, 'markReturned'])->name('warehouse.dispatch.returned')
        ->where('order', '[0-9]+');

    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('warehouse.settings.index');
        Route::put('/', [SettingsController::class, 'update'])->name('warehouse.settings.update');
        Route::get('/delete-invoice-logo', [SettingsController::class, 'deleteInvoiceLogo'])->name('warehouse.settings.delete-invoice-logo');
        Route::post('/shipping-type', [SettingsController::class, 'storeShippingType'])->name('warehouse.settings.shipping-type.store');
        Route::put('/shipping-type/{shippingType}', [SettingsController::class, 'updateShippingType'])->name('warehouse.settings.shipping-type.update');
    });

    // WooCommerce Integration
    Route::prefix('woocommerce')->group(function () {
        Route::get('/', [WooCommerceController::class, 'index'])->name('warehouse.woocommerce.index');
        Route::post('/save', [WooCommerceController::class, 'saveSettings'])->name('warehouse.woocommerce.save');
        Route::post('/test', [WooCommerceController::class, 'testConnection'])->name('warehouse.woocommerce.test');
        Route::post('/sync', [WooCommerceController::class, 'sync'])->name('warehouse.woocommerce.sync');
        Route::post('/sync-products', [WooCommerceController::class, 'syncProducts'])->name('warehouse.woocommerce.sync-products');
        Route::post('/shipping-methods', [WooCommerceController::class, 'fetchShippingMethods'])->name('warehouse.woocommerce.shipping-methods');
        Route::post('/shipping-mappings', [WooCommerceController::class, 'saveShippingMappings'])->name('warehouse.woocommerce.shipping-mappings');
    });

    // Amadest Integration
    Route::prefix('amadest')->group(function () {
        Route::get('/', [AmadestController::class, 'index'])->name('warehouse.amadest.index');
        Route::post('/save', [AmadestController::class, 'saveSettings'])->name('warehouse.amadest.save');
        Route::post('/test', [AmadestController::class, 'testConnection'])->name('warehouse.amadest.test');
        Route::post('/track', [AmadestController::class, 'track'])->name('warehouse.amadest.track');
    });
});
