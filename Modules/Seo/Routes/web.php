<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\AuditController;
use Modules\Seo\Http\Controllers\NotFoundController;
use Modules\Seo\Http\Controllers\RedirectController;
use Modules\Seo\Http\Controllers\SeoRoleController;
use Modules\Seo\Http\Controllers\SeoSettingsController;
use Modules\Seo\Http\Controllers\SeoToolsController;

// بخش مدیریت سئو (نیاز به لاگین + دسترسی manage-seo)
Route::middleware(['auth', 'can:manage-seo'])
    ->prefix('admin/seo')
    ->name('seo.admin.')
    ->group(function () {
        Route::get('/settings', [SeoSettingsController::class, 'index'])->name('settings');
        Route::put('/settings', [SeoSettingsController::class, 'update'])->name('settings.update');

        // ریدایرکت‌ها
        Route::get('/redirects', [RedirectController::class, 'index'])->name('redirects.index');
        Route::post('/redirects', [RedirectController::class, 'store'])->name('redirects.store');
        Route::put('/redirects/{redirect}/toggle', [RedirectController::class, 'toggle'])->name('redirects.toggle');
        Route::delete('/redirects/{redirect}', [RedirectController::class, 'destroy'])->name('redirects.destroy');

        // مانیتور ۴۰۴
        Route::get('/404', [NotFoundController::class, 'index'])->name('not-found.index');
        Route::delete('/404/{notFound}', [NotFoundController::class, 'destroy'])->name('not-found.destroy');

        // مانیتورینگ و آدیت
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::post('/audit/run', [AuditController::class, 'run'])->name('audit.run');
        Route::get('/audit/run/{run}', [AuditController::class, 'show'])->name('audit.show');
        Route::get('/audit/run/{run}/export/{format}', [AuditController::class, 'export'])
            ->where('format', 'csv|json')->name('audit.export');

        // ابزارها: Import/Export + Audit log
        Route::get('/tools', [SeoToolsController::class, 'index'])->name('tools.index');
        Route::get('/tools/export', [SeoToolsController::class, 'export'])->name('tools.export');
        Route::post('/tools/import', [SeoToolsController::class, 'import'])->name('tools.import');

        // Role manager
        Route::get('/roles', [SeoRoleController::class, 'index'])->name('roles.index');
        Route::put('/roles', [SeoRoleController::class, 'update'])->name('roles.update');
    });
