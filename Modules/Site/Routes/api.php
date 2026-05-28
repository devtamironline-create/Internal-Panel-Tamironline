<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\Api\V1\AboutStatController;
use Modules\Site\Http\Controllers\Api\V1\ActivityController;
use Modules\Site\Http\Controllers\Api\V1\BlogController;
use Modules\Site\Http\Controllers\Api\V1\CatalogBrandController;
use Modules\Site\Http\Controllers\Api\V1\CatalogDeviceBrandController;
use Modules\Site\Http\Controllers\Api\V1\CatalogDeviceController;
use Modules\Site\Http\Controllers\Api\V1\CommentController;
use Modules\Site\Http\Controllers\Api\V1\ContactMessageController;
use Modules\Site\Http\Controllers\Api\V1\DevicePageController;
use Modules\Site\Http\Controllers\Api\V1\DeviceReviewController;
use Modules\Site\Http\Controllers\Api\V1\ForumController;
use Modules\Site\Http\Controllers\Api\V1\HealthController;
use Modules\Site\Http\Controllers\Api\V1\PageController;
use Modules\Site\Http\Controllers\Api\V1\SettingsController;
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
        Route::get('/catalog/devices', [CatalogDeviceController::class, 'index'])
            ->name('api.v1.catalog.devices.index');
        Route::get('/catalog/device-categories', [CatalogDeviceController::class, 'categories'])
            ->name('api.v1.catalog.device-categories.index');
        Route::get('/site/about-stats', [AboutStatController::class, 'index'])
            ->name('api.v1.site.about-stats.index');
        Route::get('/settings/global', [SettingsController::class, 'global'])
            ->name('api.v1.settings.global');

        // ── Blog ─────────────────────────────────────────────────
        Route::get('/blog/topics', [BlogController::class, 'topics'])->name('api.v1.blog.topics');
        Route::get('/blog/articles', [BlogController::class, 'index'])->name('api.v1.blog.articles.index');
        Route::get('/blog/articles/{slug}', [BlogController::class, 'show'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.blog.articles.show');

        // ── Comments (polymorphic — الان فقط Article) ───────────
        Route::get('/blog/articles/{slug}/comments', [CommentController::class, 'indexForArticle'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.blog.articles.comments.index');

        // ── Forum (انجمن پرسش و پاسخ) ──────────────────────────
        Route::get('/forum/questions', [ForumController::class, 'index'])->name('api.v1.forum.questions.index');
        Route::get('/forum/questions/{slug}', [ForumController::class, 'show'])
            ->where('slug', '[^/]+')->name('api.v1.forum.questions.show');
        Route::get('/forum/experts', [ForumController::class, 'experts'])->name('api.v1.forum.experts');
        Route::get('/forum/expert-answers', [ForumController::class, 'expertAnswers'])->name('api.v1.forum.expert-answers');
        Route::get('/forum/hot-problems', [ForumController::class, 'hotProblems'])->name('api.v1.forum.hot-problems');
        Route::get('/forum/device-stats', [ForumController::class, 'deviceStats'])->name('api.v1.forum.device-stats');

        // pages + devices (public read)
        Route::get('/pages/{slug}', [PageController::class, 'show'])
            ->whereAlpha('slug')
            ->name('api.v1.pages.show');
        Route::get('/devices/{slug}', [DevicePageController::class, 'show'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.devices.show');
    });

    // ── Public writes با throttle محدودتر ────────────────────────
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/blog/articles/{slug}/comments', [CommentController::class, 'storeForArticle'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.blog.articles.comments.store');
    });
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/comments/{id}/like', [CommentController::class, 'like'])
            ->whereNumber('id')
            ->name('api.v1.comments.like');
        Route::post('/forum/answers/{id}/upvote', [ForumController::class, 'upvoteAnswer'])
            ->whereNumber('id')->name('api.v1.forum.answers.upvote');
        Route::post('/forum/questions/{id}/upvote', [ForumController::class, 'upvoteQuestion'])
            ->whereNumber('id')->name('api.v1.forum.questions.upvote');
        Route::post('/forum/answers/{id}/accept', [ForumController::class, 'acceptAnswer'])
            ->whereNumber('id')->name('api.v1.forum.answers.accept');
    });

    // ── Forum writes (سوال جدید + پاسخ جدید) با throttle جدا ──
    Route::middleware('throttle:3,10')->group(function () {  // 3 سوال در هر 10 دقیقه
        Route::post('/forum/questions', [ForumController::class, 'store'])
            ->name('api.v1.forum.questions.store');
    });
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/forum/questions/{slug}/answers', [ForumController::class, 'storeAnswer'])
            ->where('slug', '[^/]+')
            ->name('api.v1.forum.answers.store');
    });

    // ── Internal-only writes (BFF → API) ──────────────────────────
    Route::middleware(['internal.token', 'throttle:10,1'])->group(function () {
        Route::post('/contact-messages', [ContactMessageController::class, 'store'])
            ->name('api.v1.contact-messages.store');
    });

    // ── Internal-only detail endpoints (BFF → API) ────────────────
    // catalog brand/device detail با تمام فیلدهای CMS — فقط برای فرانت Next.js
    Route::middleware(['internal.token', 'throttle:60,1'])->group(function () {
        Route::get('/catalog/brands/{slug}', [CatalogBrandController::class, 'show'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.catalog.brands.show');
        Route::get('/catalog/devices/{slug}', [CatalogDeviceController::class, 'show'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.catalog.devices.show');

        // صفحه‌ی ترکیبی device × brand
        Route::get('/catalog/devices/{deviceSlug}/{brandSlug}', [CatalogDeviceBrandController::class, 'show'])
            ->where('deviceSlug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->where('brandSlug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.catalog.device-brand.show');
    });

    // ── Device Reviews — public read, public write with throttle ──
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/catalog/devices/{slug}/reviews', [DeviceReviewController::class, 'index'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.catalog.devices.reviews.index');
    });
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/catalog/devices/{slug}/reviews', [DeviceReviewController::class, 'store'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.catalog.devices.reviews.store');
    });
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/catalog/reviews/{id}/like', [DeviceReviewController::class, 'like'])
            ->where('id', '[0-9A-Za-z]{26}')
            ->name('api.v1.catalog.reviews.like');
    });

});
