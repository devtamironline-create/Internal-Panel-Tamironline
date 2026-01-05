<?php

return [
    /*
    |--------------------------------------------------------------------------
    | پیکربندی پیامک
    |--------------------------------------------------------------------------
    */

    'default' => env('SMS_DRIVER', 'kavenegar'),

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
        'sender' => env('KAVENEGAR_SENDER'),
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
        'expires_in' => 120,
        'max_attempts' => 5,
        'resend_delay' => 60,
    ],
];
