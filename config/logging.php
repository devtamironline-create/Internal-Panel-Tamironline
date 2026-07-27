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
