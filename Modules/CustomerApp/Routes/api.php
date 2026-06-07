<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerApp\Http\Controllers\Api\V1\AddressController;
use Modules\CustomerApp\Http\Controllers\Api\V1\LocationController;
use Modules\CustomerApp\Http\Controllers\Api\V1\OrderController;
use Modules\CustomerApp\Http\Controllers\Api\V1\ServiceController;
use Modules\CustomerApp\Http\Controllers\Api\V1\StatusController;
use Modules\CustomerApp\Http\Middleware\ApiEnvelope;
use Modules\CustomerApp\Http\Middleware\IdempotencyKey;
use Modules\CustomerApp\Http\Middleware\RollingToken;

/*
|--------------------------------------------------------------------------
| Customer App API — /v1/customer/*
|--------------------------------------------------------------------------
| روت‌های اپلیکیشن موبایل/PWA مشتری‌ها. envelope استاندارد، Idempotency-Key،
| RollingToken و auth:sanctum روی همه‌ی روت‌های private اعمال می‌شوند.
| Auth subject: Modules\CRM\Models\Customer (همان توکن Identity).
|
| قراردادها در docs/MOBILE_API_CONTRACT.md
*/

Route::prefix('v1/customer')
    ->middleware([
        ApiEnvelope::class,
        IdempotencyKey::class,
    ])
    ->group(function () {

        // ─── Public — هیچ auth لازم نیست ─────────────────────────
        Route::get('/status', StatusController::class)->name('api.customer.status');

        // Locations برای picker — public با cache بلندمدت
        Route::get('/locations/states', [LocationController::class, 'states'])
            ->name('api.customer.locations.states');
        Route::get('/locations/cities', [LocationController::class, 'cities'])
            ->name('api.customer.locations.cities');

        // Services picker — انواع خدمات و ایرادات per-device
        Route::get('/services/types', [ServiceController::class, 'types'])
            ->name('api.customer.services.types');
        Route::get('/services/objections', [ServiceController::class, 'objections'])
            ->name('api.customer.services.objections');

        // ─── Private — auth:sanctum + rolling token ──────────────
        Route::middleware(['auth:sanctum', RollingToken::class])->group(function () {

            // Orders — customer-facing
            // cancel-reasons قبل از {id} تا روت‌گذاری اشتباه نکند
            Route::get('/orders/cancel-reasons', [OrderController::class, 'cancelReasons'])
                ->name('api.customer.orders.cancel-reasons');
            Route::get('/orders', [OrderController::class, 'index'])
                ->name('api.customer.orders.index');
            Route::post('/orders', [OrderController::class, 'store'])
                ->name('api.customer.orders.store');
            Route::get('/orders/{id}', [OrderController::class, 'show'])
                ->whereNumber('id')->name('api.customer.orders.show');
            Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])
                ->whereNumber('id')->name('api.customer.orders.cancel');
            Route::get('/orders/{id}/version', [OrderController::class, 'version'])
                ->whereNumber('id')->name('api.customer.orders.version');

            // Addresses — multi-address per customer
            Route::get('/addresses', [AddressController::class, 'index'])
                ->name('api.customer.addresses.index');
            Route::post('/addresses', [AddressController::class, 'store'])
                ->name('api.customer.addresses.store');
            Route::get('/addresses/{id}', [AddressController::class, 'show'])
                ->whereNumber('id')
                ->name('api.customer.addresses.show');
            Route::put('/addresses/{id}', [AddressController::class, 'update'])
                ->whereNumber('id')
                ->name('api.customer.addresses.update');
            Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])
                ->whereNumber('id')
                ->name('api.customer.addresses.destroy');

            // در بلوک‌های بعدی orders/reviews/notifications/... اضافه می‌شوند
        });
    });
