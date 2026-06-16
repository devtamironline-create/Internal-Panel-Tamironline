<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\SeoSettingsController;

// بخش مدیریت سئو (نیاز به لاگین + دسترسی manage-seo)
Route::middleware(['auth', 'can:manage-seo'])
    ->prefix('admin/seo')
    ->name('seo.admin.')
    ->group(function () {
        Route::get('/settings', [SeoSettingsController::class, 'index'])->name('settings');
        Route::put('/settings', [SeoSettingsController::class, 'update'])->name('settings.update');
    });
