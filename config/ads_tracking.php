<?php

/*
|--------------------------------------------------------------------------
| Server-side Google Ads Call Tracking — پایهٔ Backend
|--------------------------------------------------------------------------
| دیتابیسِ تعمیرآنلاین Source of Truth است: ثبتِ Call Click به هیچ سرویسِ
| بیرونی (از جمله Google) وابسته نیست. آپلود به Google در این مرحله عمداً
| خاموش است و فقط فیلدها/وضعیت‌ها آماده‌اند.
*/

return [
    'enabled' => (bool) env('ADS_TRACKING_ENABLED', true),

    // originهای مجاز برای CORS دو endpoint عمومی — با کاما جدا.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'ADS_TRACKING_ORIGINS',
        'https://tamironline.com,https://www.tamironline.com,https://app.tamironline.com'
    ))))),

    // طولِ عمرِ attribution (روز) — بعد از آن منقضی محسوب می‌شود؛ در
    // business logic هاردکد نشده است.
    'attribution_ttl_days' => (int) env('ADS_TRACKING_ATTRIBUTION_TTL_DAYS', 90),

    // مرحلهٔ بعد — الان به هیچ عنوان روشن نشود.
    'google_upload_enabled' => (bool) env('ADS_TRACKING_GOOGLE_UPLOAD', false),

    /*
    |----------------------------------------------------------------------
    | Google Data Manager API — فاز ۲ (آپلود Conversion به Google Ads)
    |----------------------------------------------------------------------
    | Backend داخل ایران است؛ هیچ درخواستِ Google (توکن یا ingest یا
    | requestStatus) نباید مستقیم خارج شود — همه از Outbound Proxy عبور
    | می‌کنند و اگر پروکسی ناقص/خاموش بود، درخواست Fail-Closed مسدود
    | می‌شود (event در DB می‌ماند و بعداً retry می‌شود).
    */
    'google' => [
        // سوییچ اصلی آپلود واقعی — جدا از فلگ بالا نیست؛ همان را می‌خواند
        // تا دو منبعِ حقیقت ساخته نشود.
        'upload_enabled' => (bool) env('ADS_TRACKING_GOOGLE_UPLOAD', false),

        // تا وقتی true است هیچ conversion واقعی ساخته نمی‌شود (فقط validate).
        'validate_only' => (bool) env('ADS_TRACKING_GOOGLE_VALIDATE_ONLY', true),

        'customer_id' => (string) env('GOOGLE_ADS_CUSTOMER_ID', ''),
        'conversion_action_id' => (string) env('GOOGLE_ADS_CALL_CONVERSION_ACTION_ID', ''),
        'conversion_action_name' => (string) env('GOOGLE_ADS_CALL_CONVERSION_ACTION_NAME', 'TO | SERVER CALL CLICK | OMD'),

        // مسیرِ JSON سرویس‌اکانت — خارج از web root و git.
        'credentials_path' => (string) env('GOOGLE_DATA_MANAGER_CREDENTIALS', ''),

        'oauth_token_url' => 'https://oauth2.googleapis.com/token',
        'scope' => 'https://www.googleapis.com/auth/datamanager',
        'base_url' => 'https://datamanager.googleapis.com/v1',

        'proxy' => [
            'enabled' => (bool) env('GOOGLE_HTTP_PROXY_ENABLED', true),
            'url' => (string) env('GOOGLE_HTTP_PROXY_URL', ''),
            'username' => (string) env('GOOGLE_HTTP_PROXY_USERNAME', ''),
            'password' => (string) env('GOOGLE_HTTP_PROXY_PASSWORD', ''),
        ],

        'batch_size' => max(1, (int) env('GOOGLE_DATA_MANAGER_BATCH_SIZE', 1)),
        'request_timeout' => (int) env('GOOGLE_DATA_MANAGER_REQUEST_TIMEOUT', 30),
        'connect_timeout' => (int) env('GOOGLE_DATA_MANAGER_CONNECT_TIMEOUT', 10),
        'max_attempts' => (int) env('GOOGLE_DATA_MANAGER_MAX_ATTEMPTS', 10),

        // حاشیهٔ امنِ کشِ توکن (ثانیه) پیش از expiry.
        'token_safety_margin' => 300,
    ],
];
