<?php

namespace App\Helpers;

/**
 * تبدیل شماره تماس به لینک کلیک‌-تماس برای پنل.
 *
 * - ارقام فارسی/عربی به لاتین تبدیل می‌شوند تا href="tel:..." معتبر باشد.
 * - اگر مقدار شامل چند شماره (با - یا , یا /) باشد، هر کدام جداگانه
 *   به‌صورت لینک رندر می‌شوند تا کاربر بتواند هر یک را کلیک کند.
 * - اگر مقدار خالی بود، '—' برمی‌گرداند.
 */
class TelHelper
{
    public static function render($value): string
    {
        if ($value === null || $value === '') {
            return '<span class="text-gray-400">—</span>';
        }

        // ارقام فارسی → لاتین (برای href="tel:" معتبر)
        $latin = strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        // اگر چند شماره چسبیده باشد (مثلاً 09120000000-09127777777)
        $parts = preg_split('/[\s\-,،\/]+/u', trim($latin), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (empty($parts)) {
            return '<span class="text-gray-400">—</span>';
        }

        $links = [];
        foreach ($parts as $raw) {
            $digits = preg_replace('/[^0-9+]/', '', $raw);
            if ($digits === '' || $digits === null) {
                continue;
            }

            $href = htmlspecialchars($digits, ENT_QUOTES);
            $display = htmlspecialchars($digits, ENT_QUOTES);

            $links[] = '<a href="tel:' . $href . '" '
                . 'class="inline-flex items-center gap-1 text-brand-600 hover:text-brand-700 hover:underline" '
                . 'dir="ltr" title="کلیک برای تماس">'
                . '<svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
                . '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" '
                . 'd="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>'
                . '</svg>'
                . '<span>' . $display . '</span>'
                . '</a>';
        }

        return empty($links) ? '<span class="text-gray-400">—</span>' : implode(' / ', $links);
    }
}
