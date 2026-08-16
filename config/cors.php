<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | API روی /v1/* بازه. دو نوع مصرف‌کننده داریم:
    |   - Next.js BFF (سمت‌سرور) برای سایت — origin ندارد، نیازی به CORS نیست
    |   - PWA / موبایل (سمت‌مرورگر) برای اپ مشتری — origin اپ را در env می‌گذاریم
    |
    | CUSTOMER_APP_ORIGINS با کاما-جدا قابل تنظیم. خالی → فقط BFF (CORS off).
    | مثلاً:
    |   CUSTOMER_APP_ORIGINS=https://app.tamironline.com,https://m.tamironline.com
    |
    */

    // api/ads/* — دو endpoint عمومیِ ردیابیِ تبلیغات (سایت + PWA).
    'paths' => ['v1/*', 'api/ads/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        array_map('trim', explode(',', (string) env('CUSTOMER_APP_ORIGINS', ''))),
        // originهای ردیابیِ تبلیغات (سایت + PWA) — تنظیم در config/ads_tracking.php
        array_map('trim', explode(',', (string) env(
            'ADS_TRACKING_ORIGINS',
            'https://tamironline.com,https://www.tamironline.com,https://app.tamironline.com'
        ))),
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'Accept-Language',
        'X-Requested-With',
        // headers اپ موبایل
        'Idempotency-Key',
        'X-Device-ID',
        'X-App-Version',
        'If-None-Match',
    ],

    'exposed_headers' => [
        // فرانت باید این هدر را بخواند تا توکن renewed را در storage ذخیره کند
        'X-Renewed-Token',
        // فرانت بدنه‌ی idempotent-replay را تشخیص دهد
        'Idempotent-Replay',
        // ETag برای /bootstrap و سایر cache-friendly endpoints
        'ETag',
    ],

    'max_age' => 600,

    'supports_credentials' => false,

];
