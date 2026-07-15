<?php

use Illuminate\Support\Facades\Route;
use Modules\Seo\Http\Controllers\Api\LlmsController;
use Modules\Seo\Http\Controllers\Api\MetaController;
use Modules\Seo\Http\Controllers\Api\NotFoundController;
use Modules\Seo\Http\Controllers\Api\RedirectController;
use Modules\Seo\Http\Controllers\Api\RobotsController;
use Modules\Seo\Http\Controllers\Api\SettingsController;
use Modules\Seo\Http\Controllers\Api\SitemapController;

/*
 * API هدلس سئو — هم‌راستا با کنوانسیون پروژه: prefix «v1» و بدون «/api».
 * (پاسخ‌های v1/* به‌صورت JSON و با هندلینگ خطای اختصاصی برمی‌گردند.)
 */
Route::prefix('v1/seo')->group(function () {

    // ── منابعِ عمومیِ خزش‌شونده و کش‌شونده (sitemap/robots/llms) ──
    // این‌ها را خزنده‌ها (گوگل و…) و پراکسیِ فرانت پشتِ یک IP پشت‌سرهم می‌گیرند؛
    // throttle 120 خیلی زود پر می‌شد و «Too Many Attempts» می‌داد. سقفِ سخاوتمند
    // (600/min) چون پاسخ‌ها کش‌شونده و فقط‌خواندنی‌اند امن است.
    Route::middleware('throttle:600,1')->group(function () {
        // ── سایت‌مپِ منطبق‌بر‌اسپکِ سئو (v2) ──
        // Index اصلی → tamironline.com/sitemap.xml (فرانت به این rewrite می‌کند)
        Route::get('/sitemap.xml', [SitemapController::class, 'specIndex'])->name('api.v1.seo.sitemap.spec-index');
        // فایل‌های نام‌دار → tamironline.com/sitemaps/sitemap-{name}.xml
        Route::get('/sitemaps/sitemap-{name}.xml', [SitemapController::class, 'specNamed'])
            ->where('name', '[a-z\-]+')->name('api.v1.seo.sitemap.spec-file');

        // ── نسخهٔ قدیمی (دست‌نخورده تا تستِ نسخهٔ جدید در Search Console) ──
        Route::get('/sitemap-index.xml', [SitemapController::class, 'index'])->name('api.v1.seo.sitemap-index');
        // فایلِ بزرگ به chunkهای ۵۰هزارتایی تقسیم می‌شود: {type}-{page}.xml
        Route::get('/sitemap/{type}-{page}.xml', [SitemapController::class, 'show'])
            ->where('type', '[a-z_]+')->where('page', '[0-9]+')->name('api.v1.seo.sitemap-chunk');
        Route::get('/sitemap/{type}.xml', [SitemapController::class, 'show'])
            ->where('type', '[a-z_]+')->name('api.v1.seo.sitemap');
        Route::get('/robots.txt', [RobotsController::class, 'show'])->name('api.v1.seo.robots');
        Route::get('/llms.txt', [LlmsController::class, 'show'])->name('api.v1.seo.llms');
    });

    // ── بقیه‌ی اندپوینت‌های سئو (سقفِ معمول) ──
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/meta', [MetaController::class, 'show'])->name('api.v1.seo.meta');
        Route::get('/settings', [SettingsController::class, 'show'])->name('api.v1.seo.settings');

        // ریدایرکت‌ها (برای middleware.ts فرانت)
        Route::get('/redirects', [RedirectController::class, 'index'])->name('api.v1.seo.redirects');

        // ثبت بازدید ۴۰۴ از فرانت
        Route::post('/404', [NotFoundController::class, 'store'])->name('api.v1.seo.404');
    });
});
