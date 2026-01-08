<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\Staff\Http\Controllers\StaffController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\RoleController;

// Health Check for Coolify/Docker
Route::get('/health', function () {
    $status = ['status' => 'ok', 'timestamp' => now()->toISOString()];

    // Check database
    try {
        \DB::connection()->getPdo();
        $status['database'] = 'ok';
    } catch (\Exception $e) {
        $status['database'] = 'error';
        $status['status'] = 'degraded';
    }

    // Check redis
    try {
        \Illuminate\Support\Facades\Redis::ping();
        $status['redis'] = 'ok';
    } catch (\Exception $e) {
        $status['redis'] = 'error';
        $status['status'] = 'degraded';
    }

    return response()->json($status, $status['status'] === 'ok' ? 200 : 503);
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
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Admin Panel
Route::middleware(['auth', 'verified.mobile'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

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

    // Chat System
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/users', [ChatController::class, 'users'])->name('users');
        Route::get('/conversations', [ChatController::class, 'conversations'])->name('conversations');
        Route::post('/conversations/start', [ChatController::class, 'startConversation'])->name('conversations.start');
        Route::post('/conversations/group', [ChatController::class, 'createGroup'])->name('conversations.group');
        Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('messages');
        Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])->name('messages.send');
        Route::post('/presence', [ChatController::class, 'updatePresence'])->name('presence');
        Route::get('/online-users', [ChatController::class, 'onlineUsers'])->name('online-users');
        Route::post('/calls/initiate', [ChatController::class, 'initiateCall'])->name('calls.initiate');
        Route::post('/calls/{call}/answer', [ChatController::class, 'answerCall'])->name('calls.answer');
        Route::post('/calls/{call}/end', [ChatController::class, 'endCall'])->name('calls.end');
        Route::post('/calls/{call}/reject', [ChatController::class, 'rejectCall'])->name('calls.reject');
        Route::get('/calls/history', [ChatController::class, 'callHistory'])->name('calls.history');
        Route::get('/calls/incoming', [ChatController::class, 'checkIncomingCall'])->name('calls.incoming');
        Route::post('/signal', [ChatController::class, 'sendSignal'])->name('signal');
        Route::get('/unread-count', [ChatController::class, 'getUnreadCount'])->name('unread-count');
    });
});
