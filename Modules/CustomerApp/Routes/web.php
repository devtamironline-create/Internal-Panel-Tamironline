<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerApp\Http\Controllers\Admin\AppSettingsController;
use Modules\CustomerApp\Http\Controllers\Admin\DashboardController;
use Modules\CustomerApp\Http\Controllers\Admin\ReviewController;

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
    });
