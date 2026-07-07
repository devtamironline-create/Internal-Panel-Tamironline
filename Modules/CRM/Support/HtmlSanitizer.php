<?php

namespace Modules\CRM\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitizer سبک برای HTML تولیدشده توسط TinyMCE.
 *
 * استراتژی: allowlist محکم برای تگ‌ها و attributeها. هر چیز خارج از allowlist
 * حذف می‌شود (بدون escape) و عناصر همراه با محتوای متنی‌شان نگه‌داری می‌شوند.
 *
 * این کلاس وابستگی خارجی ندارد و فقط روی DOMDocument داخلی PHP تکیه دارد.
 */
final class HtmlSanitizer
{
    /**
     * تگ‌های مجاز با attributeهای مجازشان.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'p' => ['style', 'dir'],
        'br' => [],
        'hr' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'sub' => [],
        'sup' => [],
        'mark' => [],
        'h1' => ['style', 'dir', 'id'],
        'h2' => ['style', 'dir', 'id'],
        'h3' => ['style', 'dir', 'id'],
        'h4' => ['style', 'dir', 'id'],
        'h5' => ['style', 'dir', 'id'],
        'h6' => ['style', 'dir', 'id'],
        'ul' => ['style'],
        'ol' => ['start', 'style'],
        'li' => ['style'],
        'a' => ['href', 'title', 'target', 'rel', 'name', 'id'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'style', 'class'],
        'figure' => ['class', 'style'],
        'figcaption' => ['style'],
        'blockquote' => ['style', 'cite'],
        'pre' => ['class', 'style'],
        'code' => ['class'],
        'span' => ['style', 'class'],
        'div' => ['style', 'class'],
        'table' => ['border', 'cellspacing', 'cellpadding', 'style', 'class'],
        'caption' => [],
        'colgroup' => [],
        'col' => ['span', 'style'],
        'thead' => ['style'],
        'tbody' => ['style'],
        'tfoot' => ['style'],
        'tr' => ['style'],
        'td' => ['colspan', 'rowspan', 'style', 'scope'],
        'th' => ['colspan', 'rowspan', 'style', 'scope'],
        'iframe' => ['src', 'width', 'height', 'frameborder', 'allow', 'allowfullscreen', 'title'],
    ];

    /**
     * Style propertyهای مجاز (همه چیز دیگر از attribute style حذف می‌شود).
     */
    private const ALLOWED_STYLE_PROPS = [
        'text-align', 'direction', 'color', 'background-color',
        'font-size', 'font-family', 'font-weight', 'font-style',
        'text-decoration', 'line-height', 'letter-spacing',
        'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'border', 'border-color', 'border-width', 'border-style', 'border-radius',
        'width', 'height', 'max-width', 'min-width',
        'float', 'clear', 'vertical-align',
    ];

    /**
     * دامنه‌های مجاز برای iframe (embed ویدیو/نقشه). srcهای خارج از این لیست حذف می‌شوند.
     */
    private const IFRAME_ALLOWED_HOSTS = [
        'youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com',
        'youtu.be',
        'aparat.com', 'www.aparat.com',
        'vimeo.com', 'player.vimeo.com',
        'google.com', 'www.google.com', // برای نقشه
        'maps.google.com',
        'neshan.org', 'www.neshan.org',
    ];

    /**
     * Protocolهای مجاز برای href و src (جلوگیری از javascript:, data: غیرامن).
     */
    private const ALLOWED_HREF_PROTOCOLS = ['http', 'https', 'mailto', 'tel', '#', '/'];

    private const ALLOWED_IMG_PROTOCOLS = ['http', 'https', '/'];

    /**
     * تگ‌هایی که علاوه بر حذف خودشان، محتوای داخلی هم باید حذف شود
     * (نه unwrap) — script/style executable است یا formatting خراب می‌کند.
     */
    private const STRIP_WITH_CONTENT = ['script', 'style', 'object', 'embed', 'svg'];

    /**
     * پاک‌سازی یک رشته‌ی HTML. ورودی null/خالی بدون تغییر برمی‌گردد.
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $trimmed = trim($html);
        if ($trimmed === '') {
            return null;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        // UTF-8 wrapper برای حفظ encoding و جلوگیری از mojibake
        $wrapped = '<?xml encoding="UTF-8"?><div>'.$trimmed.'</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementsByTagName('div')->item(0);
        if (! $root instanceof DOMElement) {
            return null;
        }

        self::sanitizeNode($root);

        // خروجی innerHTML
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        $out = trim($out);

        return $out === '' ? null : $out;
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        // کپی کردن children در آرایه چون حذف در حین iteration باعث skip می‌شود
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                if (in_array($tag, self::STRIP_WITH_CONTENT, true)) {
                    // تگ خطرناک → حذف کامل با محتوا
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                if (! array_key_exists($tag, self::ALLOWED)) {
                    // تگ غیرمجاز → unwrap (محتوای متنی نگه‌داری می‌شود)
                    self::unwrap($child);

                    continue;
                }

                self::cleanAttributes($child, $tag);

                // iframe بدون src قابل قبول → حذف کامل
                if ($tag === 'iframe' && ! $child->hasAttribute('src')) {
                    $child->parentNode?->removeChild($child);

                    continue;
                }

                self::sanitizeNode($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode?->removeChild($child);
            }
            // متن‌ها (#text) دست‌نخورده می‌مانند
        }
    }

    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        if (! $parent) {
            return;
        }
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowedAttrs = self::ALLOWED[$tag];
        $toRemove = [];
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            if (! in_array($name, $allowedAttrs, true)) {
                $toRemove[] = $name;

                continue;
            }
            $value = $attr->nodeValue ?? '';

            if ($name === 'href') {
                if (! self::isSafeUrl($value, self::ALLOWED_HREF_PROTOCOLS)) {
                    $toRemove[] = $name;
                }
            } elseif ($name === 'src') {
                if ($tag === 'iframe') {
                    if (! self::isAllowedIframeSrc($value)) {
                        $toRemove[] = $name;
                    }
                } elseif (! self::isSafeUrl($value, self::ALLOWED_IMG_PROTOCOLS)) {
                    $toRemove[] = $name;
                }
            } elseif ($name === 'style') {
                $cleanStyle = self::filterStyle($value);
                if ($cleanStyle === '') {
                    $toRemove[] = $name;
                } else {
                    $el->setAttribute('style', $cleanStyle);
                }
            } elseif ($name === 'target' && $value === '_blank') {
                // اگر target=_blank است، حتماً rel=noopener هم اضافه کن
                $existingRel = $el->getAttribute('rel');
                if (! str_contains($existingRel, 'noopener')) {
                    $el->setAttribute('rel', trim($existingRel.' noopener noreferrer'));
                }
            }
        }
        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }
    }

    private static function isAllowedIframeSrc(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (! str_starts_with(strtolower($url), 'https://') && ! str_starts_with(strtolower($url), 'http://')) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }
        $host = strtolower($host);

        return in_array($host, self::IFRAME_ALLOWED_HOSTS, true);
    }

    private static function isSafeUrl(string $url, array $protocols): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        // مسیرهای داخلی و anchor
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        $lower = strtolower($url);
        foreach ($protocols as $p) {
            if ($p === '/' || $p === '#') {
                continue;
            }
            if (str_starts_with($lower, $p.':')) {
                return true;
            }
        }

        // URL نسبیِ بدونِ scheme (مثلِ storage/x.jpg یا ../media/1) امن است —
        // خطر فقط در schemeهای اجرایی (javascript:, data:, …) است. TinyMCE
        // پیش‌تر آدرس‌های هم‌دامنه را نسبی می‌کرد و src تصاویر این‌جا حذف می‌شد.
        $firstColon = strpos($url, ':');
        if ($firstColon === false) {
            return true;
        }
        $delimiter = strcspn($url, '/?#');

        return $firstColon > $delimiter;
    }

    private static function filterStyle(string $style): string
    {
        $out = [];
        foreach (explode(';', $style) as $decl) {
            $decl = trim($decl);
            if ($decl === '' || ! str_contains($decl, ':')) {
                continue;
            }
            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (! in_array($prop, self::ALLOWED_STYLE_PROPS, true)) {
                continue;
            }
            // مقادیر مشکوک (url(), expression()) را حذف کن
            if (preg_match('/url\s*\(|expression\s*\(|javascript:/i', $val)) {
                continue;
            }
            $out[] = $prop.': '.$val;
        }

        return implode('; ', $out);
    }
}
