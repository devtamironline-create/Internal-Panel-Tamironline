<?php

return [
    /*
    |--------------------------------------------------------------------------
    | پیکربندی پیامک
    |--------------------------------------------------------------------------
    */

    'default' => env('SMS_DRIVER', 'kavenegar'),

    'kavenegar' => [
        'api_key' => env('SMS_KAVENEGAR_API_KEY'),
        'sender' => env('SMS_KAVENEGAR_SENDER'),
    ],

    // پروکسی برای ارسال پیامک (وقتی IP سرور بلاک شده)
    'proxy' => [
        'enabled' => env('SMS_PROXY_ENABLED', false),
        'url' => env('SMS_PROXY_URL', ''),
        'secret' => env('SMS_PROXY_SECRET', ''),
    ],

    'templates' => [
        'otp' => env('SMS_TEMPLATE_OTP', 'verify'),
        'welcome' => env('SMS_TEMPLATE_WELCOME', 'welcome'),
        'payment_reminder' => env('SMS_TEMPLATE_PAYMENT_REMINDER', 'payment-reminder'),
        'new_invoice' => env('SMS_TEMPLATE_NEW_INVOICE', 'new-invoice'),
        'ticket_reply' => env('SMS_TEMPLATE_TICKET_REPLY', 'ticket-reply'),
        'service_expiry' => env('SMS_TEMPLATE_SERVICE_EXPIRY', 'service-expiry'),
        'service_renewed' => env('SMS_TEMPLATE_SERVICE_RENEWED', 'service-renewed'),
    ],

    'otp' => [
        'length' => 6,
        // اعتبار کد به ثانیه — از SMS_OTP_EXPIRE_MINUTES (پیش‌فرض ۲ دقیقه) محاسبه می‌شود
        'expires_in' => (int) env('SMS_OTP_EXPIRE_MINUTES', 2) * 60,
        'max_attempts' => (int) env('SMS_OTP_MAX_ATTEMPTS', 5),
        'resend_delay' => (int) env('SMS_OTP_RESEND_SECONDS', 60),
    ],
];
