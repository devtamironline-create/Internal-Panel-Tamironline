<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\Api\V1\Technician\AnnouncementController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\AuthController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\ChatController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\DashboardController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\InvoiceController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\ProfileController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\ProformaController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\PushTokenController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\TicketController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\TrainingController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\TransferReceiptController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\WalletController;
use Modules\CRM\Http\Middleware\EnsureTechnician;
use Modules\CRM\Http\Middleware\RequireTrainingCompleted;
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
    // RequireTrainingCompleted: تکنسینِ آموزش‌ندیده حتی با توکنِ معتبر هم
    // به مسیرهای کاری نمی‌رسد (allowlist داخلِ خودِ میدل‌ور است) — گیتِ
    // سمتِ اپ مکمل است، نه جایگزین.
    Route::middleware(['upload.size', 'auth:sanctum', EnsureTechnician::class, TechRollingToken::class, RequireTrainingCompleted::class])->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('api.tech.me');
        // مهلت‌ها و متنِ پیام‌های سیستم SLA — اپ هر ۳۰ دقیقه refresh می‌کند.
        Route::get('/app-config', \Modules\CRM\Http\Controllers\Api\V1\Technician\AppConfigController::class)
            ->name('api.tech.app-config');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.tech.auth.logout');

        // توکنِ پوش — امروز فقط ذخیره می‌شود؛ ارسالی در کار نیست.
        Route::post('/me/push-token', [PushTokenController::class, 'store'])->name('api.tech.push-token.store');
        Route::delete('/me/push-token', [PushTokenController::class, 'destroy'])->name('api.tech.push-token.destroy');

        // سفارش‌ها
        Route::get('/orders', [OrderController::class, 'index'])->name('api.tech.orders.index');
        Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('api.tech.orders.show');

        // اکشن‌های سفارش (نوشتن)
        Route::post('/orders/{id}/status', [OrderActionController::class, 'updateStatus'])->whereNumber('id')->name('api.tech.orders.status');
        Route::post('/orders/{id}/schedule-visit', [OrderActionController::class, 'scheduleVisit'])->whereNumber('id')->name('api.tech.orders.schedule-visit');
        Route::post('/orders/{id}/notes', [OrderActionController::class, 'addNote'])->whereNumber('id')->name('api.tech.orders.notes');
        Route::post('/orders/{id}/deliver-sms', [OrderActionController::class, 'sendDeliverSms'])->whereNumber('id')->name('api.tech.orders.deliver-sms');
        Route::post('/orders/{id}/call-result', [OrderActionController::class, 'callResult'])->whereNumber('id')->name('api.tech.orders.call-result');
        Route::post('/orders/{id}/return-review', [OrderActionController::class, 'returnReview'])->whereNumber('id')->name('api.tech.orders.return-review');
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

        // تیکتِ پشتیبانی + دسته‌ها (منوی «تیکت جدید»)
        Route::get('/ticket-categories', [TicketController::class, 'categories'])->name('api.tech.ticket-categories');
        Route::get('/tickets', [TicketController::class, 'index'])->name('api.tech.tickets.index');
        Route::post('/tickets', [TicketController::class, 'store'])->name('api.tech.tickets.store');
        Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id')->name('api.tech.tickets.show');
        Route::post('/tickets/{id}/reply', [TicketController::class, 'reply'])->whereNumber('id')->name('api.tech.tickets.reply');

        // چت با اپراتور + اعلانات — no.cache: پاسخ‌های بلادرنگ نباید توسطِ
        // کشِ مرورگر/پروکسی/LiteSpeed ذخیره شوند (باگِ «پیام بعد از پاک‌کردنِ کش»).
        Route::middleware('no.cache')->group(function () {
            // نبضِ سبکِ همگام‌سازیِ پس‌زمینه — زیرِ no.cache چون کلِ فایده‌اش
            // «تازه بودن» است.
            Route::get('/me/sync', \Modules\CRM\Http\Controllers\Api\V1\Technician\SyncController::class)
                ->name('api.tech.sync');

            Route::get('/messages', [ChatController::class, 'index'])->name('api.tech.messages.index');
            Route::post('/messages', [ChatController::class, 'send'])->name('api.tech.messages.send');
            Route::get('/messages/unread', [ChatController::class, 'unread'])->name('api.tech.messages.unread');
            Route::post('/messages/read', [ChatController::class, 'markRead'])->name('api.tech.messages.read');

            Route::get('/announcements', [AnnouncementController::class, 'index'])->name('api.tech.announcements.index');
            Route::get('/announcements/unacked', [AnnouncementController::class, 'unacked'])->name('api.tech.announcements.unacked');
            Route::post('/announcements/{id}/ack', [AnnouncementController::class, 'ack'])->whereNumber('id')->name('api.tech.announcements.ack');
        });
    });
});
