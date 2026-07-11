<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Seo\Models\SeoAuditRun;

/**
 * سلامتِ لینک‌سازیِ داخلی از گرافِ لینکِ آخرین کرال: صفحاتِ Orphan (بدونِ هیچ
 * لینکِ داخلیِ ورودی) و صفحاتِ کم‌لینک.
 */
class InternalLinkController extends Controller
{
    private function latestRun(): ?SeoAuditRun
    {
        return SeoAuditRun::query()
            ->where('status', 'done')
            ->whereIn('id', fn ($q) => $q->from('seo_links')->select('run_id')->distinct())
            ->latest('id')
            ->first();
    }

    public function index()
    {
        $run = $this->latestRun();

        $orphans = collect();
        $stats = ['pages' => 0, 'linked' => 0, 'orphan' => 0];

        if ($run) {
            // مجموعهٔ مسیرهایی که حداقل یک لینکِ داخلیِ ورودی دارند.
            $inbound = DB::table('seo_links')
                ->where('run_id', $run->id)
                ->where('is_internal', true)
                ->whereNotNull('target_path')
                ->distinct()
                ->pluck('target_path')
                ->flip();

            // صفحاتِ قابل‌ایندکسِ کرال‌شده (۲۰۰، بدونِ noindex).
            $pages = DB::table('seo_audits')
                ->where('run_id', $run->id)
                ->where('status_code', '>=', 200)
                ->where('status_code', '<', 300)
                ->where('is_noindex', false)
                ->get(['url', 'internal_links']);

            $stats['pages'] = $pages->count();
            foreach ($pages as $p) {
                $path = $this->pathOf((string) $p->url);
                // صفحهٔ اصلی هرگز Orphan محسوب نمی‌شود.
                if ($path === '/' || isset($inbound[$path])) {
                    $stats['linked']++;

                    continue;
                }
                $orphans->push(['url' => $p->url, 'path' => $path, 'outgoing' => (int) $p->internal_links]);
            }
            $stats['orphan'] = $orphans->count();
            $orphans = $orphans->sortBy('path')->values();
        }

        return view('seo::internal-links.index', compact('run', 'orphans', 'stats'));
    }

    private function pathOf(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $path = rtrim((string) $path, '/');

        return $path === '' ? '/' : $path;
    }
}
