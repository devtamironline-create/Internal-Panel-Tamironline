<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | آستانهٔ ثبتِ درخواست‌های کند (میلی‌ثانیه)
    |--------------------------------------------------------------------------
    |
    | صفر یعنی خاموش. عمداً این‌جا و نه با env() در خودِ میان‌افزار خوانده
    | می‌شود: بعد از `config:cache` تابعِ env() مقدارِ null برمی‌گرداند و
    | پروفایلر بی‌صدا از کار می‌افتاد — دقیقاً وقتی که روی پروداکشن به آن
    | نیاز داریم.
    |
    */

    'slow_request_ms' => (int) env('SLOW_REQUEST_MS', 0),

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'single')),
            // اگر نوشتن لاگ شکست بخورد (مثلاً storage/logs قابل نوشتن نباشد)،
            // درخواست اصلی نباید crash کند. true یعنی هر استثنا داخل لایه
            // logging سایلنت جذب می‌شود.
            'ignore_exceptions' => true,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        // گزارشِ فعالیتِ کاربران — هر اکشنِ مهم (ایجاد/تغییر/حذف/ورود) یک خطِ
        // JSON در storage/logs/activity/activity-YYYY-MM-DD.log. چرخشِ روزانه
        // با نگهداریِ config('activity-log.retention_days') روز (پیش‌فرض ۱۵).
        // درخواست‌های کندتر از SLOW_REQUEST_MS — فقط وقتی آن env ست باشد
        // چیزی نوشته می‌شود. برای عیب‌یابیِ «پنل کند شده».
        'slow' => [
            'driver' => 'daily',
            'path' => storage_path('logs/slow-requests.log'),
            'level' => 'warning',
            'days' => (int) env('SLOW_REQUEST_LOG_DAYS', 7),
            'permission' => 0640,
            'ignore_exceptions' => true,
        ],

        'activity' => [
            'driver' => 'daily',
            'path' => storage_path('logs/activity/activity.log'),
            'level' => 'info',
            'days' => (int) env('ACTIVITY_LOG_DAYS', 15),
            'tap' => [\App\Support\ActivityLog\JsonLogFormatter::class],
            'permission' => 0640,
            // نوشتنِ لاگ هرگز نباید درخواستِ اصلی را بشکند.
            'ignore_exceptions' => true,
        ],

        // لاگِ ساخت‌یافتهٔ تحویلِ Conversion به Google (فاز ۲ ردیابی تماس).
        // هرگز توکن/کلید/credential داخل این کانال نوشته نمی‌شود و
        // شناسه‌های کلیک (gclid/…) پیش از لاگ ماسک می‌شوند.
        'ads-google' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ads-google.log'),
            'level' => 'info',
            'days' => (int) env('ADS_GOOGLE_LOG_DAYS', 30),
            // 0666 عمداً: روی سرور، دستورهای دستی گاهی با root اجرا می‌شوند و
            // فایلِ روزانه را root می‌سازند؛ با 0640 یوزرِ panel (scheduler)
            // دیگر نمی‌توانست append کند و ads:google-poll کرش می‌کرد
            // (خطای ۱۴۰۵/۰۵/۲۶: chmod(): Operation not permitted).
            'permission' => 0666,
            'ignore_exceptions' => true,
        ],

        // لاگِ اختصاصیِ AI — جزئیاتِ هر درخواستِ gateway و هر تصمیمِ خطِ لولهٔ
        // خودکار در «فایل» (storage/logs/ai-YYYY-MM-DD.log)، جدا از laravel.log.
        'ai' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ai.log'),
            'level' => 'debug',
            'days' => env('LOG_AI_DAYS', 30),
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
