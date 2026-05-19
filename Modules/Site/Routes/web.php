<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\Admin\ContactMessageController;
use Modules\Site\Http\Controllers\Admin\FaqController;
use Modules\Site\Http\Controllers\Admin\TestimonialController;
use Modules\Site\Http\Controllers\SiteAdminController;

// بخش مدیریت سایت (نیاز به لاگین)
Route::middleware(['auth'])->prefix('admin/site')->name('site.admin.')->group(function () {
    Route::get('/', [SiteAdminController::class, 'dashboard'])->name('dashboard');

    // پیام‌های تماس
    Route::prefix('contact-messages')->name('contact-messages.')->group(function () {
        Route::get('/',            [ContactMessageController::class, 'index'])->name('index');
        Route::get('/{id}',        [ContactMessageController::class, 'show'])->name('show');
        Route::put('/{id}/status', [ContactMessageController::class, 'updateStatus'])->name('update-status');
        Route::delete('/{id}',     [ContactMessageController::class, 'destroy'])->name('destroy');
    });

    // نظرات مشتریان (مخزن)
    Route::prefix('testimonials')->name('testimonials.')->group(function () {
        Route::get('/',                    [TestimonialController::class, 'index'])->name('index');
        Route::get('/create',              [TestimonialController::class, 'create'])->name('create');
        Route::post('/',                   [TestimonialController::class, 'store'])->name('store');
        Route::get('/{id}/edit',           [TestimonialController::class, 'edit'])->name('edit');
        Route::put('/{id}',                [TestimonialController::class, 'update'])->name('update');
        Route::put('/{id}/toggle-publish', [TestimonialController::class, 'togglePublish'])->name('toggle-publish');
        Route::delete('/{id}',             [TestimonialController::class, 'destroy'])->name('destroy');
    });

    // سوالات متداول (مخزن)
    Route::prefix('faqs')->name('faqs.')->group(function () {
        Route::get('/',          [FaqController::class, 'index'])->name('index');
        Route::get('/create',    [FaqController::class, 'create'])->name('create');
        Route::post('/',         [FaqController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [FaqController::class, 'edit'])->name('edit');
        Route::put('/{id}',      [FaqController::class, 'update'])->name('update');
        Route::delete('/{id}',   [FaqController::class, 'destroy'])->name('destroy');
    });
});
