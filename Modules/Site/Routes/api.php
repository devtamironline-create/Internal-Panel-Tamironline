<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\Api\V1\AboutStatController;
use Modules\Site\Http\Controllers\Api\V1\ActivityController;
use Modules\Site\Http\Controllers\Api\V1\CatalogBrandController;
use Modules\Site\Http\Controllers\Api\V1\ContactMessageController;
use Modules\Site\Http\Controllers\Api\V1\HealthController;
use Modules\Site\Http\Controllers\Api\V1\PageController;
use Modules\Site\Http\Controllers\Api\V1\TestimonialController;

Route::prefix('v1')->group(function () {

    // ── Health check (high throttle, no auth) ─────────────────────
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/health', HealthController::class)->name('api.v1.health');
    });

    // ── Public, cached, read-only ─────────────────────────────────
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/activity/recent', [ActivityController::class, 'recent'])
            ->name('api.v1.activity.recent');
        Route::get('/testimonials', [TestimonialController::class, 'index'])
            ->name('api.v1.testimonials.index');
        Route::get('/catalog/brands', [CatalogBrandController::class, 'index'])
            ->name('api.v1.catalog.brands.index');
        Route::get('/site/about-stats', [AboutStatController::class, 'index'])
            ->name('api.v1.site.about-stats.index');
        Route::get('/pages/{slug}', [PageController::class, 'show'])
            ->whereAlpha('slug')
            ->name('api.v1.pages.show');
    });

    // ── Internal-only writes (BFF → API) ──────────────────────────
    Route::middleware(['internal.token', 'throttle:10,1'])->group(function () {
        Route::post('/contact-messages', [ContactMessageController::class, 'store'])
            ->name('api.v1.contact-messages.store');
    });

});
