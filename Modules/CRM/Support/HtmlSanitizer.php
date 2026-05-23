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
        'p' => ['style'],
        'br' => [],
        'hr' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'h1' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
        'h4' => ['style'],
        'h5' => ['style'],
        'h6' => ['style'],
        'ul' => [],
        'ol' => ['start'],
        'li' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'blockquote' => [],
        'pre' => [],
        'code' => [],
        'span' => ['style'],
        'div' => ['style'],
        'table' => ['border', 'cellspacing', 'cellpadding'],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'td' => ['colspan', 'rowspan', 'style'],
        'th' => ['colspan', 'rowspan', 'style'],
    ];

    /**
     * Style propertyهای مجاز (همه چیز دیگر از attribute style حذف می‌شود).
     */
    private const ALLOWED_STYLE_PROPS = ['text-align', 'direction'];

    /**
     * Protocolهای مجاز برای href و src (جلوگیری از javascript:, data: غیرامن).
     */
    private const ALLOWED_HREF_PROTOCOLS = ['http', 'https', 'mailto', 'tel', '#', '/'];

    private const ALLOWED_IMG_PROTOCOLS = ['http', 'https', '/'];

    /**
     * تگ‌هایی که علاوه بر حذف خودشان، محتوای داخلی هم باید حذف شود
     * (نه unwrap) — script/style executable است یا formatting خراب می‌کند.
     */
    private const STRIP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'svg'];

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
                if (! self::isSafeUrl($value, self::ALLOWED_IMG_PROTOCOLS)) {
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

        return false;
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
