<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\BrandController;
use Modules\CRM\Http\Controllers\CityController;
use Modules\CRM\Http\Controllers\CrmController;
use Modules\CRM\Http\Controllers\CustomerController;
use Modules\CRM\Http\Controllers\DeviceController;
use Modules\CRM\Http\Controllers\ImpersonateController;
use Modules\CRM\Http\Controllers\InvoiceController;
use Modules\CRM\Http\Controllers\LegacyImportController;
use Modules\CRM\Http\Controllers\OrderController;
use Modules\CRM\Http\Controllers\OrderItemController;
use Modules\CRM\Http\Controllers\PaymentController;
use Modules\CRM\Http\Controllers\ProvinceController;
use Modules\CRM\Http\Controllers\WalletController;
use Modules\CRM\Http\Controllers\SmsTemplateController;
use Modules\CRM\Http\Controllers\SyncSettingsController;
use Modules\CRM\Http\Controllers\TechPanelSettingsController;
use Modules\CRM\Http\Controllers\TrainingAdminController;
use Modules\CRM\Http\Controllers\TechDashboardController;
use Modules\CRM\Http\Controllers\TechnicianController;
use Modules\CRM\Http\Controllers\Tech\AuthController as TechAuthController;
use Modules\CRM\Http\Controllers\Tech\DashboardController as TechPanelDashboardController;

// ─── مسیرهای عمومی پرداخت (بدون نیاز به لاگین) ─────────────────────
Route::middleware('web')->group(function () {
    Route::get('/crm/pay/{invoiceCode}', [PaymentController::class, 'pay'])->name('crm.payment.pay');
    Route::post('/crm/pay/{invoiceCode}', [PaymentController::class, 'initiate'])->name('crm.payment.initiate');
    Route::match(['get', 'post'], '/crm/payment/callback', [PaymentController::class, 'callback'])->name('crm.payment.callback');
});

Route::middleware(['auth'])->prefix('admin/crm')->name('crm.')->group(function () {
    Route::get('/', [CrmController::class, 'dashboard'])
        ->middleware('can:view-crm-dashboard')
        ->name('dashboard');

    // ─── پنل تکنسین ───────────────────────────────────────────────
    Route::middleware('can:view-tech-dashboard')->prefix('tech')->name('tech.')->group(function () {
        Route::get('/', [TechDashboardController::class, 'index'])->name('dashboard');
        Route::get('wallet', [TechDashboardController::class, 'wallet'])->name('wallet');
        Route::get('invoices', [TechDashboardController::class, 'invoices'])->name('invoices');
        Route::get('profile', [TechDashboardController::class, 'profile'])->name('profile');

        Route::middleware('can:view-own-orders')->group(function () {
            Route::get('orders/{order}', [TechDashboardController::class, 'showOrder'])->name('orders.show');
        });
        Route::middleware('can:update-own-order-status')->group(function () {
            Route::post('orders/{order}/status', [TechDashboardController::class, 'updateStatus'])->name('orders.status');
        });
    });

    // ─── تاکسونومی ── برندها ───────────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
    });
    Route::middleware('can:manage-crm-brands')->group(function () {
        Route::get('brands/create', [BrandController::class, 'create'])->name('brands.create');
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::get('brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
        Route::put('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
    });

    // ─── تاکسونومی ── دستگاه‌ها ─────────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    });
    Route::middleware('can:manage-crm-devices')->group(function () {
        Route::get('devices/create', [DeviceController::class, 'create'])->name('devices.create');
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::get('devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
        Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });

    // ─── تاکسونومی ── استان‌ها ──────────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('provinces', [ProvinceController::class, 'index'])->name('provinces.index');
    });
    Route::middleware('can:manage-crm-provinces')->group(function () {
        Route::get('provinces/create', [ProvinceController::class, 'create'])->name('provinces.create');
        Route::post('provinces', [ProvinceController::class, 'store'])->name('provinces.store');
        Route::get('provinces/{province}/edit', [ProvinceController::class, 'edit'])->name('provinces.edit');
        Route::put('provinces/{province}', [ProvinceController::class, 'update'])->name('provinces.update');
        Route::delete('provinces/{province}', [ProvinceController::class, 'destroy'])->name('provinces.destroy');
    });

    // ─── تاکسونومی ── شهرها ────────────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('cities', [CityController::class, 'index'])->name('cities.index');
    });
    Route::middleware('can:manage-crm-cities')->group(function () {
        Route::get('cities/create', [CityController::class, 'create'])->name('cities.create');
        Route::post('cities', [CityController::class, 'store'])->name('cities.store');
        Route::get('cities/{city}/edit', [CityController::class, 'edit'])->name('cities.edit');
        Route::put('cities/{city}', [CityController::class, 'update'])->name('cities.update');
        Route::delete('cities/{city}', [CityController::class, 'destroy'])->name('cities.destroy');
    });

    // ─── مشتری‌ها ──────────────────────────────────────────────────
    Route::middleware('can:view-crm-customers')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/export/{format}', [CustomerController::class, 'export'])
            ->where('format', 'csv|xlsx')->name('customers.export');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        // Endpoint Ajax برای لود شهرهای هر استان (فرم مشتری/سفارش در فازهای بعد)
        Route::get('provinces/{province}/cities', [CustomerController::class, 'citiesOfProvince'])
            ->name('provinces.cities');
    });
    Route::middleware('can:create-crm-customer')->group(function () {
        Route::get('customers/create/new', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    });
    Route::middleware('can:edit-crm-customer')->group(function () {
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });
    Route::middleware('can:delete-crm-customer')->group(function () {
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    // ─── تکنسین‌های فعال ──────────────────────────────────────────
    Route::middleware('can:view-crm-technicians')->group(function () {
        Route::get('technicians', [TechnicianController::class, 'index'])->name('technicians.index');
        Route::get('technicians/export/{format}', [TechnicianController::class, 'export'])
            ->where('format', 'csv|xlsx')->name('technicians.export');
        Route::get('technicians/{technician}', [TechnicianController::class, 'show'])->name('technicians.show');
    });
    Route::middleware('can:create-crm-technician')->group(function () {
        Route::get('technicians/create/new', [TechnicianController::class, 'create'])->name('technicians.create');
        Route::post('technicians', [TechnicianController::class, 'store'])->name('technicians.store');
    });
    Route::middleware('can:edit-crm-technician')->group(function () {
        Route::get('technicians/{technician}/edit', [TechnicianController::class, 'edit'])->name('technicians.edit');
        Route::put('technicians/{technician}', [TechnicianController::class, 'update'])->name('technicians.update');
    });
    Route::middleware('can:delete-crm-technician')->group(function () {
        Route::delete('technicians/{technician}', [TechnicianController::class, 'destroy'])->name('technicians.destroy');
    });

    // لینک/جدا کردن حساب کاربری تکنسین (برای دسترسی به پنل)
    Route::middleware('can:edit-crm-technician')->group(function () {
        Route::post('technicians/{technician}/provision-user', [TechnicianController::class, 'provisionUser'])->name('technicians.provision-user');
        Route::post('technicians/{technician}/unlink-user', [TechnicianController::class, 'unlinkUser'])->name('technicians.unlink-user');
        Route::post('technicians/{technician}/impersonate', [ImpersonateController::class, 'start'])->name('technicians.impersonate');

        // تنظیم دستی مانده کیف‌پول (مبنای محاسبات از این لحظه به بعد)
        Route::post('technicians/{technician}/wallet/set-balance', [TechnicianController::class, 'setWalletBalance'])
            ->name('technicians.wallet.set-balance');
    });

    // خروج از حالت impersonate (نیازی به permission ندارد چون فقط
    // session.impersonator_id را برمی‌گرداند به ادمین قبلی).
    Route::post('impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');

    // ─── سفارش‌های تعمیر ─────────────────────────────────────────
    // داشبورد تکنسین: سفارش‌های خودم
    Route::get('my-orders', [OrderController::class, 'myOrders'])->name('orders.my');

    Route::middleware('can:view-crm-orders')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/export/{format}', [OrderController::class, 'export'])
            ->where('format', 'csv|xlsx')->name('orders.export');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    });
    Route::middleware('can:create-crm-order')->group(function () {
        Route::get('orders/create/new', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
    });
    Route::middleware('can:edit-crm-order')->group(function () {
        Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
        Route::put('orders/{order}', [OrderController::class, 'update'])->name('orders.update');

        // آیتم‌های سفارش (قطعه/خدمت/حمل/تخفیف)
        Route::post('orders/{order}/items', [OrderItemController::class, 'store'])->name('orders.items.store');
        Route::delete('orders/{order}/items/{item}', [OrderItemController::class, 'destroy'])->name('orders.items.destroy');
    });
    Route::middleware('can:delete-crm-order')->group(function () {
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    });

    Route::middleware('can:assign-crm-technician')->group(function () {
        Route::post('orders/{order}/assign', [OrderController::class, 'assign'])->name('orders.assign');
        Route::post('orders/{order}/unassign', [OrderController::class, 'unassign'])->name('orders.unassign');
    });

    Route::middleware('can:change-crm-order-status')->group(function () {
        Route::post('orders/{order}/status', [OrderController::class, 'changeStatus'])->name('orders.status.change');
        Route::post('orders/{order}/return', [OrderController::class, 'returnOrder'])->name('orders.return');
    });

    // ─── قالب‌های SMS و گزارش ارسال ────────────────────────────
    Route::middleware('can:manage-crm-sms-templates')->group(function () {
        Route::get('sms/templates', [SmsTemplateController::class, 'index'])->name('sms.templates.index');
        Route::get('sms/templates/{template}/edit', [SmsTemplateController::class, 'edit'])->name('sms.templates.edit');
        Route::put('sms/templates/{template}', [SmsTemplateController::class, 'update'])->name('sms.templates.update');
        Route::post('sms/templates/{template}/toggle', [SmsTemplateController::class, 'toggle'])->name('sms.templates.toggle');

        Route::get('sms/logs', [SmsTemplateController::class, 'logs'])->name('sms.logs');
    });

    // ─── کیف‌پول تکنسین ────────────────────────────────────────
    Route::middleware('can:view-crm-financial')->group(function () {
        Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
        Route::get('wallet/technician/{technician}', [WalletController::class, 'show'])->name('wallet.show');
    });
    Route::middleware('can:manage-crm-wallet')->group(function () {
        Route::post('wallet/technician/{technician}/transaction', [WalletController::class, 'storeTransaction'])->name('wallet.transaction.store');

        // افزودن فاکتور حسابداری — هم‌ارز add_financial.php در WP
        Route::get('wallet/add', [WalletController::class, 'addFinancial'])->name('wallet.add');
        Route::post('wallet/reward', [WalletController::class, 'storeReward'])->name('wallet.reward.store');
        Route::post('wallet/charge', [WalletController::class, 'storeCharge'])->name('wallet.charge.store');
    });

    // ─── فاکتورها ──────────────────────────────────────────────
    Route::middleware('can:view-crm-invoices')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/export/{format}', [InvoiceController::class, 'export'])
            ->where('format', 'csv|xlsx')->name('invoices.export');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });
    Route::middleware('can:manage-crm-financial')->group(function () {
        Route::post('orders/{order}/invoice', [InvoiceController::class, 'generate'])->name('orders.invoice.generate');
        Route::post('invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.paid');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    });

    // ─── درگاه پرداخت (ادمین) ─────────────────────────────────────
    Route::middleware('can:manage-crm-payment-gateway')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/settings', [PaymentController::class, 'settings'])->name('payments.settings');
        Route::post('payments/settings', [PaymentController::class, 'updateSettings'])->name('payments.settings.update');
    });

    // ─── انتقال داده از CRM وردپرسی ──────────────────────────────
    Route::middleware('can:manage-permissions')->prefix('legacy-import')->name('legacy-import.')->group(function () {
        Route::get('/', [LegacyImportController::class, 'index'])->name('index');
        Route::get('/status', [LegacyImportController::class, 'status'])->name('status');
        Route::post('/batch', [LegacyImportController::class, 'batch'])->name('batch');
    });

    // ─── سینک با CRM وردپرسی ─────────────────────────────────────
    Route::middleware('can:manage-crm-sync')->prefix('sync')->name('sync.')->group(function () {
        Route::get('/', [SyncSettingsController::class, 'index'])->name('settings');
        Route::post('regenerate', [SyncSettingsController::class, 'regenerate'])->name('regenerate');
        Route::get('plugin/download', [SyncSettingsController::class, 'downloadPlugin'])->name('plugin.download');
    });

    // ─── تنظیمات ظاهری پنل تکنسین (لوگو/بنر/هیرو/...) ──────────────
    Route::middleware('can:manage-crm-settings')->prefix('tech-panel-settings')->name('tech-panel-settings.')->group(function () {
        Route::get('/', [TechPanelSettingsController::class, 'index'])->name('index');
        Route::post('/', [TechPanelSettingsController::class, 'update'])->name('update');
        Route::delete('/image/{key}', [TechPanelSettingsController::class, 'deleteImage'])->name('delete-image');
    });

    // ─── مدیریت آموزش‌های ویدیویی تکنسین ──────────────────────────
    Route::middleware('can:manage-crm-settings')->prefix('training')->name('training.admin.')->group(function () {
        // دسته‌بندی‌ها
        Route::get('categories', [TrainingAdminController::class, 'categoriesIndex'])->name('categories');
        Route::post('categories', [TrainingAdminController::class, 'categoriesStore'])->name('categories.store');
        Route::put('categories/{category}', [TrainingAdminController::class, 'categoriesUpdate'])->name('categories.update');
        Route::delete('categories/{category}', [TrainingAdminController::class, 'categoriesDestroy'])->name('categories.destroy');

        // ویدیوها
        Route::get('videos', [TrainingAdminController::class, 'videosIndex'])->name('videos');
        Route::get('videos/create', [TrainingAdminController::class, 'videosCreate'])->name('videos.create');
        Route::post('videos', [TrainingAdminController::class, 'videosStore'])->name('videos.store');
        Route::get('videos/{video}/edit', [TrainingAdminController::class, 'videosEdit'])->name('videos.edit');
        Route::put('videos/{video}', [TrainingAdminController::class, 'videosUpdate'])->name('videos.update');
        Route::delete('videos/{video}', [TrainingAdminController::class, 'videosDestroy'])->name('videos.destroy');
    });
});

// ═══════════ پنل تکنسین (PWA) ═══════════════════════════════════════
// Auth جدا (guard=tech) روی crm_technicians. مستقل از /admin.
Route::prefix('tech')->name('tech.')->group(function () {
    // Guest — صفحات لاگین/ارسال OTP
    Route::middleware('guest:tech')->group(function () {
        Route::get('/', [TechAuthController::class, 'showLoginForm'])->name('login');
        Route::post('auth/send-otp', [TechAuthController::class, 'sendOtp'])->name('auth.send-otp');
        Route::post('auth/verify-otp', [TechAuthController::class, 'verifyOtp'])->name('auth.verify-otp');
        Route::post('auth/login-password', [TechAuthController::class, 'loginWithPassword'])->name('auth.login-password');
    });

    // Authenticated
    Route::middleware('auth:tech')->group(function () {
        Route::post('logout', [TechAuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [TechPanelDashboardController::class, 'index'])->name('dashboard');
        Route::get('calendar', [TechPanelDashboardController::class, 'calendar'])->name('calendar');
        Route::get('orders', [TechPanelDashboardController::class, 'orders'])->name('orders');
        Route::get('orders/{order}', [TechPanelDashboardController::class, 'showOrder'])->name('orders.show');
        Route::post('orders/{order}/status', [TechPanelDashboardController::class, 'updateOrderStatus'])->name('orders.update-status');
        Route::post('orders/{order}/notes', [TechPanelDashboardController::class, 'addOrderNote'])->name('orders.add-note');
        Route::post('orders/{order}/deliver-sms', [TechPanelDashboardController::class, 'sendDeliverSms'])->name('orders.deliver-sms');
        Route::get('wallet', [TechPanelDashboardController::class, 'wallet'])->name('wallet');
        Route::get('invoices', [TechPanelDashboardController::class, 'invoices'])->name('invoices');
        Route::get('profile', [TechPanelDashboardController::class, 'profile'])->name('profile');
        Route::post('profile', [TechPanelDashboardController::class, 'updateProfile'])->name('profile.update');
        Route::post('profile/password', [TechPanelDashboardController::class, 'updatePassword'])->name('profile.password');
        Route::post('profile/avatar', [TechPanelDashboardController::class, 'uploadAvatar'])->name('profile.avatar');
    });

    // خروج از حالت impersonate — بدون نیاز به guard auth، فقط بر اساس
    // session.tech_impersonator_user_id کار می‌کند.
    Route::post('impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
});
