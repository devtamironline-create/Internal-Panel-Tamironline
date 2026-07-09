<?php

use Illuminate\Support\Facades\Route;
use Modules\Site\Http\Controllers\Admin\AboutStatController;
use Modules\Site\Http\Controllers\MediaServeController;

// ─── Media serve (public route — مستقل از auth برای فایل‌های public) ──
// خود کنترلر visibility=private را با auth/permission چک می‌کند.
Route::get('/media/{id}/{name?}', [MediaServeController::class, 'serve'])
    ->where('id', '[0-9]+')->name('site.media.serve');
Route::get('/media/{id}/v/{variant}/{name?}', [MediaServeController::class, 'serveVariant'])
    ->where('id', '[0-9]+')->where('variant', '[a-z]+')->name('site.media.serve-variant');
use Modules\Site\Http\Controllers\Admin\ArticleController;
use Modules\Site\Http\Controllers\Admin\BannerController;
use Modules\Site\Http\Controllers\Admin\BlogTopicController;
use Modules\Site\Http\Controllers\Admin\CacheController;
use Modules\Site\Http\Controllers\Admin\CommentController as AdminCommentController;
use Modules\Site\Http\Controllers\Admin\ContactMessageController;
use Modules\Site\Http\Controllers\Admin\FaqController;
use Modules\Site\Http\Controllers\Admin\MediaController;
use Modules\Site\Http\Controllers\Admin\PageContentController;
use Modules\Site\Http\Controllers\Admin\PageController;
use Modules\Site\Http\Controllers\Admin\ReviewController as AdminReviewController;
use Modules\Site\Http\Controllers\Admin\SettingsController;
use Modules\Site\Http\Controllers\Admin\TaxonomyController;
use Modules\Site\Http\Controllers\SiteAdminController;

// بخش مدیریت سایت (نیاز به لاگین)
Route::middleware(['auth'])->prefix('admin/site')->name('site.admin.')->group(function () {
    Route::get('/', [SiteAdminController::class, 'dashboard'])->name('dashboard');

    // پیام‌های تماس
    Route::prefix('contact-messages')->name('contact-messages.')->group(function () {
        Route::get('/', [ContactMessageController::class, 'index'])->name('index');
        Route::get('/{id}', [ContactMessageController::class, 'show'])->name('show');
        Route::put('/{id}/status', [ContactMessageController::class, 'updateStatus'])->name('update-status');
        Route::delete('/{id}', [ContactMessageController::class, 'destroy'])->name('destroy');
    });

    // نظرات و توصیه‌نامه‌ها (یکپارچه — audio + text)
    Route::prefix('reviews')->name('reviews.')->group(function () {
        Route::get('/', [AdminReviewController::class, 'index'])->name('index');
        Route::get('/create', [AdminReviewController::class, 'create'])->name('create');
        Route::post('/', [AdminReviewController::class, 'store'])->name('store');
        Route::get('/{id}', [AdminReviewController::class, 'show'])->whereAlphaNumeric('id')->name('show');
        Route::get('/{id}/edit', [AdminReviewController::class, 'edit'])->whereAlphaNumeric('id')->name('edit');
        Route::put('/{id}', [AdminReviewController::class, 'update'])->whereAlphaNumeric('id')->name('update');
        Route::put('/{id}/status', [AdminReviewController::class, 'updateStatus'])->whereAlphaNumeric('id')->name('update-status');
        Route::put('/{id}/toggle-publish', [AdminReviewController::class, 'togglePublish'])->whereAlphaNumeric('id')->name('toggle-publish');
        Route::post('/{id}/reply', [AdminReviewController::class, 'reply'])->whereAlphaNumeric('id')->name('reply');
        Route::delete('/{id}', [AdminReviewController::class, 'destroy'])->whereAlphaNumeric('id')->name('destroy');
    });

    // Redirectهای backward-compat برای URLهای قدیمی
    Route::get('/testimonials', fn () => redirect()->route('site.admin.reviews.index', ['type' => 'audio']));
    Route::get('/device-reviews', fn () => redirect()->route('site.admin.reviews.index', ['type' => 'text']));

    // میانبر «محتوای دستگاه» — هر دستگاه فیلدهای DeviceContent در فرم edit خود دارد
    Route::get('/device-content', function () {
        return redirect()->route('crm.devices.index');
    })->name('device-content');

    // کامنت‌های polymorphic (مقالات/دستگاه‌ها/برندها)
    Route::prefix('comments')->name('comments.')->group(function () {
        Route::get('/', [AdminCommentController::class, 'index'])->name('index');
        Route::post('/bulk', [AdminCommentController::class, 'bulk'])->name('bulk');
        Route::get('/{id}', [AdminCommentController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('/{id}/status', [AdminCommentController::class, 'updateStatus'])->whereNumber('id')->name('update-status');
        Route::post('/{id}/reply', [AdminCommentController::class, 'reply'])->whereNumber('id')->name('reply');
        Route::delete('/{id}', [AdminCommentController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // هوش مصنوعی (AI) — رجیستریِ مدل‌ها + تنظیماتِ مودریشن + اجرای مودریشن
    Route::prefix('ai')->name('ai.')->middleware('can:manage-ai')->group(function () {
        $ai = \Modules\Site\Http\Controllers\Admin\AiController::class;
        Route::get('/', [$ai, 'index'])->name('index');
        Route::put('/settings', [$ai, 'saveSettings'])->name('settings');
        Route::put('/reply-settings', [$ai, 'saveReplySettings'])->name('reply-settings');
        Route::post('/models', [$ai, 'storeModel'])->name('models.store');
        Route::put('/models/{model}', [$ai, 'updateModel'])->whereNumber('model')->name('models.update');
        Route::delete('/models/{model}', [$ai, 'destroyModel'])->whereNumber('model')->name('models.destroy');
        Route::post('/models/{model}/test', [$ai, 'testModel'])->whereNumber('model')->name('models.test');
        Route::post('/moderate/comment/{comment}', [$ai, 'moderateComment'])->whereNumber('comment')->name('moderate.comment');
        Route::post('/moderate/question/{question}', [$ai, 'moderateQuestion'])->whereNumber('question')->name('moderate.question');
    });

    // انجمن — سوالات و کارشناسان
    Route::prefix('forum')->name('forum.')->group(function () {
        // داشبورد و audit log
        Route::get('/', [\Modules\Site\Http\Controllers\Admin\Forum\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/activity', [\Modules\Site\Http\Controllers\Admin\Forum\DashboardController::class, 'activity'])->name('activity');

        // گزارش‌های کاربران
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [\Modules\Site\Http\Controllers\Admin\Forum\ReportController::class, 'index'])->name('index');
            Route::put('/{id}', [\Modules\Site\Http\Controllers\Admin\Forum\ReportController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [\Modules\Site\Http\Controllers\Admin\Forum\ReportController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // لیست بن
        Route::prefix('banlist')->name('banlist.')->group(function () {
            Route::get('/', [\Modules\Site\Http\Controllers\Admin\Forum\BanlistController::class, 'index'])->name('index');
            Route::post('/', [\Modules\Site\Http\Controllers\Admin\Forum\BanlistController::class, 'store'])->name('store');
            Route::delete('/{id}', [\Modules\Site\Http\Controllers\Admin\Forum\BanlistController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // مدیریت برچسب‌ها
        Route::prefix('tags')->name('tags.')->group(function () {
            Route::get('/', [\Modules\Site\Http\Controllers\Admin\Forum\TagsController::class, 'index'])->name('index');
            Route::put('/rename', [\Modules\Site\Http\Controllers\Admin\Forum\TagsController::class, 'rename'])->name('rename');
            Route::put('/merge', [\Modules\Site\Http\Controllers\Admin\Forum\TagsController::class, 'merge'])->name('merge');
            Route::delete('/destroy', [\Modules\Site\Http\Controllers\Admin\Forum\TagsController::class, 'destroy'])->name('destroy');
        });

        // تنظیمات سراسری انجمن
        Route::get('/settings', [\Modules\Site\Http\Controllers\Admin\Forum\SettingsController::class, 'index'])->name('settings');
        Route::put('/settings', [\Modules\Site\Http\Controllers\Admin\Forum\SettingsController::class, 'update'])->name('settings.update');

        // سوالات
        Route::prefix('topics')->name('topics.')->group(function () {
            Route::get('/', [\Modules\Site\Http\Controllers\Admin\Forum\TopicController::class, 'index'])->name('index');
            Route::post('/', [\Modules\Site\Http\Controllers\Admin\Forum\TopicController::class, 'store'])->name('store');
            Route::put('/{topic}', [\Modules\Site\Http\Controllers\Admin\Forum\TopicController::class, 'update'])->whereNumber('topic')->name('update');
            Route::put('/{topic}/toggle', [\Modules\Site\Http\Controllers\Admin\Forum\TopicController::class, 'toggle'])->whereNumber('topic')->name('toggle');
            Route::delete('/{topic}', [\Modules\Site\Http\Controllers\Admin\Forum\TopicController::class, 'destroy'])->whereNumber('topic')->name('destroy');
        });

        Route::prefix('questions')->name('questions.')->group(function () {
            Route::get('/', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'index'])->name('index');
            Route::post('/bulk', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'bulk'])->name('bulk');
            Route::get('/{id}', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'show'])->whereNumber('id')->name('show');
            Route::put('/{id}', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'update'])->whereNumber('id')->name('update');
            Route::put('/{id}/status', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'updateStatus'])->whereNumber('id')->name('update-status');
            Route::put('/{id}/toggle/{flag}', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'toggleFlag'])->whereNumber('id')->whereIn('flag', ['is_hot', 'is_featured'])->name('toggle-flag');
            Route::put('/{id}/topic', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'updateTopic'])->whereNumber('id')->name('update-topic');
            Route::post('/{id}/admin-reply', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'adminReply'])->whereNumber('id')->name('admin-reply');
            Route::delete('/{id}', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'destroy'])->whereNumber('id')->name('destroy');
            // پاسخ‌های nested
            Route::put('/{id}/answers/{answerId}/status', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'answerUpdateStatus'])->whereNumber('id')->whereNumber('answerId')->name('answers.update-status');
            Route::delete('/{id}/answers/{answerId}', [\Modules\Site\Http\Controllers\Admin\Forum\QuestionController::class, 'answerDestroy'])->whereNumber('id')->whereNumber('answerId')->name('answers.destroy');
        });
        // کارشناسان
        Route::prefix('experts')->name('experts.')->group(function () {
            Route::get('/', [\Modules\Site\Http\Controllers\Admin\Forum\ExpertController::class, 'index'])->name('index');
            Route::get('/create', [\Modules\Site\Http\Controllers\Admin\Forum\ExpertController::class, 'create'])->name('create');
            Route::post('/', [\Modules\Site\Http\Controllers\Admin\Forum\ExpertController::class, 'store'])->name('store');
            Route::get('/{expert}/edit', [\Modules\Site\Http\Controllers\Admin\Forum\ExpertController::class, 'edit'])->name('edit');
            Route::put('/{expert}', [\Modules\Site\Http\Controllers\Admin\Forum\ExpertController::class, 'update'])->name('update');
            Route::delete('/{expert}', [\Modules\Site\Http\Controllers\Admin\Forum\ExpertController::class, 'destroy'])->name('destroy');
        });
    });

    // مقالات بلاگ
    Route::prefix('blog')->name('blog.')->group(function () {
        // تاپیک‌ها
        Route::prefix('topics')->name('topics.')->group(function () {
            Route::get('/', [BlogTopicController::class, 'index'])->name('index');
            Route::get('/create', [BlogTopicController::class, 'create'])->name('create');
            Route::post('/', [BlogTopicController::class, 'store'])->name('store');
            Route::get('/{topic}/edit', [BlogTopicController::class, 'edit'])->name('edit');
            Route::put('/{topic}', [BlogTopicController::class, 'update'])->name('update');
            Route::delete('/{topic}', [BlogTopicController::class, 'destroy'])->name('destroy');
        });

        // مقالات
        Route::prefix('articles')->name('articles.')->group(function () {
            Route::get('/', [ArticleController::class, 'index'])->name('index');
            Route::get('/create', [ArticleController::class, 'create'])->name('create');
            Route::post('/', [ArticleController::class, 'store'])->name('store');
            Route::post('/bulk', [ArticleController::class, 'bulk'])->name('bulk');
            Route::get('/{article}/edit', [ArticleController::class, 'edit'])->name('edit');
            Route::put('/{article}', [ArticleController::class, 'update'])->name('update');
            Route::put('/{article}/toggle-publish', [ArticleController::class, 'togglePublish'])->name('toggle-publish');
            // تغییرِ سریعِ دسته (دستگاه) یا برندِ مقاله از داخلِ جدولِ لیست.
            Route::put('/{article}/quick-classify', [ArticleController::class, 'quickClassify'])->name('quick-classify');
            Route::put('/{article}/device/{device}/toggle-active', [ArticleController::class, 'toggleDeviceActive'])->name('device.toggle-active');
            Route::put('/{article}/brand/{brand}/toggle-active', [ArticleController::class, 'toggleBrandActive'])->name('brand.toggle-active');
            Route::delete('/{article}', [ArticleController::class, 'destroy'])->name('destroy');
        });
    });

    // صفحات سایت — قدیمی (free-form content، در حال جایگزینی با page-content)
    Route::prefix('pages')->name('pages.')->group(function () {
        Route::get('/', [PageController::class, 'index'])->name('index');
        Route::get('/create', [PageController::class, 'create'])->name('create');
        Route::post('/', [PageController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [PageController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PageController::class, 'update'])->name('update');
        Route::delete('/{id}', [PageController::class, 'destroy'])->name('destroy');
    });

    // محتوای صفحات سایت — section-based
    Route::prefix('page-content')->name('page-content.')->group(function () {
        Route::get('/', [PageContentController::class, 'index'])->name('index');
        // slugها lowercase با زیرخط‌اند (مثلِ device_brand) — whereAlpha زیرخط را
        // رد می‌کرد و باعثِ ۴۰۴ می‌شد. الگویِ اختصاصیِ ترکیبیِ هر دستگاه slug
        // مجازیِ device_brand:{device_slug} دارد → «:» و «-» و رقم هم مجازند.
        Route::get('/{slug}', [PageContentController::class, 'edit'])->where('slug', '[a-z0-9_:\-]+')->name('edit');
        Route::put('/{slug}', [PageContentController::class, 'update'])->where('slug', '[a-z0-9_:\-]+')->name('update');
    });

    // مخزن مدیا (مرکزی — مثل WordPress)
    Route::prefix('media')->name('media.')->group(function () {
        Route::get('/', [MediaController::class, 'index'])->name('index');
        Route::post('/upload', [MediaController::class, 'upload'])->name('upload');
        Route::put('/settings', [MediaController::class, 'saveSettings'])->name('settings');
        Route::get('/picker', [MediaController::class, 'picker'])->name('picker');
        Route::get('/{id}', [MediaController::class, 'show'])->whereNumber('id')->name('show');
        Route::put('/{id}', [MediaController::class, 'update'])->whereNumber('id')->name('update');
        Route::post('/{id}/rebuild-variants', [MediaController::class, 'rebuildVariants'])->whereNumber('id')->name('rebuild-variants');
        Route::delete('/{id}', [MediaController::class, 'destroy'])->whereNumber('id')->name('destroy');
        Route::post('/tags', [MediaController::class, 'storeTag'])->name('tags.store');
        Route::delete('/tags/{id}', [MediaController::class, 'destroyTag'])->whereNumber('id')->name('tags.destroy');
    });

    // بنرها و اسلایدر
    Route::prefix('banners')->name('banners.')->group(function () {
        Route::get('/', [BannerController::class, 'index'])->name('index');
        Route::get('/create', [BannerController::class, 'create'])->name('create');
        Route::post('/', [BannerController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [BannerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [BannerController::class, 'update'])->name('update');
        Route::delete('/{id}', [BannerController::class, 'destroy'])->name('destroy');
    });

    // سوالات متداول (مخزن)
    Route::prefix('faqs')->name('faqs.')->group(function () {
        Route::get('/', [FaqController::class, 'index'])->name('index');
        Route::get('/create', [FaqController::class, 'create'])->name('create');
        Route::post('/', [FaqController::class, 'store'])->name('store');
        Route::post('/bulk', [FaqController::class, 'bulk'])->name('bulk');
        Route::get('/{id}/edit', [FaqController::class, 'edit'])->name('edit');
        Route::put('/{id}', [FaqController::class, 'update'])->name('update');
        Route::post('/{id}/duplicate', [FaqController::class, 'duplicate'])->name('duplicate');
        Route::delete('/{id}', [FaqController::class, 'destroy'])->name('destroy');
    });

    // آمار صفحه‌ی About
    Route::prefix('about-stats')->name('about-stats.')->group(function () {
        Route::get('/', [AboutStatController::class, 'index'])->name('index');
        Route::get('/create', [AboutStatController::class, 'create'])->name('create');
        Route::post('/', [AboutStatController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [AboutStatController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [AboutStatController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [AboutStatController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // دسته‌بندی‌های FAQ و Testimonial (تب در فرانت)
    Route::prefix('taxonomies/{type}')->name('taxonomies.')
        ->whereIn('type', ['faq', 'testimonial'])
        ->group(function () {
            Route::get('/', [TaxonomyController::class, 'index'])->name('index');
            Route::post('/', [TaxonomyController::class, 'store'])->name('store');
            Route::put('/{id}', [TaxonomyController::class, 'update'])->whereNumber('id')->name('update');
            Route::put('/{id}/toggle', [TaxonomyController::class, 'toggle'])->whereNumber('id')->name('toggle');
            Route::delete('/{id}', [TaxonomyController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

    // تنظیمات عمومی سایت
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'edit'])->name('edit');
        Route::put('/', [SettingsController::class, 'update'])->name('update');
    });

    // پاک‌سازی کش فرانت (Next.js on-demand revalidation)
    Route::prefix('cache')->name('cache.')->middleware('can:manage-site-settings')->group(function () {
        Route::get('/', [CacheController::class, 'index'])->name('index');
        Route::put('/config', [CacheController::class, 'updateConfig'])->name('config');
        Route::post('/purge', [CacheController::class, 'purge'])->name('purge');
        Route::post('/test', [CacheController::class, 'test'])->name('test');
    });

});
