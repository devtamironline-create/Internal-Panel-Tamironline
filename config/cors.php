<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | این API هرگز مستقیماً از مرورگر صدا زده نمی‌شود؛ تنها مصرف‌کننده، Next BFF
    | سمت‌سرور است. بنابراین allowed_origins خالی نگه داشته می‌شود.
    | اگر روزی نیاز شد، فقط دامنه‌ی فرانت اضافه شود.
    |
    */

    'paths' => ['v1/*'],

    'allowed_methods' => ['GET', 'POST'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
