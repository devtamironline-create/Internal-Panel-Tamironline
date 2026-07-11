<?php

namespace Modules\Seo\Services;

use Illuminate\Support\Facades\DB;

/**
 * ذخیره‌سازیِ گرافِ لینک در seo_links. جدا از LinkExtractor (پارسِ خالص) تا
 * لایهٔ DB و لایهٔ تحلیل مستقل بمانند. درجِ گروهی برای کارایی در کرالِ بزرگ.
 */
class LinkGraphStore
{
    /**
     * لینک‌های یک صفحه را (خروجیِ LinkExtractor) برای یک run ذخیره می‌کند.
     *
     * @param  list<array<string, mixed>>  $links
     */
    public function store(int $runId, string $sourceUrl, array $links): void
    {
        if ($links === []) {
            return;
        }

        $now = now();
        $source = mb_substr($sourceUrl, 0, 700);
        $rows = [];
        foreach ($links as $l) {
            $rows[] = [
                'run_id' => $runId,
                'source_url' => $source,
                'target_url' => (string) ($l['target_url'] ?? ''),
                'target_path' => $l['target_path'] ?? null,
                'anchor' => $l['anchor'] ?? null,
                'rel' => $l['rel'] ?? null,
                'is_internal' => (bool) ($l['is_internal'] ?? true),
                'selector' => $l['selector'] ?? null,
                'xpath' => $l['xpath'] ?? null,
                'context_html' => $l['context_html'] ?? null,
                'position' => (int) ($l['position'] ?? 0),
                'created_at' => $now,
            ];
        }

        // درجِ تکه‌ای — بعضی صفحات صدها لینک دارند و placeholderهای MySQL سقف دارند.
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('seo_links')->insert($chunk);
        }
    }

    /**
     * نگه‌داشتنِ گرافِ لینک فقط برای چند run اخیر (جلوگیری از رشدِ بی‌حد).
     * seo_links/seo_link_targets تنها برای گزارشِ «آخرین کرال» لازم‌اند.
     */
    public function pruneOldRuns(int $keep = 3): void
    {
        $keepIds = DB::table('seo_audit_runs')->orderByDesc('id')->limit($keep)->pluck('id')->all();
        if ($keepIds === []) {
            return;
        }
        DB::table('seo_links')->whereNotIn('run_id', $keepIds)->delete();
        DB::table('seo_link_targets')->whereNotIn('run_id', $keepIds)->delete();
    }
}
