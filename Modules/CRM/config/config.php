<?php

return [
    'name' => 'CRM',

    /*
    |--------------------------------------------------------------------------
    | WordPress push (legacy sync to old WP CRM)
    |--------------------------------------------------------------------------
    | اگر WordPress قدیمی دیگر فعال نیست، در .env بگذارید:
    |   CRM_WP_PUSH_ENABLED=false
    |
    | با این کار observer مدل Customer هیچ HTTP request به WP نمی‌زند.
    | روزی که WP برگردد، true کنید — همان منطق push دوباره فعال می‌شود.
    */
    'wp_push' => [
        'enabled' => env('CRM_WP_PUSH_ENABLED', true),
    ],
];
