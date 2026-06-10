<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerApp\Http\Controllers\Admin\AppSettingsController;
use Modules\CustomerApp\Http\Controllers\Admin\DashboardController;
use Modules\CustomerApp\Http\Controllers\Admin\HolidayController;
use Modules\CustomerApp\Http\Controllers\Admin\ReviewController;
use Modules\CustomerApp\Http\Controllers\Admin\ReviewTagController;

/*
|--------------------------------------------------------------------------
| Customer App — Admin Routes
|--------------------------------------------------------------------------
| پنل «مدیریت اپلیکیشن (مشتریان)» — هاب مرکزی برای همه‌ی موارد اپ موبایل:
| تنظیمات (maintenance/versions/force_reauth)، نظرسنجی‌ها، آمار، لینک‌های
| سریع به CRM. permission محافظ: manage-permissions (super-admin).
*/

Route::middleware(['auth'])
    ->prefix('admin/customer-app')
    ->name('customer-app.')
    ->group(function () {

        // Dashboard — صفحه‌ی هاب
        Route::get('/', [DashboardController::class, 'index'])
            ->middleware('can:manage-permissions')
            ->name('dashboard');

        // App Settings (Block 0)
        Route::middleware('can:manage-permissions')->group(function () {
            Route::get('/settings', [AppSettingsController::class, 'index'])->name('settings.index');
            Route::put('/settings', [AppSettingsController::class, 'update'])->name('settings.update');
            Route::post('/settings/force-reauth', [AppSettingsController::class, 'forceReauth'])->name('settings.force-reauth');
        });

        // Reviews moderation (Block 3)
        Route::middleware('can:manage-permissions')->group(function () {
            Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
            Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');
            Route::put('/reviews/{review}/moderate', [ReviewController::class, 'moderate'])->name('reviews.moderate');
            Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
        });

        // Holidays management (Block 5c)
        Route::middleware('can:manage-permissions')->group(function () {
            Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays.index');
            Route::get('/holidays/create', [HolidayController::class, 'create'])->name('holidays.create');
            Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
            Route::get('/holidays/{holiday}/edit', [HolidayController::class, 'edit'])->name('holidays.edit');
            Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
            Route::put('/holidays/{holiday}/toggle-active', [HolidayController::class, 'toggleActive'])->name('holidays.toggle-active');
            Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
        });

        // Review Tags management (pros/cons for mobile review form)
        Route::middleware('can:manage-permissions')->group(function () {
            Route::get('/review-tags', [ReviewTagController::class, 'index'])->name('review-tags.index');
            Route::get('/review-tags/create', [ReviewTagController::class, 'create'])->name('review-tags.create');
            Route::post('/review-tags', [ReviewTagController::class, 'store'])->name('review-tags.store');
            Route::get('/review-tags/{reviewTag}/edit', [ReviewTagController::class, 'edit'])->name('review-tags.edit');
            Route::put('/review-tags/{reviewTag}', [ReviewTagController::class, 'update'])->name('review-tags.update');
            Route::put('/review-tags/{reviewTag}/toggle-active', [ReviewTagController::class, 'toggleActive'])->name('review-tags.toggle-active');
            Route::delete('/review-tags/{reviewTag}', [ReviewTagController::class, 'destroy'])->name('review-tags.destroy');
        });
    });
