<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\AuditController;
use Modules\Seo\Http\Controllers\BrokenLinkController;
use Modules\Seo\Http\Controllers\CannibalizationController;
use Modules\Seo\Http\Controllers\CanonicalController;
use Modules\Seo\Http\Controllers\DashboardController;
use Modules\Seo\Http\Controllers\InternalLinkController;
use Modules\Seo\Http\Controllers\NotFoundController;
use Modules\Seo\Http\Controllers\RedirectController;
use Modules\Seo\Http\Controllers\SeoRoleController;
use Modules\Seo\Http\Controllers\SeoSettingsController;
use Modules\Seo\Http\Controllers\SeoTemplateController;
use Modules\Seo\Http\Controllers\SeoToolsController;

// بخش مدیریت سئو (نیاز به لاگین + دسترسی manage-seo)
Route::middleware(['auth', 'can:manage-seo'])
    ->prefix('admin/seo')
    ->name('seo.admin.')
    ->group(function () {
        // داشبورد سئو (نمای کلی)
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/settings', [SeoSettingsController::class, 'index'])->name('settings');
        Route::put('/settings', [SeoSettingsController::class, 'update'])->name('settings.update');

        // قالب‌های عنوان/توضیح به‌ازای نوع صفحه
        Route::get('/templates', [SeoTemplateController::class, 'edit'])->name('templates.index');
        Route::put('/templates', [SeoTemplateController::class, 'update'])->name('templates.update');

        // ریدایرکت‌ها
        Route::get('/redirects', [RedirectController::class, 'index'])->name('redirects.index');
        Route::post('/redirects', [RedirectController::class, 'store'])->name('redirects.store');
        // مسیرهای ثابت پیش از binding پویا تعریف می‌شوند تا «export» به‌عنوان مدل تفسیر نشود.
        Route::get('/redirects/export', [RedirectController::class, 'export'])->name('redirects.export');
        Route::post('/redirects/import', [RedirectController::class, 'import'])->name('redirects.import');
        Route::put('/redirects/{redirect}/toggle', [RedirectController::class, 'toggle'])->name('redirects.toggle');
        Route::delete('/redirects/{redirect}', [RedirectController::class, 'destroy'])->name('redirects.destroy');

        // مانیتور ۴۰۴
        Route::get('/404', [NotFoundController::class, 'index'])->name('not-found.index');
        // مسیرهای ثابت پیش از binding پویا تعریف می‌شوند تا «bulk»/«export» به‌عنوان مدل تفسیر نشوند.
        Route::get('/404/export', [NotFoundController::class, 'export'])->name('not-found.export');
        Route::delete('/404/bulk', [NotFoundController::class, 'bulkDestroy'])->name('not-found.bulk-destroy');
        Route::post('/404/{notFound}/ignore', [NotFoundController::class, 'ignore'])->name('not-found.ignore');
        Route::post('/404/{notFound}/unignore', [NotFoundController::class, 'unignore'])->name('not-found.unignore');
        Route::delete('/404/{notFound}', [NotFoundController::class, 'destroy'])->name('not-found.destroy');

        // مانیتورینگ و آدیت
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::post('/audit/run', [AuditController::class, 'run'])->name('audit.run');
        Route::get('/audit/run/{run}', [AuditController::class, 'show'])->name('audit.show');
        Route::get('/audit/run/{run}/export/{format}', [AuditController::class, 'export'])
            ->where('format', 'csv|json')->name('audit.export');

        // لینک‌های خراب (گرافِ لینک) + محلِ دقیق + کارگاهِ اصلاح
        Route::get('/broken-links', [BrokenLinkController::class, 'index'])->name('broken-links.index');
        Route::get('/broken-links/{target}', [BrokenLinkController::class, 'show'])->name('broken-links.show');
        Route::post('/broken-links/{target}/redirect', [BrokenLinkController::class, 'redirect'])->name('broken-links.redirect');
        Route::post('/broken-links/{target}/ignore', [BrokenLinkController::class, 'ignore'])->name('broken-links.ignore');
        Route::post('/broken-links/{target}/unignore', [BrokenLinkController::class, 'unignore'])->name('broken-links.unignore');

        // لینک‌سازیِ داخلی — داشبورد + پیشنهادها (تأیید/رد/اعمال/بازگردانی)
        Route::prefix('internal-links')->name('internal-links.')->group(function () {
            Route::get('/', [InternalLinkController::class, 'index'])->name('index');
            Route::get('/suggestions', [InternalLinkController::class, 'suggestions'])->name('suggestions');
            Route::post('/generate', [InternalLinkController::class, 'generate'])->name('generate');
            Route::post('/apply-approved', [InternalLinkController::class, 'applyApproved'])->name('apply-approved');
            Route::post('/{suggestion}/approve', [InternalLinkController::class, 'approve'])->name('approve')->whereNumber('suggestion');
            Route::post('/{suggestion}/reject', [InternalLinkController::class, 'reject'])->name('reject')->whereNumber('suggestion');
            Route::put('/{suggestion}/anchor', [InternalLinkController::class, 'updateAnchor'])->name('anchor')->whereNumber('suggestion');
            Route::post('/{suggestion}/apply', [InternalLinkController::class, 'apply'])->name('apply')->whereNumber('suggestion');
            Route::post('/{suggestion}/revert', [InternalLinkController::class, 'revert'])->name('revert')->whereNumber('suggestion');
        });

        // اصلاحِ لینک‌های داخلیِ اشتباه در سطحِ دیتابیس (مقالات/دستگاه‌ها/برندها/ترکیبی)
        Route::prefix('link-fixes')->name('link-fixes.')->group(function () {
            Route::get('/', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'index'])->name('index');
            Route::post('/', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'store'])->name('store');
            Route::post('/import', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'import'])->name('import');
            Route::post('/normalize-domain', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'normalizeDomain'])->name('normalize-domain');
            Route::post('/import-redirects', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'importFromRedirects'])->name('import-redirects');
            Route::post('/scan-all', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'scanAll'])->name('scan-all');
            Route::post('/apply-all', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'applyAll'])->name('apply-all');
            Route::get('/changes', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'changes'])->name('changes');
            Route::post('/changes/{change}/revert', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'revert'])->name('revert');
            Route::get('/{rule}', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'show'])->name('show')->whereNumber('rule');
            Route::post('/{rule}/apply', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'apply'])->name('apply')->whereNumber('rule');
            Route::put('/{rule}', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'update'])->name('update')->whereNumber('rule');
            Route::delete('/{rule}', [\Modules\Seo\Http\Controllers\LinkFixController::class, 'destroy'])->name('destroy')->whereNumber('rule');
        });

        // گزارش canonical (مشکلات + canonicalهای تکراری) از آخرین کرال تمام‌شده
        Route::get('/canonical', [CanonicalController::class, 'index'])->name('canonical.index');

        // گزارش هم‌نوع‌خواری کلمات (Cannibalization) + محتوای تکراری
        Route::get('/cannibalization', [CannibalizationController::class, 'index'])->name('cannibalization.index');

        // ابزارها: Import/Export + Audit log
        Route::get('/tools', [SeoToolsController::class, 'index'])->name('tools.index');
        Route::get('/tools/export', [SeoToolsController::class, 'export'])->name('tools.export');
        Route::post('/tools/import', [SeoToolsController::class, 'import'])->name('tools.import');
        Route::get('/tools/export-csv', [SeoToolsController::class, 'exportCsv'])->name('tools.export-csv');
        Route::post('/tools/import-csv', [SeoToolsController::class, 'importCsv'])->name('tools.import-csv');

        // Role manager
        Route::get('/roles', [SeoRoleController::class, 'index'])->name('roles.index');
        Route::put('/roles', [SeoRoleController::class, 'update'])->name('roles.update');
    });
