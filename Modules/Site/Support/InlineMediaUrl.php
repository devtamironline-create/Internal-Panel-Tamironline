<?php

namespace Modules\Site\Support;

/**
 * مطلق‌سازی آدرس تصاویرِ داخل HTML برای پاسخ API وب‌سایت.
 *
 * متن CMS دستگاه/برند ممکن است <img> هایی داشته باشد که src آن‌ها مسیر
 * media پنل است — به‌صورت نسبی (/media/12/x.jpg) یا مطلق با هاستِ غلطی که
 * موقع import در CLI «پخته» شده (مثل http://localhost/media/12/x.jpg).
 *
 * این کلاس فقط مسیرهای /media/{id} را برمی‌دارد و با هاستِ زنده‌ی درخواست
 * دوباره مطلق می‌کند. تصاویر خارجی (مثل سایت قدیمی وردپرس) و data: دست‌نخورده
 * می‌مانند. عملیات idempotent است.
 *
 * منطق نمایش پنل تغییری نمی‌کند؛ این فقط لایه‌ی خروجیِ API است.
 */
final class InlineMediaUrl
{
    public static function absolutize(?string $html, ?string $base): ?string
    {
        if ($html === null || $html === '' || $base === null || $base === '') {
            return $html;
        }
        // مسیر سریع: اگر اصلاً /media/ ندارد، کاری نکن
        if (! str_contains($html, '/media/')) {
            return $html;
        }

        $base = rtrim($base, '/');

        return preg_replace_callback(
            '/(<img\b[^>]*?\bsrc=)(["\'])(.*?)\2/i',
            function ($m) use ($base) {
                $src = trim($m[3]);
                // فقط src هایی که مسیرشان دقیقاً /media/{id}[/...] است
                if (preg_match('#^(?:https?://[^/]+)?(/media/\d+(?:/[^"\'\s]*)?)$#i', $src, $mm)) {
                    return $m[1].$m[2].$base.$mm[1].$m[2];
                }

                return $m[0];
            },
            $html
        ) ?? $html;
    }
}
