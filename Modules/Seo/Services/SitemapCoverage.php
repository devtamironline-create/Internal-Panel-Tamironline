<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\Http;
use Modules\Seo\Models\SeoSetting;

/**
 * پوششِ Sitemap — مقایسهٔ دوطرفهٔ «sitemapِ زندهٔ سایت» با «محتوای واقعیِ پنل»:
 *
 *   missing: در پنل منتشر است ولی در sitemapِ زنده نیست (گوگل پیدایش نمی‌کند).
 *   stale:   در sitemapِ زنده هست ولی محتوایش در پنل نیست/منتشر نیست
 *            (کاندیدِ ۴۰۴ یا محتوای حذف‌شده — باید از sitemap خارج شود).
 *
 * sitemapِ زنده از {siteUrl}/sitemap.xml خوانده می‌شود (با پشتیبانی از
 * sitemapindex چندفایلی). «مورد انتظار» همان خروجیِ SitemapBuilder است که از
 * محتوای منتشرشدهٔ پنل ساخته می‌شود.
 */
class SitemapCoverage
{
    /** سقفِ فایل‌های فرزندِ sitemapindex + سقفِ عمقِ تو در تو. */
    private const MAX_CHILD_SITEMAPS = 50;

    private const MAX_DEPTH = 2;

    /** سقفِ URLهای ذخیره‌شده در هر لیستِ گزارش (جلوگیری از JSONِ عظیم). */
    public const MAX_STORED = 500;

    public function __construct(private readonly SitemapBuilder $builder) {}

    /**
     * تحلیلِ کامل: sitemapِ زنده + مورد انتظار + تفاضلِ دوطرفه.
     *
     * @return array{available: bool, live: list<string>, expected: list<string>, missing: list<string>, stale: list<string>}
     */
    public function analyze(): array
    {
        $expected = $this->expectedUrls();
        $live = $this->liveSitemapUrls();

        if ($live === null) {
            return ['available' => false, 'live' => [], 'expected' => $expected, 'missing' => [], 'stale' => []];
        }

        $liveByNorm = [];
        foreach ($live as $u) {
            $liveByNorm[self::normalize($u)] = $u;
        }
        $expectedByNorm = [];
        foreach ($expected as $u) {
            $expectedByNorm[self::normalize($u)] = $u;
        }

        $missing = array_values(array_diff_key($expectedByNorm, $liveByNorm));
        $stale = array_values(array_diff_key($liveByNorm, $expectedByNorm));

        return [
            'available' => true,
            'live' => array_values($liveByNorm),
            'expected' => array_values($expectedByNorm),
            'missing' => $missing,
            'stale' => $stale,
        ];
    }

    /**
     * URLهایی که «باید» در sitemap باشند — از محتوای منتشرشدهٔ پنل.
     *
     * @return list<string>
     */
    public function expectedUrls(): array
    {
        $urls = [];
        foreach ($this->builder->sitemapTypes() as $type) {
            foreach ($this->builder->urlsForType($type) as $u) {
                $urls[] = $u['loc'];
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * URLهای sitemapِ زندهٔ سایت (همانی که گوگل می‌خواند).
     * null یعنی sitemap قابلِ دریافت/پارس نبود.
     *
     * @return list<string>|null
     */
    public function liveSitemapUrls(): ?array
    {
        $urls = $this->fetchSitemap(SeoSetting::siteUrl().'/sitemap.xml', 0);

        return $urls === null ? null : array_values(array_unique($urls));
    }

    /**
     * @return list<string>|null
     */
    private function fetchSitemap(string $url, int $depth): ?array
    {
        try {
            $resp = Http::timeout(25)
                ->withHeaders(['User-Agent' => OnPageAuditor::userAgent()])
                ->get($url);
        } catch (\Throwable $e) {
            return null;
        }
        if (! $resp->ok()) {
            return null;
        }

        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($resp->body());
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            return null;
        }

        // sitemapindex → دریافتِ فایل‌های فرزند (فرزندِ خراب نادیده گرفته می‌شود).
        if (strtolower($xml->getName()) === 'sitemapindex') {
            if ($depth >= self::MAX_DEPTH) {
                return [];
            }
            $out = [];
            $count = 0;
            foreach ($xml->sitemap as $child) {
                if (++$count > self::MAX_CHILD_SITEMAPS) {
                    break;
                }
                $loc = trim((string) $child->loc);
                if ($loc === '') {
                    continue;
                }
                $childUrls = $this->fetchSitemap($loc, $depth + 1);
                if (is_array($childUrls)) {
                    $out = array_merge($out, $childUrls);
                }
            }

            return $out;
        }

        $out = [];
        foreach ($xml->url as $entry) {
            $loc = trim((string) $entry->loc);
            if ($loc !== '') {
                $out[] = $loc;
            }
        }

        return $out;
    }

    /**
     * نرمال‌سازی برای مقایسه: scheme/host کوچک، decode درصدی (slug فارسی)،
     * حذفِ اسلشِ انتهایی و fragment.
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return rtrim($url, '/');
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $path = rawurldecode($parts['path'] ?? '/');
        $path = rtrim($path, '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $scheme.'://'.$host.$path.$query;
    }
}
