<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\BrandController;
use Modules\CRM\Http\Controllers\CityController;
use Modules\CRM\Http\Controllers\ComboManagerController;
use Modules\CRM\Http\Controllers\CrmController;
use Modules\CRM\Http\Controllers\CustomerController;
use Modules\CRM\Http\Controllers\DeviceBrandPageController;
use Modules\CRM\Http\Controllers\DeviceCategoryController;
use Modules\CRM\Http\Controllers\DeviceController;
use Modules\CRM\Http\Controllers\ImpersonateController;
use Modules\CRM\Http\Controllers\InvoiceController;
use Modules\CRM\Http\Controllers\LegacyImportController;
use Modules\CRM\Http\Controllers\OrderController;
use Modules\CRM\Http\Controllers\OrderItemController;
use Modules\CRM\Http\Controllers\PaymentController;
use Modules\CRM\Http\Controllers\ProvinceController;
use Modules\CRM\Http\Controllers\SmsTemplateController;
use Modules\CRM\Http\Controllers\SyncSettingsController;
use Modules\CRM\Http\Controllers\Tech\AuthController as TechAuthController;
use Modules\CRM\Http\Controllers\Tech\DashboardController as TechPanelDashboardController;
use Modules\CRM\Http\Controllers\TechDashboardController;
use Modules\CRM\Http\Controllers\TechnicianController;
use Modules\CRM\Http\Controllers\TechPanelSettingsController;
use Modules\CRM\Http\Controllers\TrainingAdminController;
use Modules\CRM\Http\Controllers\TrainingFileController;
use Modules\CRM\Http\Controllers\WalletController;

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
// no.cache روی همه: صفحهٔ کش‌شدهٔ پرداخت یعنی وضعیت/فرمِ کهنه — ریشهٔ
// باگِ «دکمهٔ پرداخت به همان صفحه برمی‌گردد».
Route::middleware(['web', 'no.cache'])->group(function () {
    Route::get('/crm/pay/{invoiceCode}', [PaymentController::class, 'pay'])->name('crm.payment.pay');
    Route::post('/crm/pay/{invoiceCode}', [PaymentController::class, 'initiate'])->name('crm.payment.initiate');
    // مستقیم به درگاه — یک‌کلیکه برای اپ‌ها: بدونِ صفحهٔ پیش‌نمایش و کلیکِ
    // دوم. GET است تا از اپ لینک‌شدنی باشد؛ throttle جلوی ساختِ انبوهِ
    // paymentِ pending را می‌گیرد.
    Route::get('/crm/pay/{invoiceCode}/go', [PaymentController::class, 'direct'])
        ->middleware('throttle:10,1')->name('crm.payment.direct');
    // وضعیتِ پرداخت برای اپِ مشتری — poll بعد از برگشت از درگاه. throttle
    // تا با توکنِ تصادفی هم نشود endpoint را بمباران کرد.
    Route::get('/crm/pay/{invoiceCode}/status', [PaymentController::class, 'status'])
        ->middleware('throttle:30,1')->name('crm.payment.status');
    Route::match(['get', 'post'], '/crm/payment/callback', [PaymentController::class, 'callback'])->name('crm.payment.callback');

    // ─── لینک عمومی صورتحساب — برای ارسال به مشتری از طریق پیامک ────
    // نسخهٔ PDF (باید قبل از روتِ HTML تعریف شود تا «.pdf» را خودش بگیرد).
    Route::get('/crm/receipt/{invoiceCode}.pdf', [\Modules\CRM\Http\Controllers\InvoiceController::class, 'publicReceiptPdf'])
        ->where('invoiceCode', '[A-Za-z0-9\-]+')->name('crm.invoice.public.pdf');
    // صفحهٔ دانلودِ سمت‌مرورگر (html2canvas + jsPDF) — مخصوص اپ مشتری.
    Route::get('/crm/receipt/{invoiceCode}/download', [\Modules\CRM\Http\Controllers\InvoiceController::class, 'publicReceiptDownload'])
        ->where('invoiceCode', '[A-Za-z0-9\-]+')->name('crm.invoice.public.download');
    Route::get('/crm/receipt/{invoiceCode}', [\Modules\CRM\Http\Controllers\InvoiceController::class, 'publicReceipt'])
        ->where('invoiceCode', '[A-Za-z0-9\-]+')->name('crm.invoice.public');

    // رسیدِ عمومیِ پیش‌فاکتور — با public_token (بدونِ لاگین، غیرقابل‌حدس).
    Route::get('/crm/proforma/{token}', [\Modules\CRM\Http\Controllers\ProformaController::class, 'publicReceipt'])
        ->where('token', '[A-Za-z0-9]+')->name('crm.proforma.public');
    // خروجیِ PDFِ تمیز (بدونِ مهر/امضا/هولوگرام، با واترمارکِ «غیرقابل استناد»).
    Route::get('/crm/proforma/{token}/pdf', [\Modules\CRM\Http\Controllers\ProformaController::class, 'pdf'])
        ->where('token', '[A-Za-z0-9]+')->name('crm.proforma.pdf');

    // نمای عمومیِ رسیدِ انتقال با token (تکنسین‌پنل + مبنایِ اپِ مشتری) — بدونِ لاگین.
    Route::get('/crm/transfer-receipt/{token}', [\Modules\CRM\Http\Controllers\TransferReceiptController::class, 'public'])
        ->where('token', '[A-Za-z0-9]+')->name('crm.transfer-receipt.public');

    // سرو لوگو/مهر فاکتور — public چون داخل لینک عمومی فاکتور هم لازم
    // می‌شود. دارایی‌های حساس نیستند (فقط برند شرکت‌اند).
    Route::get('/crm/invoice-asset/{type}', [\Modules\CRM\Http\Controllers\InvoiceController::class, 'serveAsset'])
        ->where('type', 'logo|stamp')->name('crm.invoice.asset');

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
            // رسیدِ انتقال — ثبت توسطِ تکنسین (فقط وضعیتِ انتقال/تعمیر، در کنترلر گارد می‌شود)
            Route::post('orders/{order}/transfer-receipt', [TechDashboardController::class, 'storeTransferReceipt'])->name('orders.transfer-receipt');
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
        Route::put('brands/{brand}/toggle/{flag}', [BrandController::class, 'toggle'])->whereIn('flag', ['is_active', 'is_featured'])->name('brands.toggle');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
    });
    // فعال/غیرفعال‌سازی گروهیِ برندها — فقط مدیر کل.
    Route::middleware('can:manage-permissions')->group(function () {
        Route::post('brands/bulk-toggle', [BrandController::class, 'bulkToggle'])->name('brands.bulk-toggle');
    });

    // ─── دسته‌بندی والد دستگاه‌ها ───────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('device-categories', [DeviceCategoryController::class, 'index'])->name('device-categories.index');
    });
    Route::middleware('can:manage-crm-devices')->group(function () {
        Route::get('device-categories/create', [DeviceCategoryController::class, 'create'])->name('device-categories.create');
        Route::post('device-categories', [DeviceCategoryController::class, 'store'])->name('device-categories.store');
        Route::get('device-categories/{devicecategory}/edit', [DeviceCategoryController::class, 'edit'])->name('device-categories.edit');
        Route::put('device-categories/{devicecategory}', [DeviceCategoryController::class, 'update'])->name('device-categories.update');
        Route::put('device-categories/{devicecategory}/toggle', [DeviceCategoryController::class, 'toggle'])->name('device-categories.toggle');
        Route::delete('device-categories/{devicecategory}', [DeviceCategoryController::class, 'destroy'])->name('device-categories.destroy');
    });

    // ─── تاکسونومی ── دستگاه‌ها ─────────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('devices', [DeviceController::class, 'index'])->name('devices.index');
    });
    Route::middleware('can:manage-crm-devices')->group(function () {
        Route::get('devices/create', [DeviceController::class, 'create'])->name('devices.create');
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::get('devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
        // الگوی عناوینِ «مناطق تحت پوشش» این خدمت (پیشوند/برند/حرف اضافه).
        Route::get('devices/{device}/coverage-titles', [DeviceController::class, 'coverageTitles'])->name('devices.coverage-titles');
        Route::post('devices/{device}/coverage-titles', [DeviceController::class, 'saveCoverageTitles'])->name('devices.coverage-titles.save');
        Route::put('devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::put('devices/{device}/toggle/{flag}', [DeviceController::class, 'toggle'])->whereIn('flag', ['is_active', 'is_active_app', 'is_featured'])->name('devices.toggle');
        Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');

        // تعرفهٔ خدمات (قیمت‌ها) — هم صفحهٔ اختصاصی، هم لینک از ویرایشِ دستگاه.
        Route::get('service-prices', [\Modules\CRM\Http\Controllers\ServicePriceController::class, 'index'])->name('service-prices.index');
        Route::put('service-prices/disclaimer', [\Modules\CRM\Http\Controllers\ServicePriceController::class, 'updateDisclaimer'])->name('service-prices.disclaimer');
        Route::post('devices/{device}/service-prices', [\Modules\CRM\Http\Controllers\ServicePriceController::class, 'store'])->name('service-prices.store');
        // بازکردنِ مستقیمِ آدرسِ POST بالا (مثلاً از history مرورگر بعد از یک
        // submit ناموفق) نباید 405 بدهد — به صفحهٔ تعرفه‌های همان دستگاه برو.
        Route::get('devices/{device}/service-prices', fn (\Modules\CRM\Models\Device $device) => redirect()->route('crm.service-prices.index', ['device' => $device->id]))->name('service-prices.store.get');
        Route::put('service-prices/{price}', [\Modules\CRM\Http\Controllers\ServicePriceController::class, 'update'])->whereNumber('price')->name('service-prices.update');
        Route::delete('service-prices/{price}', [\Modules\CRM\Http\Controllers\ServicePriceController::class, 'destroy'])->whereNumber('price')->name('service-prices.destroy');
    });
    // فعال/غیرفعال‌سازی گروهیِ دستگاه‌ها (سایت/اپ) — فقط مدیر کل.
    Route::middleware('can:manage-permissions')->group(function () {
        Route::post('devices/bulk-toggle', [DeviceController::class, 'bulkToggle'])->name('devices.bulk-toggle');
    });

    // ─── صفحه‌های ترکیبی (device, brand) ─── /devices/{slug}/{brand}
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('device-brand-pages', [DeviceBrandPageController::class, 'index'])->name('device-brand-pages.index');
    });
    Route::middleware('can:manage-crm-devices')->group(function () {
        Route::get('device-brand-pages/create', [DeviceBrandPageController::class, 'create'])->name('device-brand-pages.create');
        Route::post('device-brand-pages', [DeviceBrandPageController::class, 'store'])->name('device-brand-pages.store');
        Route::get('device-brand-pages/{devicebrandpage}/edit', [DeviceBrandPageController::class, 'edit'])->name('device-brand-pages.edit');
        Route::put('device-brand-pages/{devicebrandpage}', [DeviceBrandPageController::class, 'update'])->name('device-brand-pages.update');
        Route::put('device-brand-pages/{devicebrandpage}/toggle', [DeviceBrandPageController::class, 'toggle'])->name('device-brand-pages.toggle');
        Route::delete('device-brand-pages/{devicebrandpage}', [DeviceBrandPageController::class, 'destroy'])->name('device-brand-pages.destroy');
    });

    // ─── مدیریت دستگاه‌محورِ صفحات ترکیبی (device × brand) — فقط مدیر کل ──
    Route::middleware('can:manage-permissions')->group(function () {
        Route::get('combo-manager', [ComboManagerController::class, 'index'])->name('combo-manager.index');
        Route::put('combo-manager/{device}/{brand}/toggle', [ComboManagerController::class, 'toggle'])->name('combo-manager.toggle');
        Route::put('combo-manager/{device}/bulk-toggle', [ComboManagerController::class, 'bulkToggle'])->name('combo-manager.bulk-toggle');
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
        Route::put('provinces/{province}/toggle-active', [ProvinceController::class, 'toggleActive'])->name('provinces.toggle-active');
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
        Route::put('cities/{city}/toggle-active', [CityController::class, 'toggleActive'])->name('cities.toggle-active');
        Route::delete('cities/{city}', [CityController::class, 'destroy'])->name('cities.destroy');
        // تبدیل یک شهر به منطقهٔ ذیل شهر دیگر — برای رفع داده‌های قدیمی
        // که «منطقه N» به‌اشتباه به‌عنوان شهر ثبت شده بودند.
        Route::get('cities/{city}/convert-to-region', [\Modules\CRM\Http\Controllers\CityConverterController::class, 'form'])->name('cities.convert.form');
        Route::post('cities/{city}/convert-to-region', [\Modules\CRM\Http\Controllers\CityConverterController::class, 'store'])->name('cities.convert.store');

        // ─── صفحاتِ سئوِ شهری (SEO-024) ─────────────────────────────
        Route::get('city-pages', [\Modules\CRM\Http\Controllers\CityPageController::class, 'overview'])->name('city-pages.overview');
        Route::get('cities/{city}/pages', [\Modules\CRM\Http\Controllers\CityPageController::class, 'index'])->name('cities.pages.index');
        Route::post('cities/{city}/pages/sync', [\Modules\CRM\Http\Controllers\CityPageController::class, 'sync'])->name('cities.pages.sync');
        Route::post('cities/{city}/pages/publish-all', [\Modules\CRM\Http\Controllers\CityPageController::class, 'publishAll'])->name('cities.pages.publish-all');
        Route::get('city-pages/{cityPage}/edit', [\Modules\CRM\Http\Controllers\CityPageController::class, 'edit'])->name('city-pages.edit');
        Route::put('city-pages/{cityPage}', [\Modules\CRM\Http\Controllers\CityPageController::class, 'update'])->name('city-pages.update');
        Route::put('city-pages/{cityPage}/toggle-publish', [\Modules\CRM\Http\Controllers\CityPageController::class, 'togglePublish'])->name('city-pages.toggle-publish');
        Route::get('city-pages/{cityPage}/preview', [\Modules\CRM\Http\Controllers\CityPageController::class, 'preview'])->name('city-pages.preview');
        Route::delete('city-pages/{cityPage}', [\Modules\CRM\Http\Controllers\CityPageController::class, 'destroy'])->name('city-pages.destroy');
    });

    // ─── تاکسونومی ── انواع خدمات ──────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('service-types', [\Modules\CRM\Http\Controllers\ServiceTypeController::class, 'index'])->name('service-types.index');
    });
    // مدیریتِ نوع خدمت (تعمیر/سرویس/نصب) برای دستگاه‌ها و تکنسین‌ها
    Route::middleware('can:manage-crm-taxonomies')->group(function () {
        Route::get('service-matrix', [\Modules\CRM\Http\Controllers\ServiceMatrixController::class, 'index'])->name('service-matrix.index');
        Route::put('service-matrix/devices', [\Modules\CRM\Http\Controllers\ServiceMatrixController::class, 'updateDevices'])->name('service-matrix.devices');
        Route::put('service-matrix/technicians', [\Modules\CRM\Http\Controllers\ServiceMatrixController::class, 'updateTechnicians'])->name('service-matrix.technicians');
    });
    Route::middleware('can:manage-crm-taxonomies')->group(function () {
        Route::get('service-types/create', [\Modules\CRM\Http\Controllers\ServiceTypeController::class, 'create'])->name('service-types.create');
        Route::post('service-types', [\Modules\CRM\Http\Controllers\ServiceTypeController::class, 'store'])->name('service-types.store');
        Route::get('service-types/{serviceType}/edit', [\Modules\CRM\Http\Controllers\ServiceTypeController::class, 'edit'])->name('service-types.edit');
        Route::put('service-types/{serviceType}', [\Modules\CRM\Http\Controllers\ServiceTypeController::class, 'update'])->name('service-types.update');
        Route::put('service-types/{serviceType}/toggle-active', [\Modules\CRM\Http\Controllers\ServiceTypeController::class, 'toggleActive'])->name('service-types.toggle-active');
        Route::delete('service-types/{serviceType}', [\Modules\CRM\Http\Controllers\ServiceTypeController::class, 'destroy'])->name('service-types.destroy');
    });

    // ─── تاکسونومی ── ایرادات ──────────────────────────────────────
    Route::middleware('can:view-crm-taxonomies')->group(function () {
        Route::get('objections', [\Modules\CRM\Http\Controllers\ObjectionController::class, 'index'])->name('objections.index');
    });
    Route::middleware('can:manage-crm-taxonomies')->group(function () {
        Route::get('objections/create', [\Modules\CRM\Http\Controllers\ObjectionController::class, 'create'])->name('objections.create');
        Route::post('objections', [\Modules\CRM\Http\Controllers\ObjectionController::class, 'store'])->name('objections.store');
        // ویرایشِ دستگاه‌محور (انتخاب دستگاه → تیک‌زدنِ ایرادهای آن)
        Route::get('objections/device/{device}', [\Modules\CRM\Http\Controllers\ObjectionController::class, 'manageDevice'])->name('objections.device');
        Route::put('objections/device/{device}', [\Modules\CRM\Http\Controllers\ObjectionController::class, 'syncDevice'])->name('objections.device.sync');
        Route::get('objections/{objection}/edit', [\Modules\CRM\Http\Controllers\ObjectionController::class, 'edit'])->name('objections.edit');
        Route::put('objections/{objection}', [\Modules\CRM\Http\Controllers\ObjectionController::class, 'update'])->name('objections.update');
        Route::put('objections/{objection}/toggle-active', [\Modules\CRM\Http\Controllers\ObjectionController::class, 'toggleActive'])->name('objections.toggle-active');
        Route::delete('objections/{objection}', [\Modules\CRM\Http\Controllers\ObjectionController::class, 'destroy'])->name('objections.destroy');
    });

    // ─── مشتری‌ها ──────────────────────────────────────────────────
    Route::middleware('can:view-crm-customers')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/export/{format}', [CustomerController::class, 'export'])
            ->where('format', 'csv|xlsx')->name('customers.export');
        // withTrashed: ادمین باید حسابِ حذف‌شده (delete-account از اپ) را هم ببیند.
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->withTrashed()->name('customers.show');
        // Endpoint Ajax برای لود شهرهای هر استان (فرم مشتری/سفارش در فازهای بعد)
        Route::get('provinces/{province}/cities', [CustomerController::class, 'citiesOfProvince'])
            ->name('provinces.cities');
        // Endpoint Ajax برای لود مناطق هر شهر — اگر شهر منطقه ندارد آرایهٔ
        // خالی برمی‌گردد و کلاینت dropdown را مخفی نگه می‌دارد.
        Route::get('cities/{city}/regions', [CustomerController::class, 'regionsOfCity'])
            ->name('cities.regions');
    });
    Route::middleware('can:create-crm-customer')->group(function () {
        Route::get('customers/create/new', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
    });
    Route::middleware('can:edit-crm-customer')->group(function () {
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('customers/{customer}/bale-unlink', [CustomerController::class, 'baleUnlink'])->name('customers.bale-unlink');
    });
    Route::middleware('can:delete-crm-customer')->group(function () {
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        // بازگردانیِ حسابِ حذف‌شده (soft delete — چه از اپ چه از پنل)
        Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])
            ->withTrashed()->name('customers.restore');
    });

    // ─── تکنسین‌های فعال ──────────────────────────────────────────
    Route::middleware('can:view-crm-technicians')->group(function () {
        // نقشهٔ پوشش — قبل از {technician} تا مسیر ثابت گیرِ wildcard نشود
        Route::get('technicians/coverage-map', [\Modules\CRM\Http\Controllers\CoverageMapController::class, 'index'])
            ->name('technicians.coverage-map');
        Route::get('technicians/coverage-manage', [\Modules\CRM\Http\Controllers\CoverageMapController::class, 'manage'])
            ->name('technicians.coverage-manage');
        Route::get('technicians/service-coverage', [\Modules\CRM\Http\Controllers\CoverageMapController::class, 'services'])
            ->name('technicians.service-coverage');
        Route::post('technicians/service-coverage/toggle', [\Modules\CRM\Http\Controllers\CoverageMapController::class, 'toggleServiceVisibility'])
            ->middleware('can:manage-crm-devices')
            ->name('technicians.service-coverage.toggle');
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

        // تغییرِ زمان‌دارِ درصدِ کمیسیون — «از تاریخ X درصد Y» بدونِ دست‌زدن به تاریخِ مالیِ گذشته
        Route::post('technicians/{technician}/percent-changes', [TechnicianController::class, 'storePercentChange'])->name('technicians.percent-changes.store');
        Route::delete('technicians/{technician}/percent-changes/{percentChange}', [TechnicianController::class, 'destroyPercentChange'])->name('technicians.percent-changes.destroy')->whereNumber('percentChange');
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
        // ─── لیدها — بخش مستقل از سفارشات با فیلتر/خروجی جدا ─────
        Route::get('leads', [\Modules\CRM\Http\Controllers\LeadController::class, 'index'])->name('leads.index');
        Route::get('leads/dashboard', [\Modules\CRM\Http\Controllers\LeadDashboardController::class, 'index'])->name('leads.dashboard');
        Route::get('leads/export/{format}', [\Modules\CRM\Http\Controllers\LeadController::class, 'export'])
            ->where('format', 'csv|xlsx')->name('leads.export');
        // مسیر static باید قبل از orders/{order} باشد تا پارامتر اشتباه نشود
        Route::get('orders/missing-invoices', [OrderController::class, 'missingInvoices'])->name('orders.missing-invoices');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show')->whereNumber('order');

        // یادداشت‌های اپراتور — هر کاربری که می‌تواند سفارش را ببیند،
        // می‌تواند یادداشت اضافه/حذف کند (حذف فقط یادداشت خودش).
        Route::post('orders/{order}/notes', [OrderController::class, 'storeNote'])->name('orders.notes.store');
        Route::delete('orders/{order}/notes/{note}', [OrderController::class, 'destroyNote'])->name('orders.notes.destroy')->whereNumber('note');

        // نمای چاپیِ رسیدِ انتقال
        Route::get('transfer-receipts/{transferReceipt}/print', [\Modules\CRM\Http\Controllers\TransferReceiptController::class, 'print'])->name('transfer-receipts.print')->whereNumber('transferReceipt');
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

        // رسیدِ انتقال — ثبت توسطِ ادمین روی یک سفارش
        Route::post('orders/{order}/transfer-receipt', [\Modules\CRM\Http\Controllers\TransferReceiptController::class, 'store'])->name('orders.transfer-receipt.store');
    });

    // مدیریتِ رسیدِ انتقال — دسترسیِ مستقل (ادمین‌کل به هرکس بخواهد می‌دهد):
    // دیدن/ویرایش/حذف/ارسالِ مجددِ پیامک.
    Route::middleware('can:manage-transfer-receipts')->group(function () {
        Route::put('orders/{order}/transfer-receipts/{transferReceipt}', [\Modules\CRM\Http\Controllers\TransferReceiptController::class, 'update'])
            ->name('orders.transfer-receipt.update')->whereNumber('order')->whereNumber('transferReceipt');
        Route::delete('orders/{order}/transfer-receipts/{transferReceipt}', [\Modules\CRM\Http\Controllers\TransferReceiptController::class, 'destroy'])
            ->name('orders.transfer-receipt.destroy')->whereNumber('order')->whereNumber('transferReceipt');
        Route::post('orders/{order}/transfer-receipts/{transferReceipt}/resend', [\Modules\CRM\Http\Controllers\TransferReceiptController::class, 'resend'])
            ->name('orders.transfer-receipt.resend')->whereNumber('order')->whereNumber('transferReceipt');
    });

    // موارد امنیتی — دسترسیِ مستقل (پیش‌فرض فقط ادمین‌کل؛ قابلِ واگذاری به نقش‌های دیگر)
    Route::middleware('can:manage-order-security')->group(function () {
        Route::post('orders/{order}/lock', [OrderController::class, 'toggleLock'])->name('orders.lock');
        Route::post('orders/{order}/force-review', [OrderController::class, 'toggleForceReview'])->name('orders.force-review');
        Route::post('orders/{order}/fraud', [OrderController::class, 'toggleFraud'])->name('orders.fraud');
        Route::post('customers/{customer}/block', [CustomerController::class, 'toggleBlock'])->name('customers.block');
    });
    Route::middleware('can:delete-crm-order')->group(function () {
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    });

    // تبدیل لید به سفارش — دسترسی برای همه‌ی کاربران احراز شده.
    // (نیازی به permission مجزا نیست؛ هرکس صفحهٔ لید را ببیند می‌تواند
    // تبدیل کند.)
    Route::post('orders/{order}/convert-from-lead', [OrderController::class, 'convertFromLead'])
        ->name('orders.convert-from-lead');

    // گزارشِ «چرا این سفارش به این تکنسین رسید»
    Route::middleware('can:view-tech-suggestions')->group(function () {
        Route::get('assignment-logs', [\Modules\CRM\Http\Controllers\AssignmentLogController::class, 'index'])
            ->name('assignment-logs.index');
    });

    // کلیدِ «خودکار پخش کن / فقط پیشنهاد بده»
    Route::middleware('can:manage-crm-settings')->group(function () {
        Route::get('assignment-settings', [\Modules\CRM\Http\Controllers\AssignmentSettingsController::class, 'index'])
            ->name('assignment-settings.index');
        Route::post('assignment-settings', [\Modules\CRM\Http\Controllers\AssignmentSettingsController::class, 'update'])
            ->name('assignment-settings.update');
    });

    Route::middleware('can:assign-crm-technician')->group(function () {
        Route::post('orders/{order}/assign', [OrderController::class, 'assign'])->name('orders.assign');
        Route::post('orders/{order}/assign-group', [OrderController::class, 'assignGroup'])->name('orders.assign-group');
        Route::post('orders/{order}/unassign', [OrderController::class, 'unassign'])->name('orders.unassign');
        Route::post('orders/{order}/source-of-truth', [OrderController::class, 'updateSourceOfTruth'])->name('orders.source-of-truth');
    });

    Route::middleware('can:change-crm-order-status')->group(function () {
        Route::post('orders/{order}/status', [OrderController::class, 'changeStatus'])->name('orders.status.change');
        Route::post('orders/{order}/return', [OrderController::class, 'returnOrder'])->name('orders.return');
        // صفر کردنِ شمارندهٔ تغییرِ زمانِ مراجعه (اجازهٔ تغییرِ بیشتر به تکنسین)
        Route::post('orders/{order}/reset-visit-reschedule', [OrderController::class, 'resetVisitReschedule'])->name('orders.reset-visit-reschedule');
        // کارشناسیِ برگشتیِ گارانتی (روی سفارش‌های وضعیتِ «برگشتی گارانتی»)
        Route::post('orders/{order}/return/approve', [OrderController::class, 'approveReturn'])->name('orders.return.approve');
        Route::post('orders/{order}/return/reject', [OrderController::class, 'rejectReturn'])->name('orders.return.reject');
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

    // ─── پوش نوتیفیکیشن (نجوا) ─────────────────────────────────
    Route::middleware('can:manage-crm-push')->prefix('push')->name('push.')->group(function () {
        $mgmt = \Modules\CRM\Http\Controllers\PushManagementController::class;
        $campaign = \Modules\CRM\Http\Controllers\PushCampaignController::class;
        $report = \Modules\CRM\Http\Controllers\PushReportController::class;

        // ۵.۱ داشبورد
        Route::get('/', [$mgmt, 'index'])->name('index');

        // ۵.۲ رویدادها
        Route::get('events', [$mgmt, 'events'])->name('events');
        Route::post('events/{event}', [$mgmt, 'updateEvent'])->name('events.update');
        Route::post('events/{event}/toggle', [$mgmt, 'toggleEvent'])->name('events.toggle');

        // ۵.۳ ارسال دستی
        Route::get('campaign', [$campaign, 'create'])->name('campaign.create');
        Route::post('campaign', [$campaign, 'store'])->name('campaign.store');
        Route::post('campaign/cancel', [$campaign, 'cancel'])->name('campaign.cancel');
        Route::post('subscribers/{technician}/test', [$campaign, 'test'])
            ->whereNumber('technician')->name('subscribers.test');

        // ۵.۴ گزارش‌ها
        Route::get('logs', [$report, 'logs'])->name('logs');
        Route::get('logs/export', [$report, 'export'])->name('logs.export');
        Route::post('logs/trace', [$report, 'trace'])->name('logs.trace');

        // ۵.۵ مشترکین
        Route::get('subscribers', [$report, 'subscribers'])->name('subscribers');
        Route::get('subscribers/{technician}/devices', [$report, 'devices'])
            ->whereNumber('technician')->name('subscribers.devices');

        // ۵.۶ تنظیمات
        Route::get('settings', [$mgmt, 'settings'])->name('settings');
        Route::post('settings', [$mgmt, 'updateSettings'])->name('settings.update');
        Route::post('settings/test-connection', [$mgmt, 'testConnection'])->name('settings.test');
    });

    // ─── کیف‌پول تکنسین ────────────────────────────────────────
    Route::middleware('can:view-crm-financial')->group(function () {
        Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
        Route::get('wallet/technician/{technician}', [WalletController::class, 'show'])->name('wallet.show');
    });

    // ─── گزارش‌های CRM (مالی، فعالیت روزانه) — permission مجزا ────
    Route::middleware('can:view-crm-reports')->group(function () {
        Route::get('reports/financial', [\Modules\CRM\Http\Controllers\Reports\FinancialReportController::class, 'index'])
            ->name('reports.financial');
        Route::get('reports/financial/export', [\Modules\CRM\Http\Controllers\Reports\FinancialReportController::class, 'export'])
            ->name('reports.financial.export');
        Route::get('reports/daily-activity', [\Modules\CRM\Http\Controllers\Reports\DailyActivityController::class, 'index'])
            ->name('reports.daily-activity');
    });

    // ─── سفارش‌های یتیم (bulk assign تکنسین) — permission مجزا ───
    Route::middleware('can:manage-crm-orphan-orders')->group(function () {
        Route::get('orphan-orders', [\Modules\CRM\Http\Controllers\OrphanOrdersController::class, 'index'])
            ->name('orphan-orders.index');
        Route::post('orphan-orders/assign', [\Modules\CRM\Http\Controllers\OrphanOrdersController::class, 'assign'])
            ->name('orphan-orders.assign');
        Route::post('orphan-orders/auto-assign', [\Modules\CRM\Http\Controllers\OrphanOrdersController::class, 'autoAssignMatched'])
            ->name('orphan-orders.auto-assign');
        Route::post('orphan-orders/backfill-from-log', [\Modules\CRM\Http\Controllers\OrphanOrdersController::class, 'backfillFromLog'])
            ->name('orphan-orders.backfill-from-log');
        Route::post('orphan-orders/set-wp-id', [\Modules\CRM\Http\Controllers\OrphanOrdersController::class, 'setWpIdForOrder'])
            ->name('orphan-orders.set-wp-id');
        Route::post('orphan-orders/rebackfill-prefer-panel', [\Modules\CRM\Http\Controllers\OrphanOrdersController::class, 'rebackfillPreferPanelMatch'])
            ->name('orphan-orders.rebackfill-prefer-panel');
        Route::post('orphan-orders/backfill-from-wp-postmeta', [\Modules\CRM\Http\Controllers\OrphanOrdersController::class, 'backfillFromWpPostmeta'])
            ->name('orphan-orders.backfill-from-wp-postmeta');
    });
    Route::middleware('can:manage-crm-wallet')->group(function () {
        Route::post('wallet/technician/{technician}/transaction', [WalletController::class, 'storeTransaction'])->name('wallet.transaction.store');

        // حذف تراکنش — permission ویژه (`delete-wallet-transaction`) داخل کنترلر چک می‌شود
        Route::delete('wallet/technician/{technician}/transaction/{transaction}', [WalletController::class, 'destroyTransaction'])
            ->name('wallet.transaction.destroy')
            ->whereNumber('transaction');

        // افزودن فاکتور حسابداری — هم‌ارز add_financial.php در WP
        Route::get('wallet/add', [WalletController::class, 'addFinancial'])->name('wallet.add');
        Route::post('wallet/reward', [WalletController::class, 'storeReward'])->name('wallet.reward.store');
        Route::post('wallet/charge', [WalletController::class, 'storeCharge'])->name('wallet.charge.store');
    });

    // حذف کامل با OTP — خارج از گروه manage-crm-wallet چون permission
    // مستقل خودش (hard-delete-wallet-transaction) را داخل کنترلر چک می‌کند
    Route::post('wallet/hard-delete/request-otp', [WalletController::class, 'requestHardDeleteOtp'])
        ->name('wallet.transaction.hard-delete.otp');
    Route::delete('wallet/technician/{technician}/transaction/{transaction}/hard-delete', [WalletController::class, 'hardDeleteTransaction'])
        ->name('wallet.transaction.hard-delete')
        ->whereNumber('transaction');

    // ─── فاکتورها ──────────────────────────────────────────────
    Route::middleware('can:view-crm-invoices')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/export/{format}', [InvoiceController::class, 'export'])
            ->where('format', 'csv|xlsx')->name('invoices.export');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->whereNumber('invoice');
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print')->whereNumber('invoice');
        Route::post('invoices/{invoice}/send-sms', [InvoiceController::class, 'sendSms'])->name('invoices.send-sms')->whereNumber('invoice');
    });

    // ─── پیش‌فاکتورها ──────────────────────────────────────────
    Route::middleware('can:view-crm-invoices')->group(function () {
        $pf = \Modules\CRM\Http\Controllers\ProformaController::class;
        Route::get('proformas', [$pf, 'index'])->name('proformas.index');
        Route::get('proformas/create', [$pf, 'create'])->name('proformas.create');
        Route::post('proformas', [$pf, 'store'])->name('proformas.store');
        Route::get('proformas/{proforma}', [$pf, 'show'])->name('proformas.show')->whereNumber('proforma');
        Route::post('proformas/{proforma}/send-sms', [$pf, 'sendSms'])->name('proformas.send-sms')->whereNumber('proforma');
    });
    Route::middleware('can:manage-crm-settings')->group(function () {
        Route::get('invoices/settings', [InvoiceController::class, 'settings'])->name('invoices.settings');
        Route::post('invoices/settings', [InvoiceController::class, 'updateSettings'])->name('invoices.settings.update');
        // تنظیماتِ عمومی (فلگ‌های امن)
        Route::get('feature-flags', [\Modules\CRM\Http\Controllers\FeatureFlagsController::class, 'index'])->name('feature-flags.index');
        Route::post('feature-flags', [\Modules\CRM\Http\Controllers\FeatureFlagsController::class, 'update'])->name('feature-flags.update');
        // تنظیماتِ عملیاتیِ سفارش (دلایلِ کنسل/رد و …)
        Route::get('order-settings', [\Modules\CRM\Http\Controllers\OrderOpsSettingsController::class, 'index'])->name('order-settings.index');
        Route::post('order-settings', [\Modules\CRM\Http\Controllers\OrderOpsSettingsController::class, 'update'])->name('order-settings.update');
    });
    Route::middleware('can:manage-crm-financial')->group(function () {
        Route::post('orders/{order}/invoice', [InvoiceController::class, 'generate'])->name('orders.invoice.generate');
        Route::post('orders/{order}/restore-wallet-history', [OrderController::class, 'restoreWalletHistory'])->name('orders.restore-wallet');
        Route::post('orders/{order}/remove-restored-wallet', [OrderController::class, 'removeRestoredHistory'])->name('orders.remove-restored-wallet');
        Route::post('invoices/{invoice}/paid', [InvoiceController::class, 'markPaid'])->name('invoices.paid');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/push-to-wp', [InvoiceController::class, 'pushToWp'])->name('invoices.push-to-wp');
    });

    // ─── اصلاح مبلغ فاکتور — permission ویژه (فقط ادمین ارشد) ───────────
    Route::middleware('can:correct-invoices')->group(function () {
        Route::get('invoices/{invoice}/correct', [InvoiceController::class, 'correctForm'])
            ->name('invoices.correct')->whereNumber('invoice');
        Route::post('invoices/{invoice}/correct', [InvoiceController::class, 'correct'])
            ->name('invoices.correct.store')->whereNumber('invoice');
    });

    // ─── retro-close (بستن از لاگ قدیمی بدون فاکتور) — permission مجزا ───
    Route::middleware('can:manage-crm-legacy-close')->group(function () {
        Route::post('orders/{order}/retro-close', [OrderController::class, 'retroClose'])->name('orders.retro-close');
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
    // ─── مدیریت مناطق (Region/District) — اختیاری زیر هر شهر ─────
    Route::middleware('can:manage-crm-settings')->group(function () {
        Route::get('regions', [\Modules\CRM\Http\Controllers\RegionController::class, 'index'])->name('regions.index');
        Route::post('regions', [\Modules\CRM\Http\Controllers\RegionController::class, 'store'])->name('regions.store');
        Route::put('regions/{region}', [\Modules\CRM\Http\Controllers\RegionController::class, 'update'])->name('regions.update')->whereNumber('region');
        Route::put('regions/{region}/toggle-active', [\Modules\CRM\Http\Controllers\RegionController::class, 'toggleActive'])->name('regions.toggle-active')->whereNumber('region');
        Route::delete('regions/{region}', [\Modules\CRM\Http\Controllers\RegionController::class, 'destroy'])->name('regions.destroy')->whereNumber('region');
    });

    // ─── محدودهٔ سرویس‌دهی اپلیکیشن — هابِ پیمایش استان→شهر→منطقه ──
    Route::middleware('can:manage-crm-settings')->group(function () {
        Route::get('app-service-area', [\Modules\CRM\Http\Controllers\AppServiceAreaController::class, 'index'])->name('app-service-area.index');
    });

    // ─── قالب‌های پیامِ اطلاع‌رسانیِ مشتری (نوتیفِ اپ + بله) ──────────
    Route::middleware('can:manage-crm-settings')->group(function () {
        Route::get('notify-templates', [\Modules\CRM\Http\Controllers\NotifyTemplatesController::class, 'index'])->name('notify-templates.index');
        Route::post('notify-templates', [\Modules\CRM\Http\Controllers\NotifyTemplatesController::class, 'update'])->name('notify-templates.update');
        Route::post('notify-templates/reset', [\Modules\CRM\Http\Controllers\NotifyTemplatesController::class, 'reset'])->name('notify-templates.reset');
    });

    // ─── کمپین‌های پیام‌رسانِ بله ──────────────────────────────────────
    Route::middleware('can:manage-crm-settings')->prefix('bale-campaigns')->name('bale-campaigns.')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\BaleCampaignController::class, 'index'])->name('index');
        Route::get('create', [\Modules\CRM\Http\Controllers\BaleCampaignController::class, 'create'])->name('create');
        Route::get('export-phones', [\Modules\CRM\Http\Controllers\BaleCampaignController::class, 'exportPhones'])->name('export-phones');
        Route::post('/', [\Modules\CRM\Http\Controllers\BaleCampaignController::class, 'store'])->name('store');
        Route::get('{baleCampaign}', [\Modules\CRM\Http\Controllers\BaleCampaignController::class, 'show'])->name('show')->whereNumber('baleCampaign');
        Route::post('{baleCampaign}/process', [\Modules\CRM\Http\Controllers\BaleCampaignController::class, 'process'])->name('process')->whereNumber('baleCampaign');
        Route::post('{baleCampaign}/test', [\Modules\CRM\Http\Controllers\BaleCampaignController::class, 'test'])->name('test')->whereNumber('baleCampaign');
        Route::delete('{baleCampaign}', [\Modules\CRM\Http\Controllers\BaleCampaignController::class, 'destroy'])->name('destroy')->whereNumber('baleCampaign');
    });

    // ─── ایرادات فرم ثبت سفارش پنل (objectionsList در ویزارد اپراتور) ───
    // مسیر/نام «objections-settings» است تا با CRUD ایرادات اپ موبایل
    // (ObjectionController با نام‌های crm.objections.*) تداخل نکند.
    Route::middleware('can:manage-crm-settings')->group(function () {
        Route::get('objections-settings', [\Modules\CRM\Http\Controllers\ObjectionsSettingsController::class, 'index'])->name('objections-settings.index');
        Route::post('objections-settings', [\Modules\CRM\Http\Controllers\ObjectionsSettingsController::class, 'update'])->name('objections-settings.update');
    });

    // ─── دلایل عدم امکان سفارش (مدیریت لیدها) ─────────────────────
    Route::middleware('can:manage-crm-settings')->group(function () {
        Route::get('lead-reasons', [\Modules\CRM\Http\Controllers\LeadReasonController::class, 'index'])->name('lead-reasons.index');
        Route::post('lead-reasons', [\Modules\CRM\Http\Controllers\LeadReasonController::class, 'store'])->name('lead-reasons.store');
        Route::put('lead-reasons/{leadReason}', [\Modules\CRM\Http\Controllers\LeadReasonController::class, 'update'])->name('lead-reasons.update')->whereNumber('leadReason');
        Route::delete('lead-reasons/{leadReason}', [\Modules\CRM\Http\Controllers\LeadReasonController::class, 'destroy'])->name('lead-reasons.destroy')->whereNumber('leadReason');
    });

    Route::middleware('can:manage-crm-settings')->prefix('tickets/categories')->name('tickets.categories.')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\TicketCategoryController::class, 'index'])->name('index');
        Route::post('/', [\Modules\CRM\Http\Controllers\TicketCategoryController::class, 'store'])->name('store');
        Route::put('{category}', [\Modules\CRM\Http\Controllers\TicketCategoryController::class, 'update'])->name('update')->whereNumber('category');
        Route::delete('{category}', [\Modules\CRM\Http\Controllers\TicketCategoryController::class, 'destroy'])->name('destroy')->whereNumber('category');
    });

    // ─── اعلانات تکنسین‌ها (سمت ادمین/اپراتور) ───────────────────
    Route::middleware('can:manage-crm-announcements')->prefix('announcements')->name('announcements.')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\AnnouncementController::class, 'index'])->name('index');
        Route::post('/', [\Modules\CRM\Http\Controllers\AnnouncementController::class, 'store'])->name('store');
        Route::get('{announcement}', [\Modules\CRM\Http\Controllers\AnnouncementController::class, 'show'])->name('show')->whereNumber('announcement');
        Route::post('{announcement}/toggle', [\Modules\CRM\Http\Controllers\AnnouncementController::class, 'toggle'])->name('toggle')->whereNumber('announcement');
        Route::delete('{announcement}', [\Modules\CRM\Http\Controllers\AnnouncementController::class, 'destroy'])->name('destroy')->whereNumber('announcement');
    });

    // ─── حسابداری — فاز ۱: ثبت هزینه‌ها ───────────────────────────
    Route::middleware('can:view-crm-costs')->prefix('costs')->name('costs.')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\Accounting\ExpenseController::class, 'index'])->name('index');
        Route::get('create', [\Modules\CRM\Http\Controllers\Accounting\ExpenseController::class, 'create'])->name('create');
        Route::post('/', [\Modules\CRM\Http\Controllers\Accounting\ExpenseController::class, 'store'])->name('store');
        Route::get('report', [\Modules\CRM\Http\Controllers\Accounting\ExpenseReportController::class, 'index'])->name('report');
        Route::get('analytics', [\Modules\CRM\Http\Controllers\Accounting\FinancialReportController::class, 'index'])->name('analytics');
        Route::get('{expense}/attachment', [\Modules\CRM\Http\Controllers\Accounting\ExpenseController::class, 'attachment'])->name('attachment')->whereNumber('expense');
    });
    // ─── حسابداری — گوگل ادز (دفترِ روزانه) ───────────────────────
    Route::middleware('can:view-crm-costs')->prefix('google-ads')->name('google-ads.')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\Accounting\GoogleAdsController::class, 'index'])->name('index');
    });
    Route::middleware('can:manage-crm-costs')->prefix('google-ads')->name('google-ads.')->group(function () {
        Route::post('/', [\Modules\CRM\Http\Controllers\Accounting\GoogleAdsController::class, 'store'])->name('store');
        Route::put('{googleAd}', [\Modules\CRM\Http\Controllers\Accounting\GoogleAdsController::class, 'update'])->name('update')->whereNumber('googleAd');
        Route::delete('{googleAd}', [\Modules\CRM\Http\Controllers\Accounting\GoogleAdsController::class, 'destroy'])->name('destroy')->whereNumber('googleAd');
    });

    Route::middleware('can:manage-crm-costs')->prefix('costs')->name('costs.')->group(function () {
        Route::get('categories', [\Modules\CRM\Http\Controllers\Accounting\ExpenseCategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [\Modules\CRM\Http\Controllers\Accounting\ExpenseCategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [\Modules\CRM\Http\Controllers\Accounting\ExpenseCategoryController::class, 'update'])->name('categories.update')->whereNumber('category');
        Route::delete('categories/{category}', [\Modules\CRM\Http\Controllers\Accounting\ExpenseCategoryController::class, 'destroy'])->name('categories.destroy')->whereNumber('category');

        Route::get('accounts', [\Modules\CRM\Http\Controllers\Accounting\PaymentAccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts', [\Modules\CRM\Http\Controllers\Accounting\PaymentAccountController::class, 'store'])->name('accounts.store');
        Route::put('accounts/{account}', [\Modules\CRM\Http\Controllers\Accounting\PaymentAccountController::class, 'update'])->name('accounts.update')->whereNumber('account');
        Route::delete('accounts/{account}', [\Modules\CRM\Http\Controllers\Accounting\PaymentAccountController::class, 'destroy'])->name('accounts.destroy')->whereNumber('account');

        Route::get('{expense}/edit', [\Modules\CRM\Http\Controllers\Accounting\ExpenseController::class, 'edit'])->name('edit')->whereNumber('expense');
        Route::put('{expense}', [\Modules\CRM\Http\Controllers\Accounting\ExpenseController::class, 'update'])->name('update')->whereNumber('expense');
        Route::delete('{expense}', [\Modules\CRM\Http\Controllers\Accounting\ExpenseController::class, 'destroy'])->name('destroy')->whereNumber('expense');
    });

    // ─── تیکت‌های پشتیبانی تکنسین (سمت ادمین) ─────────────────────
    // {ticket} با whereNumber محدود شده تا 'categories' را به اشتباه match نکند.
    Route::middleware('can:view-crm-tickets')->group(function () {
        Route::get('tickets', [\Modules\CRM\Http\Controllers\TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [\Modules\CRM\Http\Controllers\TicketController::class, 'show'])->name('tickets.show')->whereNumber('ticket');
    });
    Route::middleware('can:reply-crm-tickets')->group(function () {
        Route::get('tickets/create', [\Modules\CRM\Http\Controllers\TicketController::class, 'create'])->name('tickets.create');
        Route::post('tickets', [\Modules\CRM\Http\Controllers\TicketController::class, 'store'])->name('tickets.store');
        Route::post('tickets/{ticket}/reply', [\Modules\CRM\Http\Controllers\TicketController::class, 'reply'])->name('tickets.reply')->whereNumber('ticket');
        Route::patch('tickets/{ticket}/status', [\Modules\CRM\Http\Controllers\TicketController::class, 'updateStatus'])->name('tickets.status')->whereNumber('ticket');
        // عمل دستی بایگانی / خروج از بایگانی — جدا از updateStatus
        // تا در view یک دکمهٔ تک‌منظوره ساده داشته باشیم.
        Route::post('tickets/{ticket}/archive', [\Modules\CRM\Http\Controllers\TicketController::class, 'archive'])->name('tickets.archive')->whereNumber('ticket');
        Route::post('tickets/{ticket}/unarchive', [\Modules\CRM\Http\Controllers\TicketController::class, 'unarchive'])->name('tickets.unarchive')->whereNumber('ticket');
    });

    // ─── چت اپراتور↔تکنسین (سمت ادمین) ─────────────────────────────
    // no.cache: بدونِ آن، پاسخ‌های poll/unread توسطِ کشِ مرورگر/LiteSpeed ذخیره
    // می‌شدند و پیامِ جدید فقط بعد از پاک‌کردنِ کش دیده می‌شد.
    Route::prefix('tech-chats')->name('tech-chats.')->middleware('no.cache')->group(function () {
        Route::get('/', [\Modules\CRM\Http\Controllers\TechChatController::class, 'index'])->name('index');
        Route::get('/unread-summary', [\Modules\CRM\Http\Controllers\TechChatController::class, 'unreadSummary'])->name('unread');
        Route::get('/search', [\Modules\CRM\Http\Controllers\TechChatController::class, 'search'])->name('search');
        Route::middleware('can:manage-technicians')->group(function () {
            Route::get('/assignments', [\Modules\CRM\Http\Controllers\TechChatController::class, 'assignments'])->name('assignments');
            Route::post('/assignments/bulk', [\Modules\CRM\Http\Controllers\TechChatController::class, 'bulkAssign'])->name('assignments.bulk');
            Route::patch('/{technician}/assign', [\Modules\CRM\Http\Controllers\TechChatController::class, 'updateAssignment'])->name('assign');
        });
        Route::get('/{technician}', [\Modules\CRM\Http\Controllers\TechChatController::class, 'show'])->name('show')->whereNumber('technician');
        Route::post('/{technician}/send', [\Modules\CRM\Http\Controllers\TechChatController::class, 'send'])->name('send')->whereNumber('technician');
        Route::get('/{technician}/poll', [\Modules\CRM\Http\Controllers\TechChatController::class, 'poll'])->name('poll')->whereNumber('technician');
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
        Route::post('toggle-tech-proforma', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'toggleTechProforma'])->name('toggle-tech-proforma');
        Route::post('toggle-transfer-receipt', [\Modules\CRM\Http\Controllers\DataToolsController::class, 'toggleTransferReceipt'])->name('toggle-transfer-receipt');
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
    // ارسال لینک دانلود اپ با پیامک — از صفحهٔ «آپدیت اجباری». عمداً خارج از
    // gate آموزش/فریز: تکنسینِ پشتِ آن صفحه هم باید بتواند لینک را بگیرد.
    Route::middleware(['auth:tech', 'throttle:3,60'])
        ->post('app-update-sms', [TechAuthController::class, 'sendAppUpdateSms'])
        ->name('app-update.sms');

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
        Route::post('orders/{order}/call-result', [TechPanelDashboardController::class, 'callResult'])->name('orders.call-result');
        Route::post('orders/{order}/return-review', [TechPanelDashboardController::class, 'submitReturnReview'])->name('orders.return-review');
        Route::post('orders/{order}/deliver-sms', [TechPanelDashboardController::class, 'sendDeliverSms'])->name('orders.deliver-sms');
        // ارسالِ دستیِ پیامکِ رسیدِ انتقال به مشتری — فقط یک‌بار توسطِ تکنسین.
        Route::post('orders/{order}/transfer-receipt/{transferReceipt}/send-sms', [TechPanelDashboardController::class, 'sendTransferReceiptSms'])
            ->name('orders.transfer-receipt.send-sms')->whereNumber('transferReceipt');
        Route::get('wallet', [TechPanelDashboardController::class, 'wallet'])->name('wallet');
        // شارژ کیف‌پول از طریق درگاه — هم‌ارز Tech_Payment پنل WP
        Route::get('wallet/recharge', [TechPanelDashboardController::class, 'walletRecharge'])->name('wallet.recharge');
        Route::post('wallet/recharge', [TechPanelDashboardController::class, 'walletRechargeInitiate'])->name('wallet.recharge.initiate');
        Route::get('invoices', [TechPanelDashboardController::class, 'invoices'])->name('invoices');

        // ── پیش‌فاکتور (تکنسین برای سفارش‌های خودش) ──────────────
        $techPf = \Modules\CRM\Http\Controllers\Tech\ProformaController::class;
        Route::get('proformas', [$techPf, 'index'])->name('proformas.index');
        Route::get('proformas/create', [$techPf, 'create'])->name('proformas.create');
        Route::post('proformas', [$techPf, 'store'])->name('proformas.store');
        Route::get('proformas/{proforma}', [$techPf, 'show'])->name('proformas.show')->whereNumber('proforma');
        // سمتِ تکنسین پیامک ندارد (سیاستِ شرکت) — فقط ساخت/نمایش/نهایی‌سازی.
        Route::post('proformas/{proforma}/finalize', [$techPf, 'finalize'])->name('proformas.finalize')->whereNumber('proforma');

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

        // چت تکنسین با اپراتور + اعلانات — no.cache: پاسخ‌های poll نباید کش شوند
        Route::middleware('no.cache')->group(function () {
            Route::get('messages', [\Modules\CRM\Http\Controllers\Tech\ChatController::class, 'index'])->name('messages');
            Route::post('messages/send', [\Modules\CRM\Http\Controllers\Tech\ChatController::class, 'send'])->name('messages.send');
            Route::get('messages/poll', [\Modules\CRM\Http\Controllers\Tech\ChatController::class, 'poll'])->name('messages.poll');
            Route::get('messages/unread', [\Modules\CRM\Http\Controllers\Tech\ChatController::class, 'unread'])->name('messages.unread');

            // ── اعلانات (نوشته‌شده توسط اپراتور از پنل ادمین) ────────
            Route::get('announcements', [\Modules\CRM\Http\Controllers\Tech\AnnouncementController::class, 'index'])->name('announcements');
            Route::get('announcements/unacked', [\Modules\CRM\Http\Controllers\Tech\AnnouncementController::class, 'unacked'])->name('announcements.unacked');
            Route::post('announcements/{announcement}/ack', [\Modules\CRM\Http\Controllers\Tech\AnnouncementController::class, 'ack'])->name('announcements.ack')->whereNumber('announcement');
        });
    });

    // خروج از حالت impersonate — بدون نیاز به guard auth، فقط بر اساس
    // session.tech_impersonator_user_id کار می‌کند.
    Route::post('impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');
});
