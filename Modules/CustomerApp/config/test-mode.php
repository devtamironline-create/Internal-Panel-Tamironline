<?php

/**
 * حالت تست اپلیکیشن — اجازه می‌دهد فرانت بدون Kavenegar و بدون OTP واقعی
 * کل flow login را تست کند.
 *
 * ⚠️ حتماً در production غیرفعال باشد:
 *   - CUSTOMER_APP_TEST_MODE=false (یا env را نگذارید)
 *
 * در حالت تست:
 *   - POST /v1/auth/send-otp فقط 200 برمی‌گرداند، Kavenegar صدا نمی‌خورد
 *   - POST /v1/auth/verify-otp اگر code === master_otp باشد قبول می‌کند
 *     (به جای تأیید واقعی OTP) و توکن می‌دهد
 *   - GET /v1/customer/status فیلد test_mode_active=true را برمی‌گرداند
 *     تا فرانت overlay زرد «حالت تست» را نمایش دهد
 *
 * شماره موبایل آزمایشی توصیه‌شده: 09000000000 — یک Customer جدا برای QA
 * که نباید روی گزارشات production تأثیر بگذارد.
 */
return [

    /**
     * فعال‌سازی اصلی. هر چیز دیگر بی‌اثر است اگر این false باشد.
     */
    'enabled' => (bool) env('CUSTOMER_APP_TEST_MODE', false),

    /**
     * کد OTP master که در حالت تست به‌جای کد واقعی پذیرفته می‌شود.
     * توصیه: یک عدد ۶ رقمی غیرواضح مثل 487213 (نه 123456).
     */
    'master_otp' => (string) env('CUSTOMER_APP_TEST_OTP', '000000'),

    /**
     * لیست شماره‌های مجاز برای استفاده در حالت تست (CSV).
     * خالی = همه‌ی شماره‌ها پذیرفته می‌شوند (در حالت enabled).
     * توصیه: فقط شماره‌های QA را اینجا بگذارید تا کاربر واقعی به master_otp
     * دسترسی نداشته باشد.
     */
    'allowed_mobiles' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CUSTOMER_APP_TEST_MOBILES', ''))
    ))),
];
