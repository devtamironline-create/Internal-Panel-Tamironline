<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\Api\V1\AuthController;

// prefix v1/* — هم‌خوان با باقی API های پروژه (Site, Catalog, ...). به‌علاوه
// bootstrap/app.php با $request->is('v1/*') اطمینان می‌دهد که exceptionها به
// JSON رندر شوند (به‌جای redirect HTML).
Route::prefix('v1/auth')->group(function () {
    // Public — rate-limited بر اساسِ «شمارهٔ موبایل» (نه IP)، چون این مسیرها از
    // BFF پراکسی می‌شوند و IP همهٔ کاربران یکسان است. تعریفِ limiterها در
    // AppServiceProvider (otp-send / otp-verify).
    Route::post('/send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:otp-send')->name('identity.send-otp');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify')->name('identity.verify-otp');

    // ورود از مینی‌اپِ بله — initData امضاشده با توکنِ ربات اعتبارسنجی می‌شود.
    Route::post('/bale-miniapp', [AuthController::class, 'baleMiniapp'])
        ->middleware('throttle:otp-verify')->name('identity.bale-miniapp');

    // Auth required
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('identity.me');
        // اتصالِ حسابِ بله به نشستِ فعلی (کاربری که با OTP داخلِ بله وارد شده) —
        // بدونِ این، bale_user_id ست نمی‌شود و نوتیفیکیشنِ بله نمی‌رسد.
        Route::post('/bale-link', [AuthController::class, 'baleLink'])
            ->middleware('throttle:otp-verify')->name('identity.bale-link');
        Route::post('/complete-profile', [AuthController::class, 'completeProfile'])->name('identity.complete-profile');
        Route::post('/logout', [AuthController::class, 'logout'])->name('identity.logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('identity.logout-all');
    });
});
