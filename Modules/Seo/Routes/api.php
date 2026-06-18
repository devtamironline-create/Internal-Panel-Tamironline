<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\Api\MetaController;
use Modules\Seo\Http\Controllers\Api\RobotsController;
use Modules\Seo\Http\Controllers\Api\SettingsController;
use Modules\Seo\Http\Controllers\Api\SitemapController;
use Modules\Seo\Http\Controllers\Api\RedirectController;
use Modules\Seo\Http\Controllers\Api\NotFoundController;

/*
 * API هدلس سئو — هم‌راستا با کنوانسیون پروژه: prefix «v1» و بدون «/api».
 * (پاسخ‌های v1/* به‌صورت JSON و با هندلینگ خطای اختصاصی برمی‌گردند.)
 */
Route::prefix('v1/seo')->middleware('throttle:120,1')->group(function () {
    Route::get('/meta', [MetaController::class, 'show'])->name('api.v1.seo.meta');
    Route::get('/settings', [SettingsController::class, 'show'])->name('api.v1.seo.settings');

    // Sitemap (XML) + robots.txt
    Route::get('/sitemap-index.xml', [SitemapController::class, 'index'])->name('api.v1.seo.sitemap-index');
    // فایلِ بزرگ به chunkهای ۵۰هزارتایی تقسیم می‌شود: {type}-{page}.xml
    Route::get('/sitemap/{type}-{page}.xml', [SitemapController::class, 'show'])
        ->where('type', '[a-z_]+')->where('page', '[0-9]+')->name('api.v1.seo.sitemap-chunk');
    Route::get('/sitemap/{type}.xml', [SitemapController::class, 'show'])
        ->where('type', '[a-z_]+')->name('api.v1.seo.sitemap');
    Route::get('/robots.txt', [RobotsController::class, 'show'])->name('api.v1.seo.robots');

    // ریدایرکت‌ها (برای middleware.ts فرانت)
    Route::get('/redirects', [RedirectController::class, 'index'])->name('api.v1.seo.redirects');

    // ثبت بازدید ۴۰۴ از فرانت
    Route::post('/404', [NotFoundController::class, 'store'])->name('api.v1.seo.404');
});
