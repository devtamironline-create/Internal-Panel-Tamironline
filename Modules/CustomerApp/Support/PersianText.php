<?php

namespace Modules\CustomerApp\Support;

/**
 * نرمالایز متن فارسی برای ذخیره در DB.
 *
 *   - ي عربی  → ی فارسی
 *   - ك عربی  → ک فارسی
 *   - اعداد عربی/فارسی → اعداد انگلیسی (برای فیلدهایی مثل کدپستی/شماره)
 *   - trim و collapse فاصله‌های متعدد
 *
 * این helper روی متن کاربر اپ موبایل اعمال می‌شود تا داده‌ی DB تمیز و
 * قابل جستجو باشد. اعداد فقط در حالت ‌numeric=true تبدیل می‌شوند.
 */
final class PersianText
{
    /** @var array<string, string> */
    private const LETTER_MAP = [
        'ي' => 'ی',
        'ك' => 'ک',
        'ى' => 'ی',
        // U+200C (نیم‌فاصله) و U+200B/U+FEFF (zero-width) دست‌نخورده می‌مانند
    ];

    /** @var array<string, string> */
    private const DIGIT_MAP = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public static function normalize(?string $text, bool $numeric = false): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = strtr($text, self::LETTER_MAP);
        if ($numeric) {
            $text = strtr($text, self::DIGIT_MAP);
        }
        // collapse فاصله‌های متعدد به یکی، trim لبه‌ها
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return $text === '' ? null : $text;
    }
}
