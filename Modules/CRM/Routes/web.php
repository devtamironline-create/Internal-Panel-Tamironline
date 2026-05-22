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
use Modules\CRM\Http\Controllers\TrainingFileController;
use Modules\CRM\Http\Controllers\TechDashboardController;
use Modules\CRM\Http\Controllers\TechnicianController;
use Modules\CRM\Http\Controllers\Tech\AuthController as TechAuthController;
use Modules\CRM\Http\Controllers\Tech\DashboardController as TechPanelDashboardController;

// ─── سرو تصاویر برند پنل تکنسین (لوگو/بنر/Hero) — عمومی، چون در صفحه
//     ورود قبل از احراز هویت استفاده می‌شوند. جایگزین asset('storage/...')
//     که روی هاست بدون symlink ۴۰۴ می‌شود. ─────────────────────────
Route::get('/tech-panel/image/{key}', [\Modules\CRM\Http\Controllers\TechPanelSettingsController::class, 'serve'])
    ->name('crm.tech-panel-settings.serve')
    ->where('key', 'tech_panel_logo|tech_panel_banner|tech_panel_hero|tech_panel_default_avatar');

// ─── سرو تصاویر تیکت‌های پشتیبانی — درون کنترلر دسترسی چک می‌شود
//     (ادمین با view-crm-tickets یا تکنسینِ مالک تیکت).
Route::middleware('web')->group(function () {
    Route::get('/crm/ticket-image/{kind}/{id}', [\Modules\CRM\Http\Controllers\TicketController::class, 'serveImage'])
        ->name('crm.tickets.image')
        ->where('kind', 'ticket|reply')
        ->where('id', '[0-9]+');
});

// ─── مسیرهای عمومی پرداخت (بدون نیاز به لاگین) ─────────────────────
Route::middleware('web')->group(function () {
    Route::get('/crm/pay/{invoiceCode}', [PaymentController::class, 'pay'])->name('crm.payment.pay');
    Route::post('/crm/pay/{invoiceCode}', [PaymentController::class, 'initiate'])->name('crm.payment.initiate');
    Route::match(['get', 'post'], '/crm/payment/callback', [PaymentController::class, 'callback'])->name('crm.payment.callback');

    // ─── فایل‌های آموزش (ویدیو + تامبنیل) — auth داخلی ─────────────
    Route::get('/crm/training/{video}/video', [TrainingFileController::class, 'streamVideo'])
        ->name('crm.training.file.video');
    Route::get('/crm/training/{video}/thumbnail', [TrainingFileController::class, 'streamThumbnail'])
        ->name('crm.training.file.thumbnail');
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
        Route::post('technicians/{technician}/training-gate', [TechnicianController::class, 'toggleTrainingGate'])->name('technicians.training-gate');
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

        // یادداشت‌های اپراتور — هر کاربری که می‌تواند سفارش را ببیند،
        // می‌تواند یادداشت اضافه/حذف کند (حذف فقط یادداشت خودش).
        Route::post('orders/{order}/notes', [OrderController::class, 'storeNote'])->name('orders.notes.store');
        Route::delete('orders/{order}/notes/{note}', [OrderController::class, 'destroyNote'])->name('orders.notes.destroy')->whereNumber('note');
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
        Route::post('orders/{order}/source-of-truth', [OrderController::class, 'updateSourceOfTruth'])->name('orders.source-of-truth');
    });

    Route::middleware('can:change-crm-order-status')->group(function () {
        Route::post('orders/{order}/status', [OrderController::class, 'changeStatus'])->name('orders.status.change');
        Route::post('orders/{order}/return', [OrderController::class, 'returnOrder'])->name('orders.return');
    });

    // ─── قالب‌های SMS و گزارش ارسال ────────────────────────────
    Route::middleware('can:manage-crm-sms-templates')->group(function () {
        // ─── صفحه یکپارچه مدیریت پیامک (تنظیمات + قالب‌ها + تست) ───
        Route::get('sms-management', [\Modules\CRM\Http\Controllers\SmsManagementController::class, 'index'])->name('sms-management.index');
        Route::post('sms-management/settings', [\Modules\CRM\Http\Controllers\SmsManagementController::class, 'updateSettings'])->name('sms-management.settings.update');
        Route::post('sms-management/template/{template}', [\Modules\CRM\Http\Controllers\SmsManagementController::class, 'updateTemplate'])->name('sms-management.template.update');
        Route::post('sms-management/template/{template}/toggle', [\Modules\CRM\Http\Controllers\SmsManagementController::class, 'toggle'])->name('sms-management.template.toggle');
        Route::post('sms-management/test', [\Modules\CRM\Http\Controllers\SmsManagementController::class, 'test'])->name('sms-management.test');

        // ─── route های قدیمی برای backward-compat ───
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
        Route::post('invoices/{invoice}/push-to-wp', [InvoiceController::class, 'pushToWp'])->name('invoices.push-to-wp');
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
    // ─── مدیریت دسته‌بندی تیکت‌ها (قبل از tickets/{ticket} باشد!) ──
    Route::middleware('can:manage-crm-settings')->prefix('tickets/categories')->name('tickets.categories.')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\TicketCategoryController::class, 'index'])->name('index');
        Route::post('/', [\Modules\CRM\Http\Controllers\TicketCategoryController::class, 'store'])->name('store');
        Route::put('{category}', [\Modules\CRM\Http\Controllers\TicketCategoryController::class, 'update'])->name('update')->whereNumber('category');
        Route::delete('{category}', [\Modules\CRM\Http\Controllers\TicketCategoryController::class, 'destroy'])->name('destroy')->whereNumber('category');
    });

    // ─── تیکت‌های پشتیبانی تکنسین (سمت ادمین) ─────────────────────
    // {ticket} با whereNumber محدود شده تا 'categories' را به اشتباه match نکند.
    Route::middleware('can:view-crm-tickets')->group(function () {
        Route::get('tickets', [\Modules\CRM\Http\Controllers\TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [\Modules\CRM\Http\Controllers\TicketController::class, 'show'])->name('tickets.show')->whereNumber('ticket');
    });
    Route::middleware('can:reply-crm-tickets')->group(function () {
        Route::post('tickets/{ticket}/reply', [\Modules\CRM\Http\Controllers\TicketController::class, 'reply'])->name('tickets.reply')->whereNumber('ticket');
        Route::patch('tickets/{ticket}/status', [\Modules\CRM\Http\Controllers\TicketController::class, 'updateStatus'])->name('tickets.status')->whereNumber('ticket');
    });

    Route::middleware('can:manage-crm-sync')->prefix('sync')->name('sync.')->group(function () {
        Route::get('/', [SyncSettingsController::class, 'index'])->name('settings');
        Route::post('regenerate', [SyncSettingsController::class, 'regenerate'])->name('regenerate');
        Route::get('plugin/download', [SyncSettingsController::class, 'downloadPlugin'])->name('plugin.download');
        // تنظیمات سینک معکوس Laravel → WP
        Route::post('wp-push', [SyncSettingsController::class, 'updateWpPush'])->name('wp-push.update');
        Route::post('resync-technicians', [SyncSettingsController::class, 'resyncTechnicians'])->name('resync-technicians');
        // قفل/بازکردن داده تکنسین در Laravel
        Route::post('tech-lock', [SyncSettingsController::class, 'updateTechLock'])->name('tech-lock.update');
        Route::get('tech-snapshot/download', [SyncSettingsController::class, 'downloadTechSnapshot'])->name('tech-snapshot.download');
    });

    // ─── ابزارهای داده (import / resync / recompute) — برای مسئول داده ───
    Route::middleware('can:manage-crm-sync')->prefix('data-tools')->name('data-tools.')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'index'])->name('index');
        Route::post('import-tech-from-wp', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'importTechFromWp'])->name('import-tech-from-wp');
        Route::post('rebuild-tech-wallet', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'rebuildTechWallet'])->name('rebuild-tech-wallet');
        Route::post('resync-technicians', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'resyncTechnicians'])->name('resync-technicians');
        Route::post('resync-invoices', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'resyncInvoices'])->name('resync-invoices');
        Route::post('resync-wallet-transactions', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'resyncWalletTransactions'])->name('resync-wallet-transactions');
        Route::post('recompute-balances', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'recomputeBalances'])->name('recompute-balances');
        Route::post('activate-by-name', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'activateTechniciansByName'])->name('activate-by-name');
        Route::post('resync-order-statuses', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'resyncOrderStatuses'])->name('resync-order-statuses');
        Route::post('toggle-tech-readonly', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'toggleTechPanelReadonly'])->name('toggle-tech-readonly');
        Route::post('set-sync-mode', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'setSyncMode'])->name('set-sync-mode');
        Route::post('wallet-audit', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'walletAudit'])->name('wallet-audit');
        Route::get('bulk-percent', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'bulkPercentForm'])->name('bulk-percent');
        Route::post('bulk-percent', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'bulkPercentApply'])->name('bulk-percent.apply');
        Route::get('bulk-balance', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'bulkBalanceForm'])->name('bulk-balance');
        Route::post('bulk-balance', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'bulkBalanceApply'])->name('bulk-balance.apply');
    });

    // ─── مدیریت گروهی تکنسین‌ها — ادیت inline + soft delete ─────
    Route::middleware('can:manage-crm-sync')->prefix('tech-manage')->name('tech-manage.')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\TechManagementController::class, 'index'])->name('index');
        Route::get('trash', [\Modules\CRM\Http\Controllers\TechManagementController::class, 'trash'])->name('trash');
        Route::post('zero-all-wallets', [\Modules\CRM\Http\Controllers\TechManagementController::class, 'zeroAllWallets'])->name('zero-all-wallets');
        Route::post('{technician}', [\Modules\CRM\Http\Controllers\TechManagementController::class, 'update'])->name('update')->whereNumber('technician');
        Route::delete('{technician}', [\Modules\CRM\Http\Controllers\TechManagementController::class, 'destroy'])->name('destroy')->whereNumber('technician');
        Route::post('{id}/restore', [\Modules\CRM\Http\Controllers\TechManagementController::class, 'restore'])->name('restore')->whereNumber('id');
    });

    // ─── لاگ سینک (دیباگ پلاگین/سرویس) ─────────────────────────────
    Route::middleware('can:manage-crm-sync')->prefix('sync-logs')->name('sync-logs.')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\SyncLogController::class, 'index'])->name('index');
        Route::get('{id}', [\Modules\CRM\Http\Controllers\SyncLogController::class, 'show'])->name('show')->whereNumber('id');
        Route::delete('/', [\Modules\CRM\Http\Controllers\SyncLogController::class, 'destroy'])->name('destroy');
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

    // Authenticated — با training gate middleware + freeze read-only mode
    Route::middleware(['auth:tech',
        \Modules\CRM\Http\Middleware\TechPanelReadOnly::class,
        \Modules\CRM\Http\Middleware\RequireTrainingCompleted::class,
    ])->group(function () {
        Route::post('logout', [TechAuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [TechPanelDashboardController::class, 'index'])->name('dashboard');
        Route::get('calendar', [TechPanelDashboardController::class, 'calendar'])->name('calendar');
        Route::get('orders', [TechPanelDashboardController::class, 'orders'])->name('orders');
        Route::get('orders/{order}', [TechPanelDashboardController::class, 'showOrder'])->name('orders.show');
        Route::post('orders/{order}/status', [TechPanelDashboardController::class, 'updateOrderStatus'])->name('orders.update-status');
        Route::post('orders/{order}/notes', [TechPanelDashboardController::class, 'addOrderNote'])->name('orders.add-note');
        Route::post('orders/{order}/schedule-visit', [TechPanelDashboardController::class, 'scheduleVisit'])->name('orders.schedule-visit');
        Route::post('orders/{order}/deliver-sms', [TechPanelDashboardController::class, 'sendDeliverSms'])->name('orders.deliver-sms');
        Route::get('wallet', [TechPanelDashboardController::class, 'wallet'])->name('wallet');
        // شارژ کیف‌پول از طریق درگاه — هم‌ارز Tech_Payment پنل WP
        Route::get('wallet/recharge', [TechPanelDashboardController::class, 'walletRecharge'])->name('wallet.recharge');
        Route::post('wallet/recharge', [TechPanelDashboardController::class, 'walletRechargeInitiate'])->name('wallet.recharge.initiate');
        Route::get('invoices', [TechPanelDashboardController::class, 'invoices'])->name('invoices');
        Route::get('profile', [TechPanelDashboardController::class, 'profile'])->name('profile');
        Route::post('profile', [TechPanelDashboardController::class, 'updateProfile'])->name('profile.update');
        Route::post('profile/password', [TechPanelDashboardController::class, 'updatePassword'])->name('profile.password');
        Route::post('profile/avatar', [TechPanelDashboardController::class, 'uploadAvatar'])->name('profile.avatar');

        // ── آموزش تکنسین (UX دو‌مرحله‌ای: دسته → ویدیوها) ────────
        Route::get('training', [TechPanelDashboardController::class, 'training'])->name('training');
        Route::get('training/uncategorized', [TechPanelDashboardController::class, 'trainingUncategorized'])->name('training.uncategorized');
        Route::get('training/category/{category}', [TechPanelDashboardController::class, 'trainingCategory'])
            ->name('training.category')
            ->whereNumber('category');
        Route::get('training/{video}', [TechPanelDashboardController::class, 'trainingShow'])
            ->name('training.show')
            ->whereNumber('video');
        Route::post('training/{video}/watched', [TechPanelDashboardController::class, 'markVideoWatched'])
            ->name('training.video-watched')
            ->whereNumber('video');

        // ── تیکت‌های پشتیبانی ───────────────────────────────────
        Route::get('tickets', [\Modules\CRM\Http\Controllers\Tech\TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [\Modules\CRM\Http\Controllers\Tech\TicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [\Modules\CRM\Http\Controllers\Tech\TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [\Modules\CRM\Http\Controllers\Tech\TicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [\Modules\CRM\Http\Controllers\Tech\TicketController::class, 'reply'])->name('tickets.reply');
    });

    // خروج از حالت impersonate — بدون نیاز به guard auth، فقط بر اساس
    // session.tech_impersonator_user_id کار می‌کند.
    Route::post('impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
});
