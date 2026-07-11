<?php

namespace Modules\Seo\Services;

/**
 * استخراجِ گرافِ لینک از HTMLِ یک صفحه با DOMDocument (بدونِ وابستگیِ جدید).
 *
 * برخلافِ OnPageAuditor که فقط لینک‌ها را می‌شمارد، این‌جا هر <a href> با
 * anchor، rel، داخلی/خارجی‌بودن و محلِ تقریبیِ DOM (CSS selector + XPath)
 * برگردانده می‌شود تا بعداً «لینکِ خراب در کدام صفحه و کجای صفحه» پاسخ داده شود.
 */
class LinkExtractor
{
    /**
     * @return list<array{target_url:string,target_path:?string,anchor:?string,rel:?string,is_internal:bool,selector:?string,xpath:?string,context_html:?string,position:int}>
     */
    public function extract(string $html, string $baseUrl, ?string $siteHost = null): array
    {
        if (trim($html) === '') {
            return [];
        }

        $siteHost ??= self::siteHost();

        $dom = new \DOMDocument;
        $prev = libxml_use_internal_errors(true);
        // UTF-8 را حفظ کن (بدونِ این prefix، DOMDocument برچسبِ charset را غلط می‌زند).
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xp = new \DOMXPath($dom);
        $anchors = $xp->query('//a[@href]');
        if ($anchors === false) {
            return [];
        }

        $out = [];
        $position = 0;
        foreach ($anchors as $a) {
            /** @var \DOMElement $a */
            $href = trim($a->getAttribute('href'));
            $abs = $this->resolve($baseUrl, $href);
            if ($abs === null) {
                continue; // mailto/tel/js/fragment/data → لینکِ ناوبری نیست
            }
            $position++;

            $host = parse_url($abs, PHP_URL_HOST);
            $isInternal = $host === null || $host === '' || ($siteHost !== null && strtolower((string) $host) === $siteHost);

            $out[] = [
                'target_url' => mb_substr($abs, 0, 700),
                'target_path' => $isInternal ? $this->pathOf($abs) : null,
                'anchor' => $this->anchorText($a),
                'rel' => ($rel = trim($a->getAttribute('rel'))) !== '' ? mb_substr($rel, 0, 120) : null,
                'is_internal' => $isInternal,
                'selector' => mb_substr($this->cssSelector($a), 0, 500),
                'xpath' => mb_substr((string) $a->getNodePath(), 0, 500),
                'context_html' => $this->context($dom, $a),
                'position' => $position,
            ];
        }

        return $out;
    }

    /** hostِ دامنهٔ عمومیِ سایت (lowercase) یا null. */
    public static function siteHost(): ?string
    {
        $host = parse_url(\Modules\Seo\Models\SeoSetting::siteUrl(), PHP_URL_HOST);

        return $host ? strtolower($host) : null;
    }

    /** متنِ لنگر (بدونِ تگ‌های داخلی، فشرده و بریده). */
    private function anchorText(\DOMElement $a): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $a->textContent));
        if ($text === '') {
            // لینکِ صرفاً تصویری → از alt یا aria-label استفاده کن
            $img = $a->getElementsByTagName('img')->item(0);
            if ($img instanceof \DOMElement) {
                $text = trim($img->getAttribute('alt'));
            }
            if ($text === '') {
                $text = trim($a->getAttribute('aria-label'));
            }
        }

        return $text === '' ? null : mb_substr($text, 0, 300);
    }

    /** HTMLِ خودِ لینک (بریده) به‌عنوانِ زمینهٔ محل. */
    private function context(\DOMDocument $dom, \DOMElement $a): ?string
    {
        $html = $dom->saveHTML($a);
        if (! is_string($html) || $html === '') {
            return null;
        }

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $html)), 0, 500);
    }

    /**
     * انتخاب‌گرِ تقریبیِ CSS از زنجیرهٔ اجدادِ عنصر (tag + nth-of-type).
     * دقیق نیست ولی برای پیداکردنِ لینک در صفحه در پنل کافی است.
     */
    private function cssSelector(\DOMElement $el): string
    {
        $parts = [];
        $node = $el;
        $depth = 0;
        while ($node instanceof \DOMElement && $depth < 6) {
            $tag = strtolower($node->nodeName);
            if ($tag === 'html' || $tag === 'body') {
                array_unshift($parts, $tag);
                break;
            }

            $id = trim($node->getAttribute('id'));
            if ($id !== '') {
                array_unshift($parts, $tag.'#'.$id);
                break; // id یکتاست — زنجیره را همین‌جا ببند
            }

            $seg = $tag;
            $nth = $this->nthOfType($node);
            if ($nth > 0) {
                $seg .= ':nth-of-type('.$nth.')';
            }
            array_unshift($parts, $seg);

            $node = $node->parentNode instanceof \DOMElement ? $node->parentNode : null;
            $depth++;
        }

        return implode(' > ', $parts);
    }

    /** ایندکسِ nth-of-type یک عنصر بینِ هم‌نوع‌های هم‌سطح (۱-پایه). */
    private function nthOfType(\DOMElement $el): int
    {
        $i = 0;
        $sib = $el;
        while ($sib !== null) {
            if ($sib instanceof \DOMElement && $sib->nodeName === $el->nodeName) {
                $i++;
            }
            $sib = $sib->previousSibling;
        }

        return $i;
    }

    /**
     * تبدیلِ href به URLِ مطلقِ نرمال. null یعنی «لینکِ ناوبری نیست»
     * (mailto/tel/javascript/data/فقط-fragment).
     */
    private function resolve(string $base, string $href): ?string
    {
        if ($href === '') {
            return null;
        }
        $lower = strtolower($href);
        foreach (['mailto:', 'tel:', 'javascript:', 'data:', 'sms:', 'whatsapp:'] as $skip) {
            if (str_starts_with($lower, $skip)) {
                return null;
            }
        }
        if ($href[0] === '#') {
            return null;
        }

        // fragment را حذف کن (روی هویتِ صفحه اثری ندارد).
        if (($hash = strpos($href, '#')) !== false) {
            $href = substr($href, 0, $hash);
            if ($href === '') {
                return null;
            }
        }

        // مطلق با scheme
        if (preg_match('#^https?://#i', $href)) {
            return $this->stripDefaultPort($href);
        }
        // پروتکل-نسبی //host/path
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

            return $this->stripDefaultPort($scheme.':'.$href);
        }

        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($base, PHP_URL_HOST);
        if ($host === null || $host === '') {
            return null;
        }
        $origin = $scheme.'://'.$host;
        $port = parse_url($base, PHP_URL_PORT);
        if ($port !== null && ! in_array((int) $port, [80, 443], true)) {
            $origin .= ':'.$port;
        }

        // ریشه-نسبی /path
        if ($href[0] === '/') {
            return $origin.$this->normalizeDots($href);
        }

        // نسبی به مسیرِ فعلی
        $basePath = parse_url($base, PHP_URL_PATH) ?: '/';
        $dir = rtrim(substr($basePath, 0, strrpos($basePath, '/') + 1), '');
        if ($dir === '') {
            $dir = '/';
        }

        return $origin.$this->normalizeDots($dir.$href);
    }

    /** حذفِ ./ و ../ از یک مسیر. */
    private function normalizeDots(string $path): string
    {
        $query = '';
        if (($q = strpos($path, '?')) !== false) {
            $query = substr($path, $q);
            $path = substr($path, 0, $q);
        }
        $segments = explode('/', $path);
        $stack = [];
        foreach ($segments as $seg) {
            if ($seg === '.' || $seg === '') {
                continue;
            }
            if ($seg === '..') {
                array_pop($stack);

                continue;
            }
            $stack[] = $seg;
        }
        $out = '/'.implode('/', $stack);
        // trailing slash اگر مسیرِ اصلی داشت
        if (str_ends_with($path, '/') && $out !== '/') {
            $out .= '/';
        }

        return $out.$query;
    }

    private function stripDefaultPort(string $url): string
    {
        return preg_replace('#^(https?://[^/:]+):(?:80|443)(/|$)#i', '$1$2', $url) ?? $url;
    }

    /** مسیرِ نرمال (بدونِ trailing slash، ریشه = /). */
    private function pathOf(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === null || $path === false || $path === '') {
            return '/';
        }
        $path = rtrim((string) $path, '/');

        return $path === '' ? '/' : $path;
    }
}
