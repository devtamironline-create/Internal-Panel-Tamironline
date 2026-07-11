<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Seo\Models\SeoAuditRun;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoLink;
use Modules\Seo\Models\SeoLinkTarget;
use Modules\Seo\Models\SeoRedirect;
use Modules\Seo\Services\LinkFixSuggester;
use Modules\Seo\Services\RedirectMatcher;

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

        // پیشنهادِ نزدیک‌ترین URLِ معتبر (فقط برای مقصدِ داخلی) + Confidence.
        $suggestion = $target->is_internal
            ? app(LinkFixSuggester::class)->suggest($target)
            : ['best' => null, 'candidates' => []];

        return view('seo::broken-links.show', compact('target', 'referrers', 'sourceHint', 'suggestion'));
    }

    /**
     * اصلاح با «ایجاد ریدایرکت»: از مسیرِ خرابِ داخلی → مقصدِ معتبر (۳۰۱ پیش‌فرض).
     * از همان SeoRedirect/RedirectMatcher/SeoChangeLogِ سیستمِ موجود استفاده می‌کند
     * (بدونِ سیستمِ موازی). ریدایرکت در فرانت از /v1/seo/redirects اعمال می‌شود.
     */
    public function redirect(Request $request, SeoLinkTarget $target)
    {
        abort_unless($target->is_internal && $target->path, 400, 'ریدایرکت فقط برای مسیرِ داخلی معنا دارد.');

        $data = $request->validate([
            'target' => 'required|string|max:2048',
            'status_code' => ['required', 'integer', Rule::in(SeoRedirect::STATUS_CODES)],
        ]);

        $source = $target->path;
        $matcher = new RedirectMatcher;
        if (! empty($data['target']) && $matcher->wouldLoop($source, $data['target'])) {
            return back()->with('error', 'این ریدایرکت یک حلقه می‌سازد؛ مقصد را اصلاح کنید.');
        }

        SeoRedirect::create([
            'source' => $source,
            'target' => $data['target'],
            'status_code' => (int) $data['status_code'],
            'match_type' => 'exact',
            'is_active' => true,
        ]);

        SeoChangeLog::record('created', 'redirect', $source.' → '.$data['target'].' (از لینکِ خراب)');

        // مقصد را «حل‌شده» علامت می‌زنیم تا از فهرستِ فعال خارج شود.
        $target->forceFill([
            'ignored_at' => now(),
            'ignore_reason' => 'ریدایرکت ایجاد شد → '.$data['target'],
        ])->save();

        return redirect()
            ->route('seo.admin.broken-links.show', $target)
            ->with('success', 'ریدایرکت ۳۰۱ ساخته شد و لینک حل‌شده علامت خورد. مدیریت/بازگردانی از صفحهٔ «ریدایرکت‌ها».');
    }

    /** علامت‌گذاری به‌عنوان «نادیده» با ثبتِ دلیل (قابلِ بازگردانی). */
    public function ignore(Request $request, SeoLinkTarget $target)
    {
        $reason = trim((string) $request->input('reason'));
        $target->forceFill([
            'ignored_at' => now(),
            'ignore_reason' => $reason !== '' ? mb_substr($reason, 0, 300) : 'بدونِ دلیل',
        ])->save();

        SeoChangeLog::record('ignored', 'broken_link', $target->url.($reason !== '' ? ' — '.$reason : ''));

        return back()->with('success', 'لینک نادیده گرفته شد.');
    }

    /** بازگردانیِ «نادیده» — لینک دوباره به فهرستِ فعال بازمی‌گردد. */
    public function unignore(SeoLinkTarget $target)
    {
        $target->forceFill(['ignored_at' => null, 'ignore_reason' => null])->save();

        SeoChangeLog::record('unignored', 'broken_link', $target->url);

        return back()->with('success', 'از حالتِ نادیده خارج شد.');
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
