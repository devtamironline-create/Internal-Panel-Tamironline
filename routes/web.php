<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\Staff\Http\Controllers\StaffController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;

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

// Home - redirect to admin login
Route::get('/', function () {
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

    // Global Search API
    Route::get('/search', [App\Http\Controllers\Admin\SearchController::class, 'search'])->name('search');

    // Notifications API
    Route::get('/notifications', [App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [App\Http\Controllers\Admin\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Messenger
    Route::get('/messenger', function () {
        return view('admin.messenger.index');
    })->name('messenger');

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

    // Chat System
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/users', [ChatController::class, 'users'])->name('users');
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
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount'])->name('unread-count');
        // Message reactions
        Route::post('/messages/{message}/reaction', [ChatController::class, 'toggleReaction'])->name('messages.reaction');
    });
});
