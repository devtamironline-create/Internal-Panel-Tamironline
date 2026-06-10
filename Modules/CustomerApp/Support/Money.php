<?php

namespace Modules\CustomerApp\Support;

/**
 * تبدیل واحد پول بین DB (ریال) و API موبایل (تومان).
 *
 * در کل پنل و WP CRM، مبالغ به‌صورت ریال ذخیره می‌شوند. ولی فرانت موبایل
 * intuitively با تومان کار می‌کند. این helper مرز تبدیل را در یک‌جا متمرکز
 * می‌کند تا اشتباه ضرب‌وتقسیم اتفاق نیفتد.
 *
 *   Money::rialToToman(125000)  // → 12500
 *   Money::tomanToRial(12500)   // → 125000
 */
final class Money
{
    public static function rialToToman(int|float|null $rial): ?int
    {
        if ($rial === null) {
            return null;
        }

        return (int) round(((float) $rial) / 10);
    }

    public static function tomanToRial(int|float|null $toman): ?int
    {
        if ($toman === null) {
            return null;
        }

        return (int) round(((float) $toman) * 10);
    }
}
