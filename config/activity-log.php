<?php

/**
 * پیکربندی «گزارش فعالیت» (Activity Log).
 *
 * همهٔ اکشن‌های مهم (ایجاد/تغییر/حذف رکوردها + ورود و خروج) در «فایل» ثبت
 * می‌شوند: storage/logs/activity/activity-YYYY-MM-DD.log (هر خط یک JSON).
 * نگهداری فقط برای بازهٔ retention_days است و فایل‌های قدیمی‌تر خودکار
 * پاک می‌شوند (هم توسط چرخش Monolog، هم دستور activity-log:purge).
 */
return [

    // مدت نگهداری فایل‌های لاگ (روز). پس از این مدت فایل‌ها حذف می‌شوند.
    'retention_days' => (int) env('ACTIVITY_LOG_DAYS', 15),

    // خاموش‌کردن کاملِ ثبت (برای مواقع اضطراری).
    'enabled' => (bool) env('ACTIVITY_LOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | ثبت رخدادهای اجراشده در کنسول
    |--------------------------------------------------------------------------
    | همگام‌سازی ووکامرس، seeder ها و کران‌ها هزاران رکورد را دسته‌ای می‌سازند و
    | لاگ را بی‌فایده شلوغ می‌کنند. پیش‌فرض: فقط اکشن‌های کاربران پنل ثبت شود.
    | رخدادهایی که صریحاً با ActivityLog::record ثبت شوند همیشه نوشته می‌شوند.
    */
    'log_console' => (bool) env('ACTIVITY_LOG_CONSOLE', false),

    // سقف تعداد ردیف‌های اسکن‌شده در هر جستجوی پنل (محافظ حافظه).
    'max_scan' => (int) env('ACTIVITY_LOG_MAX_SCAN', 5000),

    /*
    |--------------------------------------------------------------------------
    | مدل‌هایی که ثبت نمی‌شوند
    |--------------------------------------------------------------------------
    | لاگ‌های خودکار، دادهٔ پرنویز (چت/حضور/بازدید)، رکوردهای دسته‌ای و
    | خودِ زیرساخت لاگ. با پیشوند هم قابل تعریف است (پایان با \).
    */
    'excluded_models' => [
        // لاگ‌ها و رکوردهای ممیزیِ خودکار
        \Modules\CRM\Models\OrderStatusLog::class,
        \Modules\CRM\Models\OrderLog::class,
        \Modules\CRM\Models\SmsLog::class,
        \Modules\CRM\Models\SyncLog::class,
        \Modules\Technician\Models\TechnicianRegistrationLog::class,
        \Modules\Seo\Models\SeoChangeLog::class,
        \Modules\Seo\Models\SeoNotFound::class,
        \Modules\Seo\Models\SeoSlugHistory::class,
        \Modules\Seo\Models\SeoAudit::class,
        \Modules\Seo\Models\SeoAuditRun::class,
        \Modules\Seo\Models\SeoLink::class,
        \Modules\Seo\Models\SeoLinkTarget::class,
        \Modules\Site\Models\AiDecisionLog::class,
        \Modules\Task\Models\TaskActivity::class,

        // چت و حضور (بلادرنگ و پرحجم)
        'App\Models\Chat\\',
        \Modules\CRM\Models\TechChatMessage::class,

        // بازدید/پسند/رویداد تحلیلی
        \App\Models\AnalyticsEvent::class,
        \Modules\Site\Models\ReviewLike::class,
        \App\Models\AnnouncementView::class,

        // ارسال دسته‌ای
        \Modules\CRM\Models\BaleCampaignRecipient::class,

        // مشتقات رسانه (خودکار هنگام آپلود ساخته می‌شوند)
        \Modules\Site\Models\Media\MediaVariant::class,
        \Modules\Site\Models\Media\MediaUse::class,

        // زیرساخت
        \Illuminate\Notifications\DatabaseNotification::class,
    ],

    // فیلدهایی که هرگز نباید مقدارشان در فایل نوشته شود.
    'redacted_fields' => [
        'password', 'password_confirmation', 'remember_token', 'api_token',
        'token', 'access_token', 'refresh_token', 'secret', 'client_secret',
        'private_key', 'authorization', 'otp', 'otp_code', 'verification_code',
        'cod24_password', 'cod24_token', 'card_number', 'cvv2',
    ],

    /*
    | فیلدهایی که تغییرشان «تغییر معنادار» محسوب نمی‌شود.
    | deleted_at عمداً اینجاست: حذف/بازگردانیِ نرم رویدادِ اختصاصیِ خودش
    | (deleted/restored) را دارد و نباید یک «ویرایش» تکراری هم ثبت شود.
    */
    'ignored_fields' => [
        'deleted_at',
        'updated_at', 'created_at', 'remember_token', 'last_login_at',
        'last_login_ip', 'last_seen_at', 'views_count', 'view_count',
        'hits', 'last_hit_at', 'heartbeat_at',
    ],
];
