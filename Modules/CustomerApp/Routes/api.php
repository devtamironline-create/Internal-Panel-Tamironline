<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerApp\Http\Controllers\Api\V1\AddressController;
use Modules\CustomerApp\Http\Controllers\Api\V1\BootstrapController;
use Modules\CustomerApp\Http\Controllers\Api\V1\DeviceController;
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
use Modules\CustomerApp\Http\Middleware\TrackTokenUsage;

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
        // فلو: شهر → منطقه (استان از کاربر پرسیده نمی‌شود؛ سرور تشخیص می‌دهد)
        Route::get('/locations/states', [LocationController::class, 'states'])
            ->name('api.customer.locations.states');
        Route::get('/locations/cities', [LocationController::class, 'cities'])
            ->name('api.customer.locations.cities');
        Route::get('/locations/districts', [LocationController::class, 'districts'])
            ->name('api.customer.locations.districts');

        // Services picker — انواع خدمات، ایرادات، دسته‌بندی، برند، بنر
        Route::get('/services/types', [ServiceController::class, 'types'])
            ->name('api.customer.services.types');
        Route::get('/services/objections', [ServiceController::class, 'objections'])
            ->name('api.customer.services.objections');
        Route::get('/services/categories', [ServiceController::class, 'categories'])
            ->name('api.customer.services.categories');
        Route::get('/services/brands', [ServiceController::class, 'brands'])
            ->name('api.customer.services.brands');
        Route::get('/services/banners', [ServiceController::class, 'banners'])
            ->name('api.customer.services.banners');

        // Holidays picker — public
        Route::get('/holidays', [HolidayController::class, 'index'])
            ->name('api.customer.holidays.index');

        // Review tags picker — لیست تگ‌های نقاط قوت/ضعف برای فرم نظرسنجی اپ
        Route::get('/reviews/tags', [ReviewController::class, 'tags'])
            ->name('api.customer.reviews.tags');

        // Bootstrap aggregate — splash endpoint
        Route::get('/bootstrap', BootstrapController::class)
            ->name('api.customer.bootstrap');

        // ─── Private — auth:sanctum + rolling token + device tracking ──
        Route::middleware(['auth:sanctum', RollingToken::class, TrackTokenUsage::class])->group(function () {

            // Reverse geocode نشان — private تا سهمیه‌ی API عمومی هدر نرود.
            // throttle عمداً سخت‌گیر (20/min) تا اشتباهات فرانت (StrictMode دوبل،
            // event موش روی move نه moveend، یا loop در state) با 429 فوری
            // مشخص شود به‌جای اینکه سهمیه نشان سکوت‌وار مصرف شود.
            Route::get('/locations/reverse-geocode', [LocationController::class, 'reverseGeocode'])
                ->middleware('throttle:20,1')
                ->name('api.customer.locations.reverse-geocode');

            // Device sessions management (Block 7)
            Route::get('/auth/devices', [DeviceController::class, 'index'])
                ->name('api.customer.auth.devices.index');
            Route::delete('/auth/devices/{id}', [DeviceController::class, 'destroy'])
                ->whereNumber('id')->name('api.customer.auth.devices.destroy');

            // Orders — customer-facing
            // cancel-reasons و pending-reviews قبل از {id} تا روت‌گذاری اشتباه نکند
            Route::get('/orders/cancel-reasons', [OrderController::class, 'cancelReasons'])
                ->name('api.customer.orders.cancel-reasons');
            Route::get('/orders/pending-reviews', [ReviewController::class, 'pending'])
                ->name('api.customer.orders.pending-reviews');
            Route::get('/orders', [OrderController::class, 'index'])
                ->name('api.customer.orders.index');
            // Writes حساس — rate-limit per user
            Route::post('/orders', [OrderController::class, 'store'])
                ->middleware([EnsureNoPendingReview::class, 'throttle:30,1'])
                ->name('api.customer.orders.store');
            Route::get('/orders/{id}', [OrderController::class, 'show'])
                ->whereNumber('id')->name('api.customer.orders.show');
            Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])
                ->whereNumber('id')->middleware('throttle:10,1')
                ->name('api.customer.orders.cancel');
            Route::get('/orders/{id}/version', [OrderController::class, 'version'])
                ->whereNumber('id')->name('api.customer.orders.version');
            Route::post('/orders/{id}/review', [ReviewController::class, 'store'])
                ->whereNumber('id')->middleware('throttle:10,1')
                ->name('api.customer.orders.review');

            // Invoice — JSON و HTML (قابل تبدیل به PDF)
            Route::get('/orders/{id}/invoice', [InvoiceController::class, 'show'])
                ->whereNumber('id')->name('api.customer.orders.invoice');
            Route::get('/orders/{id}/invoice.pdf', [InvoiceController::class, 'pdf'])
                ->whereNumber('id')->name('api.customer.orders.invoice.pdf');
            // Invoice با توکنِ عمومی — برای فلوی login-gatedِ اپ/PWA
            // (لینک پیامک → /invoice/{token}). فقط فاکتورِ خودِ مشتری؛
            // در غیر این صورت 404 (وجودِ فاکتور لو نرود).
            Route::get('/invoices/{token}', [InvoiceController::class, 'showByToken'])
                ->where('token', '[A-Za-z0-9]+')->name('api.customer.invoices.by-token');

            // Profile (full — جداگانه از /auth/me و /auth/complete-profile)
            Route::get('/profile', [ProfileController::class, 'show'])
                ->name('api.customer.profile.show');
            Route::put('/profile', [ProfileController::class, 'update'])
                ->name('api.customer.profile.update');

            // حذفِ حساب توسطِ خودِ کاربر — soft delete + ابطالِ همه‌ی توکن‌ها.
            // ادمین در پنل رکورد را می‌بیند و می‌تواند بازگرداند.
            Route::post('/delete-account', [ProfileController::class, 'deleteAccount'])
                ->name('api.customer.delete-account');

            // Notifications
            Route::get('/notifications', [NotificationController::class, 'index'])
                ->name('api.customer.notifications.index');
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
                ->name('api.customer.notifications.read-all');
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])
                ->name('api.customer.notifications.read');

            // Forum (انجمن در اپ) — adapter روی انجمنِ سایت؛ حریمِ خصوصی سمتِ سرور
            Route::get('/forum/topics', [\Modules\CustomerApp\Http\Controllers\Api\V1\ForumController::class, 'topics'])
                ->name('api.customer.forum.topics');
            Route::get('/forum/my-questions', [\Modules\CustomerApp\Http\Controllers\Api\V1\ForumController::class, 'myQuestions'])
                ->name('api.customer.forum.my-questions');
            Route::get('/forum/topics/{id}', [\Modules\CustomerApp\Http\Controllers\Api\V1\ForumController::class, 'topicShow'])
                ->whereNumber('id')->name('api.customer.forum.topics.show');
            // ~۵ سوال در ساعت برای هر کاربر (پیشنهادِ خودِ فرانت)
            Route::post('/forum/topics/{id}/comments', [\Modules\CustomerApp\Http\Controllers\Api\V1\ForumController::class, 'storeComment'])
                ->whereNumber('id')->middleware('throttle:5,60')
                ->name('api.customer.forum.comments.store');

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
