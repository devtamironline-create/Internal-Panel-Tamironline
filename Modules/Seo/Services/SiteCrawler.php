<?php

namespace Modules\Seo\Services;

use Modules\Seo\Models\SeoAudit;
use Modules\Seo\Models\SeoAuditRun;

/**
 * هماهنگ‌کنندهٔ یک کرال کامل: URLها را از sitemap جمع می‌کند، هر کدام را
 * آدیت/تحلیل می‌کند، نتیجه را ذخیره و در پایان هشدار می‌فرستد.
 */
class SiteCrawler
{
    public function __construct(
        private readonly SitemapBuilder $sitemap,
        private readonly OnPageAuditor $auditor,
        private readonly AuditAnalyzer $analyzer,
        private readonly PageSpeedClient $pageSpeed,
        private readonly AlertNotifier $alerts,
        private readonly SitemapCoverage $coverage,
    ) {}

    /**
     * @param  callable(string,int,int):void|null  $progress  (url, index, total)
     */
    public function run(string $source = 'manual', ?int $limit = null, bool $cwv = false, ?callable $progress = null): SeoAuditRun
    {
        // کرالِ ۱۰۰۰صفحه‌ای با سقفِ پیش‌فرضِ 128M سرور OOM می‌شود — سقف را بالا
        // می‌بریم (همان الگوی ImageProcessor).
        if (self::memoryLimitBytes() < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }

        $run = SeoAuditRun::create([
            'status' => 'running',
            'source' => $source,
            'started_at' => now(),
        ]);

        try {
            // پوششِ sitemap: مقایسهٔ دوطرفهٔ sitemapِ زندهٔ سایت با محتوای پنل.
            // اگر sitemapِ زنده در دسترس بود، کرال از روی «زنده + جاافتاده‌ها»
            // انجام می‌شود — یعنی همان چیزی که گوگل واقعاً می‌بیند + آنچه باید ببیند.
            $coverage = null;
            try {
                $coverage = $this->coverage->analyze();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('seo.coverage_failed', ['error' => $e->getMessage()]);
            }
            $coverageAvailable = (bool) ($coverage['available'] ?? false);

            if ($coverageAvailable) {
                $urls = array_values(array_unique(array_merge($coverage['live'], $coverage['missing'])));
                if ($limit) {
                    $urls = array_slice($urls, 0, $limit);
                }
            } else {
                $urls = $this->collectUrls($limit);
            }

            $run->update([
                'total_urls' => count($urls),
                'missing_in_sitemap_count' => $coverageAvailable ? count($coverage['missing']) : 0,
                'stale_in_sitemap_count' => $coverageAvailable ? count($coverage['stale']) : 0,
                'coverage' => $coverageAvailable ? [
                    'live_count' => count($coverage['live']),
                    'expected_count' => count($coverage['expected']),
                    'missing' => array_slice($coverage['missing'], 0, SitemapCoverage::MAX_STORED),
                    'stale' => array_slice($coverage['stale'], 0, SitemapCoverage::MAX_STORED),
                ] : null,
            ]);

            $counts = ['good' => 0, 'notice' => 0, 'warning' => 0, 'critical' => 0];
            $scoreSum = 0;
            $total = count($urls);

            foreach ($urls as $i => $url) {
                $raw = $this->auditor->audit($url);
                $analysis = $this->analyzer->analyze($raw);

                SeoAudit::create(array_merge($this->columns($raw), [
                    'run_id' => $run->id,
                    'cwv' => $cwv ? $this->pageSpeed->measure($url) : null,
                    'issues' => $analysis['issues'],
                    'severity' => $analysis['severity'],
                    'score' => $analysis['score'],
                    'crawled_at' => now(),
                ]));

                $counts[$analysis['severity']]++;
                $scoreSum += $analysis['score'];

                if ($progress) {
                    $progress($url, $i + 1, $total);
                }

                // آزادسازیِ دوره‌ایِ چرخه‌های مرجعِ PHP — بدونِ آن، حافظه در
                // کرال‌های طولانی به‌تدریج انباشته و OOM می‌شود.
                unset($raw, $analysis);
                if (($i + 1) % 25 === 0) {
                    gc_collect_cycles();
                }
            }

            $run->update([
                'status' => 'done',
                'finished_at' => now(),
                'ok_count' => $counts['good'],
                'notice_count' => $counts['notice'],
                'warning_count' => $counts['warning'],
                'critical_count' => $counts['critical'],
                'avg_score' => $total > 0 ? (int) round($scoreSum / $total) : 0,
            ]);

            // پاس پس از run: مشکلات canonical میان‌ردیفی (وابسته به سایر صفحات همین
            // کرال) را محاسبه و شمارش‌های run را بازمحاسبه می‌کند.
            $this->analyzer->analyzeRun($run->refresh());

            $this->alerts->afterRun($run->refresh());
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'finished_at' => now()]);
            throw $e;
        }

        return $run;
    }

    /** @return list<string> */
    public function collectUrls(?int $limit = null): array
    {
        $urls = [];
        foreach ($this->sitemap->sitemapTypes() as $type) {
            foreach ($this->sitemap->urlsForType($type) as $u) {
                $urls[] = $u['loc'];
            }
        }
        $urls = array_values(array_unique($urls));

        return $limit ? array_slice($urls, 0, $limit) : $urls;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function columns(array $raw): array
    {
        return [
            'url' => mb_substr((string) ($raw['url'] ?? ''), 0, 700),
            'status_code' => (int) ($raw['status_code'] ?? 0),
            'final_url' => $raw['final_url'] ? mb_substr((string) $raw['final_url'], 0, 700) : null,
            'redirect_count' => (int) ($raw['redirect_count'] ?? 0),
            'title' => $raw['title'] !== null ? mb_substr((string) $raw['title'], 0, 512) : null,
            'title_length' => (int) ($raw['title_length'] ?? 0),
            'meta_description' => $raw['meta_description'] ?? null,
            'description_length' => (int) ($raw['description_length'] ?? 0),
            'h1_count' => (int) ($raw['h1_count'] ?? 0),
            'canonical' => $raw['canonical'] ? mb_substr((string) $raw['canonical'], 0, 700) : null,
            'is_noindex' => (bool) ($raw['is_noindex'] ?? false),
            'robots' => $raw['robots'] !== null ? mb_substr((string) $raw['robots'], 0, 191) : null,
            'og_present' => (bool) ($raw['og_present'] ?? false),
            'twitter_present' => (bool) ($raw['twitter_present'] ?? false),
            'jsonld_present' => (bool) ($raw['jsonld_present'] ?? false),
            'images_total' => (int) ($raw['images_total'] ?? 0),
            'images_without_alt' => (int) ($raw['images_without_alt'] ?? 0),
            'internal_links' => (int) ($raw['internal_links'] ?? 0),
            'external_links' => (int) ($raw['external_links'] ?? 0),
            'word_count' => (int) ($raw['word_count'] ?? 0),
            'response_time_ms' => (int) ($raw['response_time_ms'] ?? 0),
        ];
    }

    /** memory_limit فعلی به بایت (−1 یعنی نامحدود → عددِ بزرگ). */
    private static function memoryLimitBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return PHP_INT_MAX;
        }
        $unit = strtolower($raw[strlen($raw) - 1]);
        $num = (int) $raw;

        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => (int) $raw,
        };
    }
}
