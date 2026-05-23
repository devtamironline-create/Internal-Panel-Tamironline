<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\Admin\AboutStatController;
use Modules\Site\Http\Controllers\Admin\BannerController;
use Modules\Site\Http\Controllers\Admin\ContactMessageController;
use Modules\Site\Http\Controllers\Admin\FaqController;
use Modules\Site\Http\Controllers\Admin\PageContentController;
use Modules\Site\Http\Controllers\Admin\PageController;
use Modules\Site\Http\Controllers\Admin\ReviewController as AdminReviewController;
use Modules\Site\Http\Controllers\Admin\SettingsController;
use Modules\Site\Http\Controllers\Admin\TaxonomyController;
use Modules\Site\Http\Controllers\SiteAdminController;

// بخش مدیریت سایت (نیاز به لاگین)
Route::middleware(['auth'])->prefix('admin/site')->name('site.admin.')->group(function () {
    Route::get('/', [SiteAdminController::class, 'dashboard'])->name('dashboard');

    // پیام‌های تماس
    Route::prefix('contact-messages')->name('contact-messages.')->group(function () {
        Route::get('/', [ContactMessageController::class, 'index'])->name('index');
        Route::get('/{id}', [ContactMessageController::class, 'show'])->name('show');
        Route::put('/{id}/status', [ContactMessageController::class, 'updateStatus'])->name('update-status');
        Route::delete('/{id}', [ContactMessageController::class, 'destroy'])->name('destroy');
    });

    // نظرات و توصیه‌نامه‌ها (یکپارچه — audio + text)
    Route::prefix('reviews')->name('reviews.')->group(function () {
        Route::get('/', [AdminReviewController::class, 'index'])->name('index');
        Route::get('/create', [AdminReviewController::class, 'create'])->name('create');
        Route::post('/', [AdminReviewController::class, 'store'])->name('store');
        Route::get('/{id}', [AdminReviewController::class, 'show'])->whereAlphaNumeric('id')->name('show');
        Route::get('/{id}/edit', [AdminReviewController::class, 'edit'])->whereAlphaNumeric('id')->name('edit');
        Route::put('/{id}', [AdminReviewController::class, 'update'])->whereAlphaNumeric('id')->name('update');
        Route::put('/{id}/status', [AdminReviewController::class, 'updateStatus'])->whereAlphaNumeric('id')->name('update-status');
        Route::put('/{id}/toggle-publish', [AdminReviewController::class, 'togglePublish'])->whereAlphaNumeric('id')->name('toggle-publish');
        Route::post('/{id}/reply', [AdminReviewController::class, 'reply'])->whereAlphaNumeric('id')->name('reply');
        Route::delete('/{id}', [AdminReviewController::class, 'destroy'])->whereAlphaNumeric('id')->name('destroy');
    });

    // Redirectهای backward-compat برای URLهای قدیمی
    Route::get('/testimonials', fn () => redirect()->route('site.admin.reviews.index', ['type' => 'audio']));
    Route::get('/device-reviews', fn () => redirect()->route('site.admin.reviews.index', ['type' => 'text']));

    // میانبر «محتوای دستگاه» — هر دستگاه فیلدهای DeviceContent در فرم edit خود دارد
    Route::get('/device-content', function () {
        return redirect()->route('crm.devices.index');
    })->name('device-content');

    // صفحات سایت — قدیمی (free-form content، در حال جایگزینی با page-content)
    Route::prefix('pages')->name('pages.')->group(function () {
        Route::get('/', [PageController::class, 'index'])->name('index');
        Route::get('/create', [PageController::class, 'create'])->name('create');
        Route::post('/', [PageController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [PageController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PageController::class, 'update'])->name('update');
        Route::delete('/{id}', [PageController::class, 'destroy'])->name('destroy');
    });

    // محتوای صفحات سایت — section-based
    Route::prefix('page-content')->name('page-content.')->group(function () {
        Route::get('/', [PageContentController::class, 'index'])->name('index');
        Route::get('/{slug}', [PageContentController::class, 'edit'])->whereAlpha('slug')->name('edit');
        Route::put('/{slug}', [PageContentController::class, 'update'])->whereAlpha('slug')->name('update');
    });

    // بنرها و اسلایدر
    Route::prefix('banners')->name('banners.')->group(function () {
        Route::get('/', [BannerController::class, 'index'])->name('index');
        Route::get('/create', [BannerController::class, 'create'])->name('create');
        Route::post('/', [BannerController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [BannerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [BannerController::class, 'update'])->name('update');
        Route::delete('/{id}', [BannerController::class, 'destroy'])->name('destroy');
    });

    // سوالات متداول (مخزن)
    Route::prefix('faqs')->name('faqs.')->group(function () {
        Route::get('/', [FaqController::class, 'index'])->name('index');
        Route::get('/create', [FaqController::class, 'create'])->name('create');
        Route::post('/', [FaqController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [FaqController::class, 'edit'])->name('edit');
        Route::put('/{id}', [FaqController::class, 'update'])->name('update');
        Route::delete('/{id}', [FaqController::class, 'destroy'])->name('destroy');
    });

    // آمار صفحه‌ی About
    Route::prefix('about-stats')->name('about-stats.')->group(function () {
        Route::get('/', [AboutStatController::class, 'index'])->name('index');
        Route::get('/create', [AboutStatController::class, 'create'])->name('create');
        Route::post('/', [AboutStatController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [AboutStatController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [AboutStatController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [AboutStatController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // دسته‌بندی‌های FAQ و Testimonial (تب در فرانت)
    Route::prefix('taxonomies/{type}')->name('taxonomies.')
        ->whereIn('type', ['faq', 'testimonial'])
        ->group(function () {
            Route::get('/', [TaxonomyController::class, 'index'])->name('index');
            Route::post('/', [TaxonomyController::class, 'store'])->name('store');
            Route::put('/{id}', [TaxonomyController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [TaxonomyController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

    // تنظیمات عمومی سایت
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'edit'])->name('edit');
        Route::put('/', [SettingsController::class, 'update'])->name('update');
    });

});
