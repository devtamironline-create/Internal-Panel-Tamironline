<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoAuditRun;
use Modules\Seo\Models\SeoLink;
use Modules\Seo\Models\SeoLinkTarget;

/**
 * لینک‌های خراب (و ریدایرکت‌شده) از گرافِ لینکِ آخرین کرال، همراهِ محلِ دقیق
 * (صفحهٔ ارجاع‌دهنده + anchor + selector). داده از seo_links ⨝ seo_link_targets.
 */
class BrokenLinkController extends Controller
{
    /** آخرین کرالِ تمام‌شده که گرافِ لینک دارد. */
    private function latestRun(): ?SeoAuditRun
    {
        return SeoAuditRun::query()
            ->where('status', 'done')
            ->whereIn('id', fn ($q) => $q->from('seo_link_targets')->select('run_id')->distinct())
            ->latest('id')
            ->first();
    }

    public function index(Request $request)
    {
        $run = $this->latestRun();
        $type = in_array($request->query('type'), ['broken', 'redirect', 'ignored'], true)
            ? $request->query('type') : 'broken';

        $targets = null;
        $counts = ['broken' => 0, 'redirect' => 0, 'ignored' => 0];

        if ($run) {
            $base = SeoLinkTarget::query()->where('run_id', $run->id);
            $counts = [
                'broken' => (clone $base)->where('is_broken', true)->whereNull('ignored_at')->count(),
                'redirect' => (clone $base)->where('is_redirect', true)->where('is_broken', false)->whereNull('ignored_at')->count(),
                'ignored' => (clone $base)->whereNotNull('ignored_at')->count(),
            ];

            $query = SeoLinkTarget::query()
                ->where('run_id', $run->id)
                // شمارِ صفحاتِ ارجاع‌دهنده = وزنِ اهمیت.
                ->select('seo_link_targets.*')
                ->selectSub(
                    fn ($q) => $q->from('seo_links')
                        ->whereColumn('seo_links.target_url', 'seo_link_targets.url')
                        ->where('seo_links.run_id', $run->id)
                        ->selectRaw('count(*)'),
                    'ref_count'
                );

            if ($type === 'broken') {
                $query->where('is_broken', true)->whereNull('ignored_at');
            } elseif ($type === 'redirect') {
                $query->where('is_redirect', true)->where('is_broken', false)->whereNull('ignored_at');
            } else {
                $query->whereNotNull('ignored_at');
            }

            $targets = $query->orderByDesc('ref_count')->orderByDesc('id')->paginate(30)->withQueryString();
        }

        return view('seo::broken-links.index', compact('run', 'targets', 'type', 'counts'));
    }

    public function show(SeoLinkTarget $target)
    {
        // صفحاتِ ارجاع‌دهنده به این مقصد + محلِ دقیقِ لینک در هر صفحه.
        $referrers = SeoLink::query()
            ->where('run_id', $target->run_id)
            ->where('target_url', $target->url)
            ->orderBy('source_url')
            ->get(['source_url', 'anchor', 'rel', 'selector', 'xpath', 'context_html', 'position']);

        // منبعِ احتمالیِ داده: اگر مسیر به یک entity شناخته‌شده می‌خورد (blog/devices/…)
        $sourceHint = $this->sourceHint($target->path);

        return view('seo::broken-links.show', compact('target', 'referrers', 'sourceHint'));
    }

    /**
     * حدسِ منبعِ لینک از روی مسیر — «از کدام نوع محتوا/entity آمده».
     * فقط راهنماست (نه قطعی)؛ اتصال به فایلِ کامپوننت بدونِ کدِ فرانت ممکن نیست.
     *
     * @return array{type:string,slug:?string}|null
     */
    private function sourceHint(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if ($segments === []) {
            return null;
        }

        return match ($segments[0]) {
            'blog' => ['type' => 'مقاله (blog)', 'slug' => $segments[1] ?? null],
            'devices' => ['type' => 'دستگاه (device)', 'slug' => $segments[1] ?? null],
            'services' => ['type' => 'خدمت/ترکیبی (service)', 'slug' => implode('/', array_slice($segments, 1)) ?: null],
            'forum' => ['type' => 'پرسش انجمن (forum)', 'slug' => $segments[1] ?? null],
            default => ['type' => 'صفحه', 'slug' => $segments[0]],
        };
    }
}
