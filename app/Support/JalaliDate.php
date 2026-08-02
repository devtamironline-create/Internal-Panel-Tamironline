<?php

namespace App\Support;

use Morilog\Jalali\Jalalian;

/**
 * تبدیلِ تاریخِ شمسیِ واردشده در فرم‌ها به میلادیِ قابلِ ذخیره.
 *
 * ستون‌های تاریخ در دیتابیس میلادی می‌مانند — مرتب‌سازی، بازه‌گیری و مقایسه
 * همه به آن وابسته‌اند. فقط لایهٔ ورودی/نمایش شمسی است.
 *
 * ورودی عمداً با ارفاق خوانده می‌شود: datepicker با `/` می‌دهد، ولی کاربر
 * ممکن است دستی با `-` یا با رقمِ فارسی تایپ کند و هر سه یک تاریخ‌اند.
 */
final class JalaliDate
{
    /** @var array<string, string> */
    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /** `1405/05/11` → `2026-08-02`. در صورت نامعتبر بودن `null`. */
    public static function toGregorian(?string $jalali): ?string
    {
        $clean = self::clean($jalali);
        if ($clean === null) {
            return null;
        }

        try {
            return Jalalian::fromFormat('Y/m/d', $clean)->toCarbon()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** برای ولیدیشن: آیا این رشته یک تاریخِ شمسیِ معتبر است؟ */
    public static function isValid(?string $jalali): bool
    {
        return self::toGregorian($jalali) !== null;
    }

    /** میلادی → شمسیِ نمایشی، برای پرکردنِ مقدارِ اولیهٔ فرم. */
    public static function toJalali(?string $gregorian): string
    {
        if (blank($gregorian)) {
            return '';
        }

        try {
            return Jalalian::fromDateTime($gregorian)->format('Y/m/d');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function clean(?string $value): ?string
    {
        $value = trim(strtr((string) $value, self::DIGITS));
        if ($value === '') {
            return null;
        }

        $value = str_replace(['-', '.'], '/', $value);

        if (! preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $value, $m)) {
            return null;
        }

        [, $year, $month, $day] = $m;

        // بازهٔ عقلانیِ سالِ شمسی. بدونِ این، یک تاریخِ *میلادی* مثل
        // `2026/08/02` هم الگو را پاس می‌کرد و بی‌سروصدا به سالِ ۲۶۴۷ تبدیل
        // می‌شد — خطایی که هیچ‌کس تا وقتی سند چاپ نشود نمی‌بیند.
        if ((int) $year < 1200 || (int) $year > 1600) {
            return null;
        }

        // `fromFormat('Y/m/d')` عددِ بدونِ صفرِ ابتدایی را نمی‌پذیرد، ولی کاربر
        // «1405/5/11» می‌نویسد.
        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }
}
