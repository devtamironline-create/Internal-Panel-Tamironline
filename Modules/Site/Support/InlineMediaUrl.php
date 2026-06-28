<?php

namespace Modules\Site\Support;

/**
 * نرمال‌سازیِ تصاویرِ داخلِ HTML برای پاسخِ API وب‌سایت.
 *
 * دو کار انجام می‌شود:
 *   ۱) normalize(): رفعِ lazy-loadِ وردپرس — خیلی از <img>های واردشده از
 *      وردپرس، src را یک placeholder (مثلِ data:image/svg یا blank.gif)
 *      گذاشته‌اند و URLِ واقعی در data-src / data-lazy-src / srcset است.
 *      چون فرانتِ جدید JSِ lazyload ندارد، فقط placeholder رندر می‌شود و
 *      تصویر دیده نمی‌شود. اینجا URLِ واقعی را به src ارتقا می‌دهیم.
 *   ۲) absolutize(): مسیرهای /media/{id} را با هاستِ زنده‌ی درخواست مطلق
 *      می‌کند (مسیرهای نسبی یا مطلق با هاستِ غلطِ زمانِ import).
 *
 * تصاویرِ خارجی (وردپرسِ قدیمی) و data: دست‌نخورده می‌مانند مگر اینکه
 * placeholder باشند. عملیات idempotent است و منطقِ پنل را تغییر نمی‌دهد.
 */
final class InlineMediaUrl
{
    /** صفاتی که URLِ واقعیِ تصویر در آن‌ها مخفی می‌شود (lazy-loadِ وردپرس). */
    private const LAZY_SRC_ATTRS = [
        'data-src', 'data-lazy-src', 'data-original', 'data-orig-src',
        'data-original-src', 'data-lazy', 'data-url', 'data-image',
    ];

    /**
     * رفعِ lazy-load + مطلق‌سازیِ /media — نقطه‌ی ورودِ توصیه‌شده برای محتوای CMS.
     */
    public static function normalize(?string $html, ?string $base): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }
        if (stripos($html, '<img') === false) {
            return self::absolutize($html, $base);
        }

        $html = preg_replace_callback('/<img\b[^>]*>/i', fn ($m) => self::fixLazyImg($m[0]), $html) ?? $html;

        return self::absolutize($html, $base);
    }

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

    /**
     * یک تگِ <img> را بررسی می‌کند: اگر src یک placeholder/خالی باشد، URLِ واقعی
     * را از صفاتِ lazy (یا srcset) به src ارتقا می‌دهد.
     */
    private static function fixLazyImg(string $tag): string
    {
        $src = self::attr($tag, 'src');

        // src واقعی است → دست نزن (حتی اگر data-src هم داشته باشد).
        if (! self::isPlaceholder($src)) {
            return $tag;
        }

        // ۱) از صفاتِ lazy یک URLِ واقعی پیدا کن.
        foreach (self::LAZY_SRC_ATTRS as $attr) {
            $v = self::attr($tag, $attr);
            if (self::isRealUrl($v)) {
                return self::withSrc($tag, trim((string) $v));
            }
        }

        // ۲) از data-srcset یا srcset اولین URL را بردار.
        foreach (['data-srcset', 'srcset'] as $attr) {
            $v = self::attr($tag, $attr);
            if ($v === null || trim($v) === '') {
                continue;
            }
            $first = trim((string) (explode(',', $v)[0] ?? ''));
            $url = trim((string) (preg_split('/\s+/', $first)[0] ?? ''));
            if (self::isRealUrl($url)) {
                return self::withSrc($tag, $url);
            }
        }

        return $tag;
    }

    private static function isPlaceholder(?string $src): bool
    {
        if ($src === null) {
            return true;
        }
        $src = trim($src);
        if ($src === '') {
            return true;
        }
        // data URI (svg/gif یک‌پیکسلیِ placeholder)
        if (stripos($src, 'data:image') === 0) {
            return true;
        }
        // فایل‌های متداولِ placeholder
        if (preg_match('#(?:^|[/_-])(?:lazy|placeholder|blank|spacer|loader|transparent|1x1)[^/]*\.(?:gif|png|svg|webp)#i', $src)) {
            return true;
        }

        return false;
    }

    private static function isRealUrl(?string $v): bool
    {
        if ($v === null) {
            return false;
        }
        $v = trim($v);

        return $v !== '' && stripos($v, 'data:') !== 0 && (bool) preg_match('#^(https?://|//|/)#i', $v);
    }

    private static function attr(string $tag, string $name): ?string
    {
        // (?<![-\w]) تا «src» داخلِ «data-src»/«data-lazy-src» را اشتباه نخواند.
        $n = preg_quote($name, '/');
        if (preg_match('/(?<![-\w])'.$n.'\s*=\s*"([^"]*)"/i', $tag, $m)) {
            return $m[1];
        }
        if (preg_match('/(?<![-\w])'.$n.'\s*=\s*\'([^\']*)\'/i', $tag, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * src فعلی را حذف و یک src جدید بلافاصله بعد از <img قرار می‌دهد.
     * مقدار خام (attribute-encoded) درج می‌شود تا از double-encoding جلوگیری شود.
     */
    private static function withSrc(string $tag, string $url): string
    {
        // فقط src مستقل را حذف کن، نه data-src/data-lazy-src را.
        $tag = (string) preg_replace('/(?<![-\w])src\s*=\s*"[^"]*"/i', '', $tag);
        $tag = (string) preg_replace('/(?<![-\w])src\s*=\s*\'[^\']*\'/i', '', $tag);

        return (string) preg_replace('/<img\b/i', '<img src="'.$url.'"', $tag, 1);
    }
}
