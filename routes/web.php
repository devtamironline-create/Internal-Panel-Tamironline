<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\Staff\Http\Controllers\StaffController;
use Modules\Invoice\Http\Controllers\InvoiceController;
use App\Http\Controllers\Admin\ChatController;

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

    // Invoices
    Route::resource('invoices', InvoiceController::class);
    Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.update-status');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.download-pdf');

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
