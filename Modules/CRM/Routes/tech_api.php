<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\Api\V1\Technician\AuthController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\DashboardController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\InvoiceController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\ProfileController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\ProformaController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\TrainingController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\TransferReceiptController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\WalletController;
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
        Route::post('/orders/{id}/deliver-sms', [OrderActionController::class, 'sendDeliverSms'])->whereNumber('id')->name('api.tech.orders.deliver-sms');
        Route::post('/orders/{id}/call-result', [OrderActionController::class, 'callResult'])->whereNumber('id')->name('api.tech.orders.call-result');
        // رسیدِ انتقالِ دستی
        Route::post('/orders/{id}/transfer-receipt', [TransferReceiptController::class, 'store'])->whereNumber('id')->name('api.tech.orders.transfer-receipt');

        // داشبورد / تقویم
        Route::get('/dashboard', [DashboardController::class, 'calendar'])->name('api.tech.dashboard');

        // کیف‌پول
        Route::get('/wallet', [WalletController::class, 'index'])->name('api.tech.wallet');
        Route::post('/wallet/recharge', [WalletController::class, 'recharge'])->middleware('throttle:20,1')->name('api.tech.wallet.recharge');

        // فاکتورها
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('api.tech.invoices');

        // پیش‌فاکتور
        Route::get('/proformas', [ProformaController::class, 'index'])->name('api.tech.proformas.index');
        Route::post('/proformas', [ProformaController::class, 'store'])->name('api.tech.proformas.store');
        Route::get('/proformas/{id}', [ProformaController::class, 'show'])->whereNumber('id')->name('api.tech.proformas.show');
        Route::post('/proformas/{id}/finalize', [ProformaController::class, 'finalize'])->whereNumber('id')->name('api.tech.proformas.finalize');

        // آموزش
        Route::get('/training', [TrainingController::class, 'index'])->name('api.tech.training.index');
        Route::get('/training/categories/{id}', [TrainingController::class, 'category'])->whereNumber('id')->name('api.tech.training.category');
        Route::get('/training/uncategorized', [TrainingController::class, 'uncategorized'])->name('api.tech.training.uncategorized');
        Route::get('/training/videos/{id}', [TrainingController::class, 'show'])->whereNumber('id')->name('api.tech.training.video');
        Route::post('/training/videos/{id}/watched', [TrainingController::class, 'markWatched'])->whereNumber('id')->name('api.tech.training.watched');

        // پروفایل
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('api.tech.profile.avatar');
        Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('api.tech.profile.password');
    });
});
