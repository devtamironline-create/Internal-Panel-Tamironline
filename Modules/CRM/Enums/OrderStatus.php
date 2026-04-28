<?php

namespace Modules\CRM\Enums;

/**
 * وضعیت سفارش — هم‌تراز با CRM وردپرسی (libs/order.php).
 *
 * در WP وضعیت در postmeta `status` به‌صورت عددی ذخیره می‌شود:
 *   0=جدید، 1=هماهنگ شده، 2=باز شده، 3=معلق، 4=کنسل شده،
 *   5=انجام کار، 10=ایاب و ذهاب، 100=رد سفارش.
 *
 * در لاراول از value های string (نام انگلیسی) استفاده می‌کنیم تا
 * در DB و کد خواناتر باشد، ولی نگاشت دوطرفه با کد عددی WP حفظ می‌شود
 * (متدهای wpCode/fromWpCode).
 *
 * Returned (11) در WP موجود نیست؛ اضافه‌ست برای پنل لاراول
 * به‌خاطر فرق معنایی با Transit/«ایاب و ذهاب».
 */
enum OrderStatus: string
{
    case New = 'new';                 // WP 0   — جدید
    case Coordinated = 'coordinated'; // WP 1   — هماهنگ شده
    case Open = 'open';               // WP 2   — باز شده
    case Suspended = 'suspended';     // WP 3   — معلق
    case Cancelled = 'cancelled';     // WP 4   — کنسل شده
    case Completed = 'completed';     // WP 5   — انجام کار
    case Transit = 'transit';         // WP 10  — ایاب و ذهاب
    case Returned = 'returned';       // WP 11  — برگشت‌خورده (لاراول‌اِسپِسیفیک)
    case Declined = 'declined';       // WP 100 — رد سفارش

    public function label(): string
    {
        return match ($this) {
            self::New => 'جدید',
            self::Coordinated => 'هماهنگ شده',
            self::Open => 'باز شده',
            self::Suspended => 'معلق',
            self::Cancelled => 'کنسل شده',
            self::Completed => 'انجام کار',
            self::Transit => 'ایاب و ذهاب',
            self::Returned => 'برگشت‌خورده',
            self::Declined => 'رد سفارش',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'bg-gray-100 text-gray-800',
            self::Coordinated => 'bg-blue-100 text-blue-800',
            self::Open => 'bg-indigo-100 text-indigo-800',
            self::Suspended => 'bg-yellow-100 text-yellow-800',
            self::Cancelled => 'bg-red-100 text-red-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Transit => 'bg-amber-100 text-amber-800',
            self::Returned => 'bg-orange-100 text-orange-800',
            self::Declined => 'bg-red-200 text-red-900',
        };
    }

    /** نگاشت enum به کد عددی متناظر در WP. */
    public function wpCode(): int
    {
        return match ($this) {
            self::New => 0,
            self::Coordinated => 1,
            self::Open => 2,
            self::Suspended => 3,
            self::Cancelled => 4,
            self::Completed => 5,
            self::Transit => 10,
            self::Returned => 11,
            self::Declined => 100,
        };
    }

    /** ساخت enum از کد عددی WP (یا رشتهٔ عددی). */
    public static function fromWpCode(int|string|null $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ((int) $code) {
            0 => self::New,
            1 => self::Coordinated,
            2 => self::Open,
            3 => self::Suspended,
            4 => self::Cancelled,
            5 => self::Completed,
            10 => self::Transit,
            11 => self::Returned,
            100 => self::Declined,
            default => null,
        };
    }

    /** لیست گزینه‌ها برای dropdownها. */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
