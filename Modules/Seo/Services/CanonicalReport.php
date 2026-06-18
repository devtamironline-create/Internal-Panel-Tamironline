<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Collection;
use Modules\Seo\Models\SeoAudit;
use Modules\Seo\Models\SeoAuditRun;

/**
 * گزارش‌های canonical بر پایهٔ آخرین run تمام‌شده: شمارش مشکلات canonical و
 * گروه‌های canonical تکراری (چند صفحه با یک canonical مشترک).
 */
class CanonicalReport
{
    /** آخرین run با وضعیت done (یا null اگر هیچ‌کدام). */
    public function latestRun(): ?SeoAuditRun
    {
        return SeoAuditRun::query()
            ->where('status', 'done')
            ->latest('id')
            ->first();
    }

    /**
     * شمارش هر نوع مشکل canonical در یک run. روی issues(json) ردیف‌ها کار می‌کند.
     *
     * @return array<string,int>  code => count
     */
    public function issueCounts(?SeoAuditRun $run): array
    {
        $counts = array_fill_keys(AuditAnalyzer::CANONICAL_CODES, 0);
        if ($run === null) {
            return $counts;
        }

        $run->audits()->select('issues')->cursor()->each(function ($a) use (&$counts) {
            foreach (($a->issues ?? []) as $iss) {
                $code = $iss['code'] ?? '';
                if (array_key_exists($code, $counts)) {
                    $counts[$code]++;
                }
            }
        });

        return $counts;
    }

    /**
     * گروه‌های canonical تکراری: canonicalهایی که بیش از یک صفحهٔ متمایز به آن‌ها
     * اشاره می‌کنند. یک GROUP BY برای یافتن canonicalهای تکراری + یک fetch بعدی برای
     * فهرست URLها.
     *
     * @return Collection<int,array{canonical:string,count:int,urls:list<string>}>
     */
    public function duplicateGroups(?SeoAuditRun $run): Collection
    {
        if ($run === null) {
            return collect();
        }

        // مرحلهٔ ۱: canonicalهایی که بیش از یک URL متمایز دارند (GROUP BY).
        $dupes = SeoAudit::query()
            ->where('run_id', $run->id)
            ->whereNotNull('canonical')
            ->where('canonical', '!=', '')
            ->groupBy('canonical')
            ->havingRaw('COUNT(DISTINCT url) > 1')
            ->orderByRaw('COUNT(DISTINCT url) DESC')
            ->pluck('canonical');

        if ($dupes->isEmpty()) {
            return collect();
        }

        // مرحلهٔ ۲: URLهای هر canonical تکراری را یک‌جا واکشی و گروه‌بندی می‌کنیم.
        $rows = SeoAudit::query()
            ->where('run_id', $run->id)
            ->whereIn('canonical', $dupes->all())
            ->orderBy('canonical')
            ->orderBy('url')
            ->get(['canonical', 'url']);

        return $rows
            ->groupBy('canonical')
            ->map(function ($group, $canonical) {
                $urls = $group->pluck('url')->unique()->values();

                return [
                    'canonical' => (string) $canonical,
                    'count' => $urls->count(),
                    'urls' => $urls->all(),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }
}
