<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerApp\Http\Controllers\Api\V1\AddressController;
use Modules\CustomerApp\Http\Controllers\Api\V1\BootstrapController;
use Modules\CustomerApp\Http\Controllers\Api\V1\HolidayController;
use Modules\CustomerApp\Http\Controllers\Api\V1\InvoiceController;
use Modules\CustomerApp\Http\Controllers\Api\V1\LocationController;
use Modules\CustomerApp\Http\Controllers\Api\V1\NotificationController;
use Modules\CustomerApp\Http\Controllers\Api\V1\OrderController;
use Modules\CustomerApp\Http\Controllers\Api\V1\ProfileController;
use Modules\CustomerApp\Http\Controllers\Api\V1\ReviewController;
use Modules\CustomerApp\Http\Controllers\Api\V1\ServiceController;
use Modules\CustomerApp\Http\Controllers\Api\V1\StatusController;
use Modules\CustomerApp\Http\Middleware\ApiEnvelope;
use Modules\CustomerApp\Http\Middleware\EnsureNoPendingReview;
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

        // Holidays picker — public
        Route::get('/holidays', [HolidayController::class, 'index'])
            ->name('api.customer.holidays.index');

        // Bootstrap aggregate — splash endpoint
        Route::get('/bootstrap', BootstrapController::class)
            ->name('api.customer.bootstrap');

        // ─── Private — auth:sanctum + rolling token ──────────────
        Route::middleware(['auth:sanctum', RollingToken::class])->group(function () {

            // Orders — customer-facing
            // cancel-reasons و pending-reviews قبل از {id} تا روت‌گذاری اشتباه نکند
            Route::get('/orders/cancel-reasons', [OrderController::class, 'cancelReasons'])
                ->name('api.customer.orders.cancel-reasons');
            Route::get('/orders/pending-reviews', [ReviewController::class, 'pending'])
                ->name('api.customer.orders.pending-reviews');
            Route::get('/orders', [OrderController::class, 'index'])
                ->name('api.customer.orders.index');
            Route::post('/orders', [OrderController::class, 'store'])
                ->middleware(EnsureNoPendingReview::class)
                ->name('api.customer.orders.store');
            Route::get('/orders/{id}', [OrderController::class, 'show'])
                ->whereNumber('id')->name('api.customer.orders.show');
            Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])
                ->whereNumber('id')->name('api.customer.orders.cancel');
            Route::get('/orders/{id}/version', [OrderController::class, 'version'])
                ->whereNumber('id')->name('api.customer.orders.version');
            Route::post('/orders/{id}/review', [ReviewController::class, 'store'])
                ->whereNumber('id')->name('api.customer.orders.review');

            // Invoice — JSON و HTML (قابل تبدیل به PDF)
            Route::get('/orders/{id}/invoice', [InvoiceController::class, 'show'])
                ->whereNumber('id')->name('api.customer.orders.invoice');
            Route::get('/orders/{id}/invoice.pdf', [InvoiceController::class, 'pdf'])
                ->whereNumber('id')->name('api.customer.orders.invoice.pdf');

            // Profile (full — جداگانه از /auth/me و /auth/complete-profile)
            Route::get('/profile', [ProfileController::class, 'show'])
                ->name('api.customer.profile.show');
            Route::put('/profile', [ProfileController::class, 'update'])
                ->name('api.customer.profile.update');

            // Notifications
            Route::get('/notifications', [NotificationController::class, 'index'])
                ->name('api.customer.notifications.index');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
                ->name('api.customer.notifications.read-all');
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
                ->name('api.customer.notifications.read');

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
