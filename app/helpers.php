<?php

if (! function_exists('storage_url')) {
    /**
     * URL یک فایل در public disk — جایگزین asset('storage/...') که روی
     * هاست‌های بدون symlink ۴۰۴ می‌شود. این تابع همیشه از route Laravel
     * استفاده می‌کند که فایل را با PHP استریم می‌کند.
     *
     * @param  string|null  $path  مسیر نسبی داخل storage/app/public
     */
    function storage_url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return route('storage.proxy', ['path' => $path]);
    }
}

if (! function_exists('fa_to_en_digits')) {
    /**
     * نرمال‌سازیِ ارقامِ فارسی و عربی به انگلیسی — برای این‌که سرچ‌ها فارغ از
     * نوعِ رقمِ ورودی کار کنند (۰۹۱۲ ↔ 0912). کاراکترهای غیرعددی دست‌نخورده
     * می‌مانند. ورودیِ null → رشتهٔ خالی.
     */
    function fa_to_en_digits(?string $value): string
    {
        if ($value === null || $value === '') {
            return (string) $value;
        }

        $fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $ar = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $en = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($fa, $en, str_replace($ar, $en, $value));
    }
}
