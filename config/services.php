<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'internal' => [
        // توکن ثابت برای احراز هویت سرور-به-سرور (Next BFF → Laravel API).
        // حداقل ۴۸ بایت رندوم. هرگز در ریپو commit نشود.
        'token'     => env('INTERNAL_API_TOKEN'),
        // برای rotation بدون downtime می‌توان توکن قدیمی را موقتاً نگه داشت.
        'token_old' => env('INTERNAL_API_TOKEN_OLD'),
    ],

];
