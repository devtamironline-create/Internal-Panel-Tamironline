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
        // پایهٔ APIِ ربات (سازگار با تلگرام) — برای ارسالِ نوتیفیکیشن به چتِ کاربر
        'api_base' => env('BALE_API_BASE', 'https://tapi.bale.ai'),

        // سفیرِ بله (docs.bale.ai/safir) — ارسالِ پیام با «شمارهٔ موبایل» برای
        // مشتری‌هایی که bale_user_id ندارند. سرویسِ تجاری است و کلیدِ دسترسیِ
        // جدا دارد؛ خالی = این مسیر غیرفعال.
        'safir_access_key' => env('BALE_SAFIR_ACCESS_KEY'),
        'safir_bot_id' => env('BALE_SAFIR_BOT_ID'),
        'safir_base' => env('BALE_SAFIR_BASE', 'https://safir.bale.ai'),
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

    // پوش نوتیفیکیشن نجوا (push.najva.com) — اعلانِ وبِ اپِ تکنسین.
    //
    //   api_key    — هدرِ `apiKey` تمام درخواست‌ها (نه Authorization).
    //   website_id — شناسهٔ عددیِ سایت، از GET /v1/sender. با UUIDِ اسکریپت
    //                (`data-najva-id`) که داخل اپ است اشتباه نشود؛ فرستادن
    //                UUID به‌جای عدد، خطای ۴۰۰ می‌دهد.
    //
    // هر دو از پنل ادمین هم قابل ویرایش‌اند و مقدارِ `crm_settings` بر این
    // مقدارها اولویت دارد — همان الگوی کاوه‌نگار.
    //
    // یادآوری: بدونِ ثبتِ IP سرور در وایت‌لیستِ پنل نجوا، هر ارسال ۴۱۶
    // می‌گیرد؛ این تنظیمات به‌تنهایی کافی نیست.
    'najva' => [
        'api_key' => env('NAJVA_API_KEY'),
        'website_id' => env('NAJVA_WEBSITE_ID'),
        'base_url' => env('NAJVA_BASE_URL', 'https://push.najva.com'),
    ],

];
