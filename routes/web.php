<?php

use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\DataRestoreController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\Staff\Http\Controllers\StaffController;

// Health Check for Coolify/Docker
Route::get('/health', function () {
    $status = ['status' => 'ok', 'timestamp' => now()->toISOString()];

    // Check database (required)
    try {
        \DB::connection()->getPdo();
        $status['database'] = 'ok';
    } catch (\Exception $e) {
        $status['database'] = 'error';
        $status['status'] = 'error';
    }

    // Check redis (optional - don't fail health check if redis is down)
    try {
        \Illuminate\Support\Facades\Redis::ping();
        $status['redis'] = 'ok';
    } catch (\Exception $e) {
        $status['redis'] = 'unavailable';
        // Don't mark as error - redis is optional when using file session/cache
    }

    // Return 200 if database is ok, 503 only if database fails
    return response()->json($status, $status['database'] === 'ok' ? 200 : 503);
})->name('health');

// ─── Storage Proxy — جایگزین asset('storage/...') روی هاست‌های بدون symlink.
//     فایل‌های public disk را با PHP سرو می‌کند. wildcard {path} تا
//     ساب‌فولدر و فایل با پسوند هم match کند. ─────────────────────
Route::get('/file/{path}', [\App\Http\Controllers\StorageProxyController::class, 'serve'])
    ->where('path', '.+')
    ->name('storage.proxy');

// ─── آدرس کوتاهِ دانلود اپ تکنسین ────────────────────────────────
// «panel.tamironline.com/app» (و همین مسیر روی ساب‌دامین karbalad) →
// فایل APK. برای وقتی که تکنسین باید آدرس را دستی در مرورگر تایپ کند؛
// TechSubdomainScope هم 'app' را عبور می‌دهد.
Route::get('/app', function () {
    return redirect()->away('https://panel.tamironline.com/karbalad-ver4.apk');
})->name('app.download');

// ─── ساب‌دامین اختصاصی پنل تکنسین ─────────────────────────────────
// اگر TECH_SUBDOMAIN در .env ست شده باشد، root آن ساب‌دامین مستقیم به
// صفحه لاگین/داشبورد تکنسین می‌رود تا تکنسین‌ها مجبور نباشند /tech را
// تایپ کنند. این بلاک باید قبل از Route::get('/') عمومی باشد تا برای
// آن host زودتر مچ شود.
if ($techHost = config('app.tech_subdomain')) {
    Route::domain($techHost)->group(function () {
        Route::get('/', function () {
            return Auth::guard('tech')->check()
                ? redirect()->route('tech.dashboard')
                : redirect()->route('tech.login');
        })->name('tech.subdomain.home');
    });
}

// Home - تشخیص guard فعال و ارسال به مقصد درست
Route::get('/', function () {
    // اولویت با تکنسین — اگر تکنسین لاگین است، به داشبورد تکنسین برود
    // (نه admin login). جلوگیری از سردرگمی در PWA که اگر start_url
    // به / اشاره کند، به طور پیش‌فرض به admin می‌رفت.
    if (Auth::guard('tech')->check()) {
        return redirect()->route('tech.dashboard');
    }
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('admin.login');
})->name('home');

// Auth Routes
Route::middleware('guest')->group(function () {
    // Admin Login
    Route::get('/login', [AuthController::class, 'showAdminLoginForm'])->name('admin.login');
    Route::get('/tamironline-admin', [AuthController::class, 'showAdminLoginForm'])->name('login');

    // OTP endpoints
    Route::post('/auth/send-otp', [AuthController::class, 'sendOTP'])->name('auth.send-otp');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOTP'])->name('auth.verify-otp');
    Route::post('/auth/login-password', [AuthController::class, 'loginWithPassword'])->name('auth.login-password');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Admin Panel
Route::middleware(['auth', 'verified.mobile'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

    // Profile
    Route::get('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'index'])->name('profile');
    Route::get('/profile/edit', [App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [App\Http\Controllers\Admin\ProfileController::class, 'uploadAvatar'])->name('profile.avatar');

    // Settings
    Route::get('/settings', [App\Http\Controllers\Admin\SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');
    Route::get('/settings/delete-logo', [App\Http\Controllers\Admin\SettingController::class, 'deleteLogo'])->name('settings.delete-logo');
    Route::get('/settings/delete-favicon', [App\Http\Controllers\Admin\SettingController::class, 'deleteFavicon'])->name('settings.delete-favicon');
    Route::get('/settings/delete-sound', [App\Http\Controllers\Admin\SettingController::class, 'deleteSound'])->name('settings.delete-sound');

    // Global Search API
    Route::get('/search', [App\Http\Controllers\Admin\SearchController::class, 'search'])->name('search');

    // Notifications API
    Route::get('/notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Messenger — no.cache: خودِ صفحه هم نباید توسطِ مرورگر/LiteSpeed کش
    // شود؛ نسخهٔ کهنه یعنی توکنِ CSRF کهنه و وضعیتِ اولیهٔ غلط.
    Route::get('/messenger', function () {
        return view('admin.messenger.index');
    })->middleware('no.cache')->name('messenger');

    // Staff Management
    Route::resource('staff', StaffController::class)->except(['show']);
    Route::patch('staff/{staff}/toggle-status', [StaffController::class, 'toggleStatus'])->name('staff.toggle-status');

    // Role Management
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // Permission Management
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('/permissions/{user}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
    Route::put('/permissions/{user}', [PermissionController::class, 'update'])->name('permissions.update');

    // ─── قرارداد کارمندان (پرسنل) ─────────────────────────────────────
    // مدیریت و تأیید: دسترسی manage-staff-contracts
    Route::middleware('can:manage-staff-contracts')->prefix('staff-contracts')->name('staff-contracts.')->group(function () {
        Route::get('/', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'index'])->name('index');
        Route::get('/create', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'create'])->name('create');
        Route::post('/', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'store'])->name('store');
        Route::get('/{staffContract}', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'show'])->name('show');
        Route::post('/{staffContract}/approve', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'approve'])->name('approve');
        Route::post('/{staffContract}/reject', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'reject'])->name('reject');
        Route::post('/{staffContract}/regenerate-pdf', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'regeneratePdf'])->name('regenerate-pdf');
        Route::get('/{staffContract}/download', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'download'])->name('download');
        Route::get('/{staffContract}/file/{field}', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'document'])
            ->where('field', '[a-z0-9_]+')->name('file');
        // حذفِ قرارداد — دسترسیِ جداگانهٔ delete-staff-contracts، و با تأییدِ پیامکی.
        //
        // قرارداد سندی امضاشده همراه با مدارکِ هویتیِ کارمند است؛ `manage-staff-contracts`
        // برای دیدن و تأیید کافی است ولی برای پاک‌کردن نه. کد از سرویسِ احراز
        // هویتِ پنل به موبایلِ خودِ حذف‌کننده می‌رود.
        Route::middleware('can:delete-staff-contracts')->group(function () {
            Route::post('/{staffContract}/delete-code', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'requestDeleteCode'])->name('delete-code');
            Route::delete('/{staffContract}', [\Modules\Staff\Http\Controllers\Admin\StaffContractController::class, 'destroy'])->name('destroy');
        });
    });

    // گزارش فعالیت (فایل‌محور، نگهداری ۱۵ روزه) — فقط مدیر کل
    Route::middleware('can:manage-permissions')->group(function () {
        Route::get('/activity-log', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])->name('activity-log.index');
        Route::get('/activity-log/export', [\App\Http\Controllers\Admin\ActivityLogController::class, 'export'])->name('activity-log.export');
    });

    // Data Restore from backup database
    Route::get('/restore', [DataRestoreController::class, 'index'])->name('restore.index');
    Route::get('/restore/{table}', [DataRestoreController::class, 'show'])->name('restore.show');
    Route::post('/restore/{table}', [DataRestoreController::class, 'restore'])->name('restore.do');

    // Chat System — no.cache: پاسخ‌های بلادرنگ نباید توسطِ مرورگر/LiteSpeed کش شوند
    Route::prefix('chat')->name('chat.')->middleware('no.cache')->group(function () {
        Route::get('/users', [ChatController::class, 'users'])->name('users');
        // ضربانِ سبک: فقط می‌گوید چیزی عوض شده یا نه + تعدادِ خوانده‌نشده‌ها.
        Route::get('/tick', [ChatController::class, 'tick'])->name('tick');
        // اعلانِ سراسریِ پیام‌های جدید (روی همهٔ صفحات پنل) — پیام‌های طرفِ مقابل بعد از یک id.
        Route::get('/unread-recent', [ChatController::class, 'recentUnread'])->name('unread-recent');
        Route::get('/conversations', [ChatController::class, 'conversations'])->name('conversations');
        Route::post('/conversations/start', [ChatController::class, 'startConversation'])->name('conversations.start');
        Route::post('/conversations/group', [ChatController::class, 'createGroup'])->name('conversations.group');
        Route::post('/conversations/{conversation}/join', [ChatController::class, 'joinGroup'])->name('conversations.join');
        Route::post('/conversations/{conversation}/update', [ChatController::class, 'updateGroup'])->name('conversations.update');
        Route::delete('/conversations/{conversation}/delete', [ChatController::class, 'deleteGroup'])->name('conversations.delete');
        Route::post('/conversations/{conversation}/pin/personal', [ChatController::class, 'togglePersonalPin'])->name('conversations.pin.personal');
        Route::post('/conversations/{conversation}/pin/global', [ChatController::class, 'toggleGlobalPin'])->name('conversations.pin.global');
        Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('messages');
        Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])->name('messages.send');
        Route::get('/conversations/{conversation}/mentionable-users', [ChatController::class, 'mentionableUsers'])->name('mentionable-users');
        Route::get('/conversations/{conversation}/my-mentions', [ChatController::class, 'myMentions'])->name('my-mentions');
        Route::post('/mentions/{mention}/read', [ChatController::class, 'markMentionRead'])->name('mentions.read');
        Route::get('/conversations/{conversation}/search-messages', [ChatController::class, 'searchMessages'])->name('search-messages');
        Route::post('/presence', [ChatController::class, 'updatePresence'])->name('presence');
        Route::post('/activity-status', [ChatController::class, 'setActivityStatus'])->name('activity-status');
        Route::post('/heartbeat', [ChatController::class, 'heartbeat'])->name('heartbeat');
        Route::get('/online-users', [ChatController::class, 'onlineUsers'])->name('online-users');
        Route::post('/calls/initiate', [ChatController::class, 'initiateCall'])->name('calls.initiate');
        Route::post('/calls/{call}/answer', [ChatController::class, 'answerCall'])->name('calls.answer');
        Route::post('/calls/{call}/end', [ChatController::class, 'endCall'])->name('calls.end');
        Route::post('/calls/{call}/reject', [ChatController::class, 'rejectCall'])->name('calls.reject');
        Route::get('/calls/history', [ChatController::class, 'callHistory'])->name('calls.history');
        Route::get('/calls/incoming', [ChatController::class, 'checkIncomingCall'])->name('calls.incoming');
        Route::post('/signal', [ChatController::class, 'sendSignal'])->name('signal');
        Route::get('/signals/poll', [ChatController::class, 'pollCallSignals'])->name('signals.poll');
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount'])->name('unread-count');
        // Message reactions
        Route::post('/messages/{message}/reaction', [ChatController::class, 'toggleReaction'])->name('messages.reaction');

        // Announcements
        Route::get('/announcements', [ChatController::class, 'getAnnouncements'])->name('announcements');
        Route::get('/announcements/unread', [ChatController::class, 'getUnreadAnnouncements'])->name('announcements.unread');
        Route::post('/announcements/{announcement}/seen', [ChatController::class, 'markAnnouncementSeen'])->name('announcements.seen');
        Route::post('/messages/{message}/announcement', [ChatController::class, 'createAnnouncement'])->name('messages.announcement');

        // Message Tasks
        Route::post('/messages/{message}/task', [ChatController::class, 'createTask'])->name('messages.task');
        Route::get('/conversations/{conversation}/tasks', [ChatController::class, 'getConversationTasks'])->name('conversations.tasks');
        Route::post('/tasks/{task}/status', [ChatController::class, 'updateTaskStatus'])->name('tasks.status');
        Route::get('/tasks/my', [ChatController::class, 'getMyTasks'])->name('tasks.my');
    });
});

/*
|--------------------------------------------------------------------------
| قرارداد من (بخشِ خودِ کارمند)
|--------------------------------------------------------------------------
| هر کاربرِ لاگین‌کرده فقط قراردادهای خودش را می‌بیند و تکمیل می‌کند:
| بارگذاری مدارک (تا ۲۰ مگابایت)، امضای الکترونیک، ضبط ویدیوی احراز و
| در نهایت دانلود نسخهٔ PDF مهر و امضاشده پس از تأیید مدیریت.
*/
Route::middleware(['auth', 'verified.mobile'])
    ->prefix('my-contracts')
    ->name('my-contracts.')
    ->group(function () {
        Route::get('/', [\Modules\Staff\Http\Controllers\MyContractController::class, 'index'])->name('index');
        Route::get('/{contract}', [\Modules\Staff\Http\Controllers\MyContractController::class, 'show'])->name('show');
        Route::post('/{contract}/documents/{field}', [\Modules\Staff\Http\Controllers\MyContractController::class, 'uploadDocument'])
            ->where('field', '[a-z0-9_]+')->name('documents.upload');
        Route::delete('/{contract}/documents/{field}', [\Modules\Staff\Http\Controllers\MyContractController::class, 'deleteDocument'])
            ->where('field', '[a-z0-9_]+')->name('documents.delete');
        Route::post('/{contract}/signature', [\Modules\Staff\Http\Controllers\MyContractController::class, 'signature'])->name('signature');
        Route::post('/{contract}/video', [\Modules\Staff\Http\Controllers\MyContractController::class, 'video'])->name('video');
        Route::post('/{contract}/submit', [\Modules\Staff\Http\Controllers\MyContractController::class, 'submit'])->name('submit');
        Route::get('/{contract}/download', [\Modules\Staff\Http\Controllers\MyContractController::class, 'download'])->name('download');
        Route::get('/{contract}/file/{field}', [\Modules\Staff\Http\Controllers\MyContractController::class, 'file'])
            ->where('field', '[a-z0-9_]+')->name('file');
    });
