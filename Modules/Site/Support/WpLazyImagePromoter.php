<?php

namespace Modules\Site\Support;

/**
 * ارتقای تصاویرِ lazy-loadِ وردپرس: آدرسِ واقعی که در data-src/data-lazy-src/
 * srcset قرار دارد را به src منتقل می‌کند — تا قبل از HtmlSanitizer (که data:
 * و data-src را حذف می‌کند) آدرسِ واقعی در src باشد و از دست نرود.
 *
 * باید قبل از دانلودِ تصاویر inline و sanitize اجرا شود.
 */
final class WpLazyImagePromoter
{
    public static function promote(?string $html): ?string
    {
        if ($html === null || trim($html) === '' || stripos($html, '<img') === false) {
            return $html;
        }

        return preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
            $tag = $m[0];

            // اگر src معتبرِ http/https// دارد (و placeholderِ data: نیست)، رها کن.
            $hasRealSrc = preg_match('/\bsrc\s*=\s*["\'](https?:\/\/|\/)[^"\']+["\']/i', $tag)
                && ! preg_match('/\bsrc\s*=\s*["\']\s*data:/i', $tag);
            if ($hasRealSrc) {
                return $tag;
            }

            $real = self::extractRealUrl($tag);
            if ($real === null) {
                return $tag;
            }

            $clean = (string) preg_replace('/\s+src\s*=\s*["\'][^"\']*["\']/i', '', $tag);

            return (string) preg_replace('/<img\b/i', '<img src="'.$real.'"', $clean, 1);
        }, $html) ?? $html;
    }

    /**
     * آدرسِ واقعی را از attributeهای lazy استخراج می‌کند (اولین مقدارِ معتبر).
     */
    private static function extractRealUrl(string $tag): ?string
    {
        foreach (['data-src', 'data-lazy-src', 'data-original', 'data-orig-src', 'data-large-file'] as $attr) {
            if (preg_match('/\b'.preg_quote($attr, '/').'\s*=\s*["\']([^"\']+)["\']/i', $tag, $mm)) {
                $u = trim($mm[1]);
                if (preg_match('#^(https?://|/)#i', $u)) {
                    return $u;
                }
            }
        }

        // srcset / data-srcset → اولین URL (قبل از توضیحِ اندازه)
        foreach (['data-srcset', 'srcset'] as $attr) {
            if (preg_match('/\b'.preg_quote($attr, '/').'\s*=\s*["\']([^"\']+)["\']/i', $tag, $mm)) {
                $first = trim(explode(',', $mm[1])[0]);
                $u = trim(explode(' ', $first)[0]);
                if ($u !== '' && preg_match('#^(https?://|/)#i', $u)) {
                    return $u;
                }
            }
        }

        return null;
    }
}
