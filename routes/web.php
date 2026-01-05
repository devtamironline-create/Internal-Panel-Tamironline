<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\AuthController;
use Modules\Core\Http\Controllers\DashboardController;
use Modules\Staff\Http\Controllers\StaffController;
use Modules\Customer\Http\Controllers\CustomerController;
use Modules\Customer\Http\Controllers\CustomerCategoryController;
use Modules\Product\Http\Controllers\ProductController;
use Modules\Product\Http\Controllers\ProductCategoryController;
use Modules\Product\Http\Controllers\ProductAddonController;
use Modules\Service\Http\Controllers\ServiceController;
use Modules\Invoice\Http\Controllers\InvoiceController;
use Modules\Ticket\Http\Controllers\TicketController;
use App\Http\Controllers\Admin\ServerController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ChatController;

// Home - Customer Login or redirect if logged in
Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->is_staff
            ? redirect()->route('admin.dashboard')
            : redirect()->route('panel.dashboard');
    }
    return app(AuthController::class)->showLoginForm();
})->name('login');

// Auth Routes
Route::middleware('guest')->group(function () {
    // Admin Login - Secret URL
    Route::get('/hostlino-admin-1644', [AuthController::class, 'showAdminLoginForm'])->name('admin.login');

    // OTP endpoints
    Route::post('/auth/send-otp', [AuthController::class, 'sendOTP'])->name('auth.send-otp');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOTP'])->name('auth.verify-otp');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/stop-impersonate', [CustomerController::class, 'stopImpersonate'])->name('stop-impersonate');
});

// Admin Panel
Route::middleware(['auth', 'verified.mobile'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

    // Messenger
    Route::get('/messenger', function () {
        return view('admin.messenger.index');
    })->name('messenger');

    Route::resource('staff', StaffController::class)->except(['show']);
    Route::patch('staff/{staff}/toggle-status', [StaffController::class, 'toggleStatus'])->name('staff.toggle-status');

    // Customers
    Route::resource('customers', CustomerController::class);
    Route::patch('customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
    Route::post('customers/{customer}/create-account', [CustomerController::class, 'createAccount'])->name('customers.create-account');
    Route::get('customers/{customer}/impersonate', [CustomerController::class, 'impersonate'])->name('customers.impersonate');

    // Customer Categories
    Route::resource('customer-categories', CustomerCategoryController::class)->except(['show']);

    // Products
    Route::resource('products', ProductController::class);

    // Product Categories
    Route::resource('product-categories', ProductCategoryController::class)->except(['show']);

    // Product Addons
    Route::resource('product-addons', ProductAddonController::class)->except(['show']);

    // Services
    Route::get('/services/get-price', [ServiceController::class, 'getProductPrice'])->name('services.get-price');
    Route::resource('services', ServiceController::class);

    // Invoices
    Route::resource('invoices', InvoiceController::class);
    Route::patch('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.update-status');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.download-pdf');

    // Tickets
    Route::resource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::patch('tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
    Route::patch('tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('tickets.reopen');

    // Servers
    Route::resource('servers', ServerController::class)->except(['show']);

    // Import
    Route::get('/import', [ImportController::class, 'index'])->name('import.index');
    Route::post('/import/preview', [ImportController::class, 'preview'])->name('import.preview');
    Route::post('/import/process', [ImportController::class, 'process'])->name('import.process');

    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/monthly-renewals', [ReportController::class, 'monthlyRenewals'])->name('reports.monthly-renewals');

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

// Customer Panel
Route::middleware(['auth', 'verified.mobile'])->prefix('panel')->name('panel.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Panel\DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [\App\Http\Controllers\Panel\ProfileController::class, 'index'])->name('profile');
    Route::get('/profile/edit', [\App\Http\Controllers\Panel\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\Panel\ProfileController::class, 'update'])->name('profile.update');

    // Services
    Route::get('/services', [\App\Http\Controllers\Panel\ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [\App\Http\Controllers\Panel\ServiceController::class, 'show'])->name('services.show');

    // Invoices
    Route::get('/invoices', [\App\Http\Controllers\Panel\InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [\App\Http\Controllers\Panel\InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/pdf', [\App\Http\Controllers\Panel\InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');

    // Tickets
    Route::get('/tickets', [\App\Http\Controllers\Panel\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [\App\Http\Controllers\Panel\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [\App\Http\Controllers\Panel\TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [\App\Http\Controllers\Panel\TicketController::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [\App\Http\Controllers\Panel\TicketController::class, 'reply'])->name('tickets.reply');
    Route::patch('/tickets/{ticket}/close', [\App\Http\Controllers\Panel\TicketController::class, 'close'])->name('tickets.close');
});
