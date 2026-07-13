<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\Api\V1\Technician\AuthController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderController;
use Modules\CRM\Http\Middleware\EnsureTechnician;
use Modules\CRM\Http\Middleware\TechRollingToken;

/*
|--------------------------------------------------------------------------
| Technician App API — /v1/technician/*
|--------------------------------------------------------------------------
| API توکنیِ اپِ موبایل/PWA تکنسین. Auth subject: Modules\CRM\Models\Technician
| (توکنِ Sanctum). این فایل با middleware('api') و بدونِ prefixِ اضافه لود می‌شود
| (RouteServiceProvider) پس مسیرها زیرِ /v1/technician قرار می‌گیرند — مثلِ اپِ مشتری.
|
| قرارداد: پاسخِ استانداردِ { success, data|message }. روی هر پاسخِ خصوصیِ موفق،
| هدرِ X-Renewed-Token برای session کشویی می‌آید. 401=session مرده، 403=غیرتکنسین،
| 422=اعتبارسنجی.
*/

Route::prefix('v1/technician')->group(function () {

    // ─── Public (auth) — throttle per mobile ─────────────────────
    Route::post('/auth/send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:otp-send')->name('api.tech.auth.send-otp');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify')->name('api.tech.auth.verify-otp');

    // ─── Private — auth:sanctum + tech guard + rolling token ─────
    Route::middleware(['auth:sanctum', EnsureTechnician::class, TechRollingToken::class])->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('api.tech.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.tech.auth.logout');

        // سفارش‌ها
        Route::get('/orders', [OrderController::class, 'index'])->name('api.tech.orders.index');
        Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('api.tech.orders.show');

        // اکشن‌های سفارش (نوشتن)
        Route::post('/orders/{id}/status', [OrderActionController::class, 'updateStatus'])->whereNumber('id')->name('api.tech.orders.status');
        Route::post('/orders/{id}/schedule-visit', [OrderActionController::class, 'scheduleVisit'])->whereNumber('id')->name('api.tech.orders.schedule-visit');
        Route::post('/orders/{id}/notes', [OrderActionController::class, 'addNote'])->whereNumber('id')->name('api.tech.orders.notes');
    });
});
