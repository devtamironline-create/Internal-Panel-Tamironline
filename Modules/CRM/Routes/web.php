<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\BrandController;
use Modules\CRM\Http\Controllers\CityController;
use Modules\CRM\Http\Controllers\CrmController;
use Modules\CRM\Http\Controllers\CustomerController;
use Modules\CRM\Http\Controllers\DeviceController;
use Modules\CRM\Http\Controllers\HappyCallQuestionController;
use Modules\CRM\Http\Controllers\HappyCallResponseController;
use Modules\CRM\Http\Controllers\InvoiceController;
use Modules\CRM\Http\Controllers\OrderController;
use Modules\CRM\Http\Controllers\OrderItemController;
use Modules\CRM\Http\Controllers\PaymentController;
use Modules\CRM\Http\Controllers\ProvinceController;
use Modules\CRM\Http\Controllers\WalletController;
use Modules\CRM\Http\Controllers\SmsTemplateController;
use Modules\CRM\Http\Controllers\TechDashboardController;
use Modules\CRM\Http\Controllers\TechnicianController;

// ─── مسیرهای عمومی پرداخت (بدون نیاز به لاگین) ─────────────────────
Route::middleware('web')->group(function () {
    Route::get('/crm/pay/{invoiceCode}', [PaymentController::class, 'pay'])->name('crm.payment.pay');
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
    });

    // ─── سفارش‌های تعمیر ─────────────────────────────────────────
    // داشبورد تکنسین: سفارش‌های خودم
    Route::get('my-orders', [OrderController::class, 'myOrders'])->name('orders.my');

    Route::middleware('can:view-crm-orders')->group(function () {
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
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
    });

    // ─── فاکتورها ──────────────────────────────────────────────
    Route::middleware('can:view-crm-invoices')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
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

    // ─── HappyCall ────────────────────────────────────────────────
    Route::middleware('can:view-crm-happycall')->group(function () {
        Route::get('happycall/responses', [HappyCallResponseController::class, 'index'])->name('happycall.responses.index');
        Route::get('happycall/responses/{response}', [HappyCallResponseController::class, 'show'])->name('happycall.responses.show');
    });
    Route::middleware('can:manage-crm-happycall')->group(function () {
        // قالب سوالات
        Route::get('happycall/questions', [HappyCallQuestionController::class, 'index'])->name('happycall.questions.index');
        Route::post('happycall/questions', [HappyCallQuestionController::class, 'store'])->name('happycall.questions.store');
        Route::put('happycall/questions/{question}', [HappyCallQuestionController::class, 'update'])->name('happycall.questions.update');
        Route::delete('happycall/questions/{question}', [HappyCallQuestionController::class, 'destroy'])->name('happycall.questions.destroy');

        // ثبت پاسخ از سفارش
        Route::get('orders/{order}/happycall/new', [HappyCallResponseController::class, 'create'])->name('happycall.responses.create');
        Route::post('orders/{order}/happycall', [HappyCallResponseController::class, 'store'])->name('happycall.responses.store');
        Route::delete('happycall/responses/{response}', [HappyCallResponseController::class, 'destroy'])->name('happycall.responses.destroy');
    });
});
