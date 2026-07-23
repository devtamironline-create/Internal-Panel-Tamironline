<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\Api\V1\AboutStatController;
use Modules\Site\Http\Controllers\Api\V1\ActivityController;
use Modules\Site\Http\Controllers\Api\V1\AnalyticsController;
use Modules\Site\Http\Controllers\Api\V1\BannerController;
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
    // limiter «catalog»: BFF (توکن داخلی) معاف؛ مرورگرِ عادی 60/min بر اساس IP.
    Route::middleware('throttle:catalog')->group(function () {
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

        // تعرفهٔ خدمات (قیمت‌ها)
        Route::get('/catalog/prices', [\Modules\Site\Http\Controllers\Api\V1\PricingController::class, 'index'])
            ->name('api.v1.catalog.prices.index');
        Route::get('/catalog/devices/{slug}/prices', [\Modules\Site\Http\Controllers\Api\V1\PricingController::class, 'device'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.catalog.devices.prices');
        Route::get('/site/about-stats', [AboutStatController::class, 'index'])
            ->name('api.v1.site.about-stats.index');
        Route::get('/settings/global', [SettingsController::class, 'global'])
            ->name('api.v1.settings.global');

        // ── Blog ─────────────────────────────────────────────────
        Route::get('/blog/topics', [BlogController::class, 'topics'])->name('api.v1.blog.topics');
        Route::get('/blog/filters', [BlogController::class, 'filters'])->name('api.v1.blog.filters');
        Route::get('/blog/articles', [BlogController::class, 'index'])->name('api.v1.blog.articles.index');
        // slug ممکن است فارسی باشد (تداومِ SEO سایتِ قدیمی) → مثلِ forum از [^/]+
        Route::get('/blog/articles/{slug}', [BlogController::class, 'show'])
            ->where('slug', '[^/]+')
            ->name('api.v1.blog.articles.show');

        // ── Comments (polymorphic — الان فقط Article) ───────────
        Route::get('/blog/articles/{slug}/comments', [CommentController::class, 'indexForArticle'])
            ->where('slug', '[^/]+')
            ->name('api.v1.blog.articles.comments.index');

        // ── Banners (per-zone با cache 60s و ETag) ───────────
        Route::get('/banners/{zone}', [BannerController::class, 'byZone'])
            ->where('zone', '[a-z0-9](?:[a-z0-9_\-]*[a-z0-9])?')
            ->name('api.v1.banners.zone');

        // ── Forum (انجمن پرسش و پاسخ) ──────────────────────────
        Route::get('/forum/categories', [ForumController::class, 'categories'])->name('api.v1.forum.categories');
        Route::get('/forum/questions', [ForumController::class, 'index'])->name('api.v1.forum.questions.index');
        Route::get('/forum/questions/{slug}', [ForumController::class, 'show'])
            ->where('slug', '[^/]+')->name('api.v1.forum.questions.show');
        Route::get('/forum/experts', [ForumController::class, 'experts'])->name('api.v1.forum.experts');
        Route::get('/forum/expert-answers', [ForumController::class, 'expertAnswers'])->name('api.v1.forum.expert-answers');
        Route::get('/forum/hot-problems', [ForumController::class, 'hotProblems'])->name('api.v1.forum.hot-problems');
        Route::get('/forum/device-stats', [ForumController::class, 'deviceStats'])->name('api.v1.forum.device-stats');
        Route::get('/forum/topics', [ForumController::class, 'topics'])->name('api.v1.forum.topics');
        Route::get('/forum/popular-searches', [ForumController::class, 'popularSearches'])->name('api.v1.forum.popular-searches');
        Route::get('/forum/report-reasons', [ForumController::class, 'reportReasons'])->name('api.v1.forum.report-reasons');

        // pages + devices (public read)
        // slug ممکن است kebab-case باشد (مثلِ join-technician) → whereAlpha کافی نیست
        Route::get('/pages/{slug}', [PageController::class, 'show'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.pages.show');
        Route::get('/devices/{slug}', [DevicePageController::class, 'show'])
            ->where('slug', '[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?')
            ->name('api.v1.devices.show');
    });

    // ── کامنت‌ها — auth الزامی + throttle ────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:5,1'])->group(function () {
        Route::post('/blog/articles/{slug}/comments', [CommentController::class, 'storeForArticle'])
            ->where('slug', '[^/]+')
            ->name('api.v1.blog.articles.comments.store');
    });
    Route::middleware('throttle:public-write')->group(function () {
        // رویدادنویسِ تحلیلیِ سراسری — fire-and-forget از سمتِ کلاینت
        Route::post('/analytics/track', [AnalyticsController::class, 'track'])
            ->name('api.v1.analytics.track');
        Route::post('/banners/{id}/impression', [BannerController::class, 'impression'])
            ->name('api.v1.banners.impression');
        Route::post('/banners/{id}/click', [BannerController::class, 'click'])
            ->name('api.v1.banners.click');
        Route::post('/comments/{id}/like', [CommentController::class, 'like'])
            ->whereNumber('id')
            ->name('api.v1.comments.like');
        Route::post('/forum/answers/{id}/accept', [ForumController::class, 'acceptAnswer'])
            ->whereNumber('id')->name('api.v1.forum.answers.accept');
    });

    // ── رأیِ انجمن — فقط کاربرِ واردشده (dedupe بر اساسِ حساب، نه IPِ مشترکِ BFF).
    // throttle با auth به‌صورتِ per-user کلید می‌خورد (۳۰ رأی در دقیقه برای هر حساب).
    Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
        Route::post('/forum/answers/{id}/upvote', [ForumController::class, 'upvoteAnswer'])
            ->whereNumber('id')->name('api.v1.forum.answers.upvote');
        Route::post('/forum/questions/{id}/upvote', [ForumController::class, 'upvoteQuestion'])
            ->whereNumber('id')->name('api.v1.forum.questions.upvote');
        Route::post('/forum/answers/{id}/downvote', [ForumController::class, 'downvoteAnswer'])
            ->whereNumber('id')->name('api.v1.forum.answers.downvote');
        Route::post('/forum/questions/{id}/downvote', [ForumController::class, 'downvoteQuestion'])
            ->whereNumber('id')->name('api.v1.forum.questions.downvote');
    });

    // ── Forum writes (سوال جدید + پاسخ جدید) — auth الزامی + throttle ──
    Route::middleware(['auth:sanctum', 'throttle:3,10'])->group(function () {  // 3 سوال در هر 10 دقیقه
        Route::post('/forum/questions', [ForumController::class, 'store'])
            ->name('api.v1.forum.questions.store');
    });
    // «سوالاتِ من» و «پاسخ‌های من» — تاریخچهٔ کاربرِ واردشده (همهٔ وضعیت‌ها؛ خصوصی/no-store)
    Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
        Route::get('/forum/my-questions', [ForumController::class, 'myQuestions'])
            ->name('api.v1.forum.my-questions');
        Route::get('/forum/my-answers', [ForumController::class, 'myAnswers'])
            ->name('api.v1.forum.my-answers');
    });
    // گزارش سوء استفاده — ۵ گزارش در دقیقه بر «کاربر یا IPِ واقعی» (نه IPِ مشترکِ BFF)
    Route::middleware('throttle:forum-report')->group(function () {
        Route::post('/forum/report', [ForumController::class, 'storeReport'])
            ->name('api.v1.forum.report');
    });

    Route::middleware(['auth:sanctum', 'throttle:5,1'])->group(function () {
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
    // catalog brand/device detail با تمام فیلدهای CMS — فقط برای فرانت Next.js.
    // trusted.frontend = توکنِ BFF **یا** IPِ معتمدِ سرورِ فرانت — چون هنگامِ
    // `npm run build` در Docker توکن در مرحلهٔ build در دسترس نیست و همهٔ
    // fetchهای ISR با 401 می‌شکستند (متا/محتوای پیش‌فرض بیک می‌شد).
    Route::middleware(['trusted.frontend', 'throttle:catalog'])->group(function () {
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
