<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\Http;

/**
 * آدیت On-page یک URL: واکشی HTTP و استخراج سیگنال‌های سئو از HTML.
 * خروجی خام است؛ تحلیل/امتیاز در AuditAnalyzer انجام می‌شود.
 */
class OnPageAuditor
{
    /**
     * User-Agentِ کرالر — قابلِ تغییر از تنظیماتِ سئو (کلیدِ crawler_user_agent).
     * پیش‌فرض شبیه‌سازیِ رفتارِ Googlebot با شناسهٔ شفافِ خودمان است؛ اگر خواستید
     * دقیقاً UA گوگل باشد، مقدارِ تنظیم را عوض کنید (مراقبِ WAF/کلودفلر باشید —
     * Googlebotِ جعلی ممکن است challenge بخورد).
     */
    public static function userAgent(): string
    {
        $custom = trim((string) \Modules\Seo\Models\SeoSetting::get('crawler_user_agent', ''));

        return $custom !== ''
            ? $custom
            : 'Mozilla/5.0 (compatible; TamironlineSeoBot/1.0; like Googlebot; +https://tamironline.com)';
    }

    /**
     * @return array<string, mixed>
     */
    public function audit(string $url): array
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
        $start = microtime(true);

        try {
            $resp = Http::timeout(20)
                ->withHeaders(['User-Agent' => self::userAgent()])
                ->withOptions(['allow_redirects' => ['track_redirects' => true]])
                ->get($url);
        } catch (\Throwable $e) {
            return [
                'url' => $url,
                'status_code' => 0,
                'error' => $e->getMessage(),
                'response_time_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        }

        $ms = (int) round((microtime(true) - $start) * 1000);
        $html = (string) $resp->body();
        // سقفِ اندازه‌ی HTML پردازشی — سیگنال‌های سئو در head و ابتدای body هستند؛
        // صفحه‌های چندمگابایتی در کرالِ ۱۰۰۰صفحه‌ای حافظه را تمام می‌کردند (OOM).
        if (strlen($html) > 2_000_000) {
            $html = substr($html, 0, 2_000_000);
        }
        $history = (string) $resp->header('X-Guzzle-Redirect-History');
        $status = $resp->status();
        unset($resp); // آزادسازیِ بدنه‌ی پاسخ پیش از پردازش‌های regex
        $hops = $history !== '' ? array_values(array_filter(explode(', ', $history))) : [];
        $redirectCount = count($hops);
        $finalUrl = $hops !== [] ? end($hops) : $url;

        $robots = $this->meta($html, 'robots');
        [$internal, $external] = $this->links($html, $host);
        [$imgTotal, $imgNoAlt] = $this->images($html);
        $title = $this->title($html);
        $desc = $this->meta($html, 'description');

        return [
            'url' => $url,
            'status_code' => $status,
            'final_url' => $finalUrl,
            'redirect_count' => $redirectCount,
            'title' => $title,
            'title_length' => mb_strlen((string) $title),
            'meta_description' => $desc,
            'description_length' => mb_strlen((string) $desc),
            'h1_count' => preg_match_all('/<h1[\s>]/i', $html),
            'canonical' => $this->canonical($html),
            'robots' => $robots,
            'is_noindex' => $robots !== null && str_contains(strtolower($robots), 'noindex'),
            'og_present' => (bool) preg_match('/property=["\']og:/i', $html),
            'twitter_present' => (bool) preg_match('/name=["\']twitter:card/i', $html),
            'jsonld_present' => (bool) preg_match('/application\/ld\+json/i', $html),
            'images_total' => $imgTotal,
            'images_without_alt' => $imgNoAlt,
            'internal_links' => $internal,
            'external_links' => $external,
            'word_count' => $this->wordCount($html),
            'response_time_ms' => $ms,
        ];
    }

    private function title(string $html): ?string
    {
        return preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)
            ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : null;
    }

    private function canonical(string $html): ?string
    {
        if (preg_match_all('/<link\b[^>]*>/i', $html, $m)) {
            foreach ($m[0] as $tag) {
                $attr = $this->attrs($tag);
                if (($attr['rel'] ?? '') === 'canonical') {
                    return $attr['href'] ?? null;
                }
            }
        }

        return null;
    }

    private function meta(string $html, string $name): ?string
    {
        if (preg_match_all('/<meta\b[^>]*>/i', $html, $m)) {
            foreach ($m[0] as $tag) {
                $attr = $this->attrs($tag);
                if (strtolower($attr['name'] ?? '') === $name) {
                    return $attr['content'] ?? null;
                }
            }
        }

        return null;
    }

    /** @return array{0:int,1:int} [internal, external] */
    private function links(string $html, string $host): array
    {
        $internal = 0;
        $external = 0;
        if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            foreach ($m[1] as $href) {
                $href = trim($href);
                if ($href === '' || $href[0] === '#' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
                    continue;
                }
                $h = parse_url($href, PHP_URL_HOST);
                if ($h === null || $h === '' || ($host !== '' && $h === $host)) {
                    $internal++;
                } else {
                    $external++;
                }
            }
        }

        return [$internal, $external];
    }

    /** @return array{0:int,1:int} [total, withoutAlt] */
    private function images(string $html): array
    {
        $total = 0;
        $noAlt = 0;
        if (preg_match_all('/<img\b[^>]*>/i', $html, $m)) {
            foreach ($m[0] as $tag) {
                $total++;
                $attr = $this->attrs($tag);
                if (trim($attr['alt'] ?? '') === '') {
                    $noAlt++;
                }
            }
        }

        return [$total, $noAlt];
    }

    private function wordCount(string $html): int
    {
        // فقط بدنه را در نظر بگیر و اسکریپت/استایل را حذف کن.
        $body = preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $m) ? $m[1] : $html;
        $body = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $body);
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($body)));

        return $text === '' ? 0 : count(explode(' ', $text));
    }

    /** @return array<string,string> */
    private function attrs(string $tag): array
    {
        $out = [];
        if (preg_match_all('/([a-zA-Z\-:]+)\s*=\s*["\']([^"\']*)["\']/', $tag, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                $out[strtolower($pair[1])] = $pair[2];
            }
        }

        return $out;
    }
}
