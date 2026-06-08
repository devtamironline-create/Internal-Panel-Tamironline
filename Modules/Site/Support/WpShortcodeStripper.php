<?php

namespace Modules\Site\Support;

/**
 * تبدیل/حذف shortcodes وردپرس قبل از عبور از HtmlSanitizer.
 *
 * HtmlSanitizer shortcode را به‌عنوان متن plain می‌بیند و آن را نگه می‌دارد —
 * که خروجی زشتی روی صفحه می‌سازد. این کلاس قبل از sanitize اعمال می‌شود.
 *
 * استراتژی:
 *   - [caption ...]X[/caption]              → X  (متن داخلی نگه داشته می‌شود)
 *   - [embed]URL[/embed]                    → <iframe src="URL"> اگر دامنه‌ی مجاز
 *   - [video src="..."]                     → <video src="...">
 *   - [audio src="..."]                     → حذف (audio پشتیبانی نمی‌شود در sanitizer)
 *   - [gallery], [su_*], [contact-form], …  → حذف کامل
 *   - هر shortcode ناشناخته                   → حذف کامل
 */
final class WpShortcodeStripper
{
    /** دامنه‌های مجاز برای [embed]URL[/embed]. */
    private const EMBED_ALLOWED_HOSTS = [
        'youtube.com', 'www.youtube.com', 'youtu.be',
        'aparat.com', 'www.aparat.com',
        'vimeo.com', 'player.vimeo.com',
    ];

    public static function strip(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        // ۱) [caption ...]MEDIA + متن[/caption] — اغلب فقط <img> + نوشته‌ی زیر تصویر.
        //    داخل را نگه می‌داریم چون <img> در sanitizer مجاز است.
        $html = preg_replace_callback(
            '/\[caption[^\]]*\](.*?)\[\/caption\]/is',
            fn ($m) => $m[1] ?? '',
            $html
        ) ?? $html;

        // ۲) [embed]URL[/embed] → iframe اگر دامنه مجاز است
        $html = preg_replace_callback(
            '/\[embed[^\]]*\](.*?)\[\/embed\]/is',
            function ($m) {
                $url = trim($m[1] ?? '');
                $host = parse_url($url, PHP_URL_HOST);
                if (! $host || ! in_array(strtolower($host), self::EMBED_ALLOWED_HOSTS, true)) {
                    return ''; // حذف
                }

                return '<iframe src="'.htmlspecialchars($url, ENT_QUOTES).'" frameborder="0" allowfullscreen></iframe>';
            },
            $html
        ) ?? $html;

        // ۳) [video src="..."] خودبسته → <video src=...>
        $html = preg_replace_callback(
            '/\[video[^\]]*\bsrc=["\']([^"\']+)["\'][^\]]*\](?:(.*?)\[\/video\])?/is',
            function ($m) {
                $src = trim($m[1] ?? '');
                if ($src === '') {
                    return '';
                }

                return '<video src="'.htmlspecialchars($src, ENT_QUOTES).'" controls></video>';
            },
            $html
        ) ?? $html;

        // ۴) باقی shortcodes — حذف کامل (هم خودبسته هم باز/بسته)
        // closing/opening pair:  [foo ...]...[/foo]
        $html = preg_replace('/\[([a-zA-Z][\w-]*)[^\]]*\].*?\[\/\1\]/is', '', $html) ?? $html;

        // self-closing:  [foo ...]
        $html = preg_replace('/\[[a-zA-Z][\w-]*[^\]]*\]/s', '', $html) ?? $html;

        return $html;
    }
}
