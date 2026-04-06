<?php

use Illuminate\Support\Facades\Route;
use Modules\Warehouse\Http\Controllers\WarehouseController;
use Modules\Warehouse\Http\Controllers\WooCommerceController;
use Modules\Warehouse\Http\Controllers\AmadestController;
use Modules\Warehouse\Http\Controllers\PackingController;
use Modules\Warehouse\Http\Controllers\PrintController;
use Modules\Warehouse\Http\Controllers\ReprintRequestController;
use Modules\Warehouse\Http\Controllers\DispatchController;
use Modules\Warehouse\Http\Controllers\TapinController;
use Modules\Warehouse\Http\Controllers\PostexController;
use Modules\Warehouse\Http\Controllers\Cod24Controller;
use Modules\Warehouse\Http\Controllers\ExitScanController;
use Modules\Warehouse\Http\Controllers\SettingsController;
use Modules\Warehouse\Http\Controllers\OrderPresenceController;
use Modules\Warehouse\Http\Controllers\StaffDistributionController;
use Modules\Warehouse\Http\Controllers\ManualOrderController;


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
    Route::patch('/{order}/shipping-type', [WarehouseController::class, 'updateShippingType'])->name('warehouse.update-shipping-type')
        ->where('order', '[0-9]+');
    Route::post('/bulk-status', [WarehouseController::class, 'bulkUpdateStatus'])->name('warehouse.bulk-status');
    Route::post('/{order}/confirm-and-print', [WarehouseController::class, 'confirmAndPrint'])->name('warehouse.confirm-and-print')
        ->where('order', '[0-9]+');
    Route::get('/quick-search', [WarehouseController::class, 'quickSearch'])->name('warehouse.quick-search');
    Route::delete('/{order}', [WarehouseController::class, 'destroy'])->name('warehouse.destroy')
        ->where('order', '[0-9]+');

    // Order presence (concurrent viewer detection)
    Route::post('/{order}/presence/heartbeat', [OrderPresenceController::class, 'heartbeat'])->name('warehouse.presence.heartbeat')
        ->where('order', '[0-9]+');
    Route::post('/{order}/presence/leave', [OrderPresenceController::class, 'leave'])->name('warehouse.presence.leave')
        ->where('order', '[0-9]+');

    // Exit Scan Station
    Route::prefix('exit-scan')->group(function () {
        Route::get('/', [ExitScanController::class, 'index'])->name('warehouse.exit-scan.index');
        Route::post('/scan-and-ship', [ExitScanController::class, 'scanAndShip'])->name('warehouse.exit-scan.scan-and-ship');
    });

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

    // Reprint Requests
    Route::prefix('reprint-requests')->group(function () {
        Route::get('/', [ReprintRequestController::class, 'index'])->name('warehouse.reprint-requests.index');
        Route::post('/{request}/approve', [ReprintRequestController::class, 'approve'])->name('warehouse.reprint-requests.approve');
        Route::post('/{request}/reject', [ReprintRequestController::class, 'reject'])->name('warehouse.reprint-requests.reject');
        Route::get('/pending-count', [ReprintRequestController::class, 'pendingCount'])->name('warehouse.reprint-requests.pending-count');
    });
    Route::post('/{order}/retry-register', [PrintController::class, 'retryRegister'])->name('warehouse.retry-register')
        ->where('order', '[0-9]+');
    Route::post('/{order}/save-tapin-location', [WarehouseController::class, 'saveTapinLocation'])->name('warehouse.save-tapin-location')
        ->where('order', '[0-9]+');
    Route::post('/{order}/save-postal-code', [WarehouseController::class, 'savePostalCode'])->name('warehouse.save-postal-code')
        ->where('order', '[0-9]+');
    Route::post('/{order}/save-address', [WarehouseController::class, 'saveAddress'])->name('warehouse.save-address')
        ->where('order', '[0-9]+');

    // Dispatch
    Route::prefix('dispatch')->group(function () {
        Route::get('/', [DispatchController::class, 'index'])->name('warehouse.dispatch.index');
        Route::get('/courier', [DispatchController::class, 'courierStation'])->name('warehouse.dispatch.courier');
        Route::post('/scan-ship', [DispatchController::class, 'scanAndShip'])->name('warehouse.dispatch.scan-ship');
    });
    Route::post('/{order}/ship-post', [DispatchController::class, 'shipViaPost'])->name('warehouse.dispatch.ship-post')
        ->where('order', '[0-9]+');
    Route::post('/{order}/ship-courier', [DispatchController::class, 'shipViaCourier'])->name('warehouse.dispatch.ship-courier')
        ->where('order', '[0-9]+');
    Route::post('/{order}/assign-courier', [DispatchController::class, 'assignCourier'])->name('warehouse.dispatch.assign-courier')
        ->where('order', '[0-9]+');
    Route::post('/{order}/delivered', [DispatchController::class, 'markDelivered'])->name('warehouse.dispatch.delivered')
        ->where('order', '[0-9]+');
    Route::post('/{order}/returned', [DispatchController::class, 'markReturned'])->name('warehouse.dispatch.returned')
        ->where('order', '[0-9]+');

    // Staff Distribution (تقسیم‌بندی سفارشات)
    Route::prefix('distribution')->group(function () {
        Route::get('/', [StaffDistributionController::class, 'index'])->name('warehouse.distribution.index');
        Route::post('/toggle-readiness', [StaffDistributionController::class, 'toggleReadiness'])->name('warehouse.distribution.toggle-readiness');
        Route::post('/distribute', [StaffDistributionController::class, 'distribute'])->name('warehouse.distribution.distribute');
        Route::put('/settings', [StaffDistributionController::class, 'updateSettings'])->name('warehouse.distribution.update-settings');
        Route::put('/eligible-users', [StaffDistributionController::class, 'updateEligibleUsers'])->name('warehouse.distribution.update-eligible-users');
        Route::post('/reset', [StaffDistributionController::class, 'resetAssignments'])->name('warehouse.distribution.reset');
        Route::put('/shipping-map', [StaffDistributionController::class, 'updateShippingMap'])->name('warehouse.distribution.update-shipping-map');
    });

    // Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('warehouse.settings.index');
        Route::put('/', [SettingsController::class, 'update'])->name('warehouse.settings.update');
        Route::get('/delete-invoice-logo', [SettingsController::class, 'deleteInvoiceLogo'])->name('warehouse.settings.delete-invoice-logo');
        Route::post('/shipping-type', [SettingsController::class, 'storeShippingType'])->name('warehouse.settings.shipping-type.store');
        Route::put('/shipping-type/{shippingType}', [SettingsController::class, 'updateShippingType'])->name('warehouse.settings.shipping-type.update');
        // Box Sizes
        Route::post('/box-size', [SettingsController::class, 'storeBoxSize'])->name('warehouse.settings.box-size.store');
        Route::put('/box-size/{boxSize}', [SettingsController::class, 'updateBoxSize'])->name('warehouse.settings.box-size.update');
        Route::delete('/box-size/{boxSize}', [SettingsController::class, 'deleteBoxSize'])->name('warehouse.settings.box-size.delete');
        // Shipping Rules
        Route::post('/shipping-rule', [SettingsController::class, 'storeShippingRule'])->name('warehouse.settings.shipping-rule.store');
        Route::put('/shipping-rule/{shippingRule}', [SettingsController::class, 'updateShippingRule'])->name('warehouse.settings.shipping-rule.update');
        Route::delete('/shipping-rule/{shippingRule}', [SettingsController::class, 'deleteShippingRule'])->name('warehouse.settings.shipping-rule.delete');
        // Working Hours
        Route::put('/working-hours', [SettingsController::class, 'updateWorkingHours'])->name('warehouse.settings.working-hours.update');
    });

    // Manual Order (سفارش دستی)
    Route::prefix('manual-order')->group(function () {
        Route::get('/create', [ManualOrderController::class, 'create'])->name('warehouse.manual-order.create');
        Route::post('/', [ManualOrderController::class, 'store'])->name('warehouse.manual-order.store');
        Route::get('/{order}/edit', [ManualOrderController::class, 'edit'])->name('warehouse.manual-order.edit')
            ->where('order', '[0-9]+');
        Route::put('/{order}', [ManualOrderController::class, 'update'])->name('warehouse.manual-order.update')
            ->where('order', '[0-9]+');
        Route::get('/search-products', [ManualOrderController::class, 'searchProducts'])->name('warehouse.manual-order.search-products');
        Route::get('/search-customers', [ManualOrderController::class, 'searchCustomers'])->name('warehouse.manual-order.search-customers');
        Route::post('/validate-coupon', [ManualOrderController::class, 'validateCoupon'])->name('warehouse.manual-order.validate-coupon');
    });

    // WooCommerce Integration
    Route::prefix('woocommerce')->group(function () {
        Route::get('/', [WooCommerceController::class, 'index'])->name('warehouse.woocommerce.index');
        Route::post('/save', [WooCommerceController::class, 'saveSettings'])->name('warehouse.woocommerce.save');
        Route::post('/test', [WooCommerceController::class, 'testConnection'])->name('warehouse.woocommerce.test');
        Route::post('/sync', [WooCommerceController::class, 'sync'])->name('warehouse.woocommerce.sync');
        Route::post('/toggle-status-sync', [WooCommerceController::class, 'toggleStatusSync'])->name('warehouse.woocommerce.toggle-status-sync');
        Route::post('/sync-products', [WooCommerceController::class, 'syncProducts'])->name('warehouse.woocommerce.sync-products');
        Route::post('/redetect-shipping', [WooCommerceController::class, 'redetectShippingTypes'])->name('warehouse.woocommerce.redetect-shipping');
        Route::post('/fix-zero-weight', [WooCommerceController::class, 'fixZeroWeightProducts'])->name('warehouse.woocommerce.fix-zero-weight');
        Route::post('/fix-zero-weight-variations', [WooCommerceController::class, 'fixZeroWeightVariations'])->name('warehouse.woocommerce.fix-zero-weight-variations');
        Route::post('/debug-product-weight', [WooCommerceController::class, 'debugProductWeight'])->name('warehouse.woocommerce.debug-product-weight');
        Route::post('/bulk-resync-to-wc', [WooCommerceController::class, 'bulkResyncToWc'])->name('warehouse.woocommerce.bulk-resync-to-wc');
        Route::get('/products-catalog', [WooCommerceController::class, 'productsCatalog'])->name('warehouse.woocommerce.products-catalog');
    });

    // Amadest Integration
    Route::prefix('amadest')->group(function () {
        Route::get('/', [AmadestController::class, 'index'])->name('warehouse.amadest.index');
        Route::post('/save', [AmadestController::class, 'saveSettings'])->name('warehouse.amadest.save');
        Route::post('/save-sender', [AmadestController::class, 'saveSenderInfo'])->name('warehouse.amadest.save-sender');
        Route::post('/register-user', [AmadestController::class, 'registerUser'])->name('warehouse.amadest.register-user');
        Route::post('/refresh-token', [AmadestController::class, 'refreshToken'])->name('warehouse.amadest.refresh-token');
        Route::post('/test', [AmadestController::class, 'testConnection'])->name('warehouse.amadest.test');
        Route::post('/track', [AmadestController::class, 'track'])->name('warehouse.amadest.track');
        Route::get('/stores', [AmadestController::class, 'getStores'])->name('warehouse.amadest.stores');
        Route::post('/select-store', [AmadestController::class, 'selectStore'])->name('warehouse.amadest.select-store');
    });

    // Tapin Integration
    Route::prefix('tapin')->group(function () {
        Route::get('/', [TapinController::class, 'index'])->name('warehouse.tapin.index');
        Route::post('/save', [TapinController::class, 'saveSettings'])->name('warehouse.tapin.save');
        Route::post('/test', [TapinController::class, 'testConnection'])->name('warehouse.tapin.test');
        Route::post('/track', [TapinController::class, 'track'])->name('warehouse.tapin.track');
        Route::get('/credit', [TapinController::class, 'getShopCredit'])->name('warehouse.tapin.credit');
        Route::post('/check-price', [TapinController::class, 'checkPrice'])->name('warehouse.tapin.check-price');
        Route::get('/shop-details', [TapinController::class, 'getShopDetails'])->name('warehouse.tapin.shop-details');
        Route::post('/set-provider', [TapinController::class, 'setProvider'])->name('warehouse.tapin.set-provider');
        Route::get('/pending-orders', [TapinController::class, 'getPendingOrders'])->name('warehouse.tapin.pending-orders');
        Route::post('/clear-barcodes', [TapinController::class, 'clearBarcodes'])->name('warehouse.tapin.clear-barcodes');
        Route::post('/bulk-register', [TapinController::class, 'bulkRegister'])->name('warehouse.tapin.bulk-register');
        Route::get('/diagnostic-states', [TapinController::class, 'diagnosticStates'])->name('warehouse.tapin.diagnostic-states');
        Route::get('/wc-states', [TapinController::class, 'tapinStatesForWc'])->name('warehouse.tapin.wc-states');
    });

    // Postex Integration
    Route::prefix('postex')->group(function () {
        Route::get('/', [PostexController::class, 'index'])->name('warehouse.postex.index');
        Route::post('/save', [PostexController::class, 'saveSettings'])->name('warehouse.postex.save');
        Route::post('/test', [PostexController::class, 'testConnection'])->name('warehouse.postex.test');
        Route::get('/wallet', [PostexController::class, 'getWalletBalance'])->name('warehouse.postex.wallet');
        Route::get('/profile', [PostexController::class, 'getUserProfile'])->name('warehouse.postex.profile');
        Route::get('/provinces', [PostexController::class, 'getProvinces'])->name('warehouse.postex.provinces');
        Route::get('/cities', [PostexController::class, 'getCities'])->name('warehouse.postex.cities');
        Route::post('/calculate-price', [PostexController::class, 'calculatePrice'])->name('warehouse.postex.calculate-price');
        Route::post('/track', [PostexController::class, 'track'])->name('warehouse.postex.track');
        Route::post('/set-provider', [PostexController::class, 'setProvider'])->name('warehouse.postex.set-provider');
        Route::get('/debug-create', [PostexController::class, 'debugCreateShipment'])->name('warehouse.postex.debug-create');
        Route::get('/probe-endpoints', [PostexController::class, 'probeEndpoints'])->name('warehouse.postex.probe-endpoints');
        Route::get('/cities/sample', [PostexController::class, 'sampleCities'])->name('warehouse.postex.cities-sample');
        Route::get('/debug-find-city', [PostexController::class, 'debugFindCity'])->name('warehouse.postex.debug-find-city');
    });

    // COD24 Integration
    Route::prefix('cod24')->group(function () {
        Route::get('/', [Cod24Controller::class, 'index'])->name('warehouse.cod24.index');
        Route::post('/save', [Cod24Controller::class, 'saveSettings'])->name('warehouse.cod24.save');
        Route::post('/test', [Cod24Controller::class, 'testConnection'])->name('warehouse.cod24.test');
        Route::get('/wallet', [Cod24Controller::class, 'getWalletBalance'])->name('warehouse.cod24.wallet');
        Route::get('/states', [Cod24Controller::class, 'getStates'])->name('warehouse.cod24.states');
        Route::get('/cities', [Cod24Controller::class, 'getCities'])->name('warehouse.cod24.cities');
        Route::get('/test-city', [Cod24Controller::class, 'testCityResolve'])->name('warehouse.cod24.test-city');
        Route::post('/calculate-price', [Cod24Controller::class, 'calculatePrice'])->name('warehouse.cod24.calculate-price');
        Route::post('/track', [Cod24Controller::class, 'track'])->name('warehouse.cod24.track');
        Route::post('/barcode-status', [Cod24Controller::class, 'getBarcodeStatus'])->name('warehouse.cod24.barcode-status');
        Route::post('/set-provider', [Cod24Controller::class, 'setProvider'])->name('warehouse.cod24.set-provider');
        Route::get('/city-map', [Cod24Controller::class, 'cityMap'])->name('warehouse.cod24.city-map');
    });
});
