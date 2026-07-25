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
        'token' => env('INTERNAL_API_TOKEN'),
        // برای rotation بدون downtime می‌توان توکن قدیمی را موقتاً نگه داشت.
        'token_old' => env('INTERNAL_API_TOKEN_OLD'),
        // IPهای معتمدِ سرورِ فرانت (SSR/ISR) — با کاما جدا. درخواست‌های این IPها
        // از rate-limitِ endpointهای فقط‌خواندنیِ عمومی (seo/کاتالوگ) معاف‌اند؛
        // هنگامِ بازتولیدِ انبوهِ صفحاتِ ISR همه از یک IP می‌آیند و سقفِ per-IP
        // خیلی زود پر می‌شد (429 → بیک‌شدنِ متایِ پیش‌فرض در صفحهٔ استاتیک).
        'trusted_ips' => env('TRUSTED_FRONTEND_IPS', '62.220.124.156'),
    ],

    // رباتِ بلهٔ تعمیرآنلاین — توکنِ ربات رازِ مشترکِ اعتبارسنجیِ initData
    // مینی‌اپ است (الگوریتمِ WebAppData). فقط سمتِ سرور؛ هرگز در فرانت.
    'bale' => [
        'bot_token' => env('BALE_BOT_TOKEN'),
        // حداکثر عمرِ auth_date برای پذیرشِ initData (ثانیه)
        'init_data_max_age' => env('BALE_INIT_DATA_MAX_AGE', 86400),
    ],

    // Webhook به فرانت Next.js برای revalidateTag فوری پس از تغییر بنر/مقاله/...
    // در فرانت endpoint مثل /api/revalidate تعریف کنید که tag را با
    // header X-Revalidation-Secret تأیید کند و revalidateTag(tag) را صدا بزند.
    'site_revalidation' => [
        'url' => env('SITE_REVALIDATION_URL'),
        'secret' => env('SITE_REVALIDATION_SECRET'),
    ],

    // نقشه نشان (neshan.org) — platform.neshan.org
    //   web_key:     کلید Web SDK برای نمایش نقشه (فرانت اپ + پنل ادمین)
    //   service_key: کلید REST برای «تبدیل نقطه به آدرس» (reverse geocode)
    //                — فقط سمت سرور استفاده می‌شود و هرگز به فرانت نمی‌رود.
    'neshan' => [
        'web_key' => env('NESHAN_WEB_KEY'),
        'service_key' => env('NESHAN_SERVICE_KEY'),
        'base_url' => env('NESHAN_BASE_URL', 'https://api.neshan.org'),
    ],

];
