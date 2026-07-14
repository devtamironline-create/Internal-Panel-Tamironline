<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\LinkSuggestion;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Services\InternalLinkAnalyzer;

/**
 * لینک‌سازیِ داخلی — داشبورد (امتیاز/یتیم/جزیره‌ها/عمق) + پیشنهادها
 * (تولید/تأیید/رد/اعمال/بازگردانی). تحلیل مستقیم از محتوای دیتابیس است و
 * ۱۵ دقیقه کش می‌شود (دکمهٔ «بروزرسانی» کش را تازه می‌کند).
 */
class InternalLinkController extends Controller
{
    public function __construct(private readonly InternalLinkAnalyzer $analyzer) {}

    public function index(Request $request)
    {
        $this->raiseLimits();
        $analysis = $this->analyzer->analyze($request->boolean('fresh'));

        // صفحاتِ نیازمندِ اقدام: کمترین امتیاز (غیرمادر)
        $needsAction = collect($analysis['pages'])
            ->map(fn ($p, $path) => $p + ['path' => $path])
            ->filter(fn ($p) => $p['type'] !== 'device')
            ->sortBy('score')
            ->take(12)
            ->values();

        $suggestionStats = [
            'pending' => LinkSuggestion::pending()->count(),
            'approved' => LinkSuggestion::approved()->count(),
            'applied' => LinkSuggestion::where('status', 'applied')->count(),
        ];

        $topSuggestions = LinkSuggestion::pending()
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->limit(6)->get();

        return view('seo::internal-links.index', [
            'analysis' => $analysis,
            'needsAction' => $needsAction,
            'suggestionStats' => $suggestionStats,
            'topSuggestions' => $topSuggestions,
        ]);
    }

    /** صفحهٔ پیشنهادها — فیلتر بر اساس وضعیت/اولویت/جزیره. */
    public function suggestions(Request $request)
    {
        $status = (string) $request->query('status', 'pending');

        $q = LinkSuggestion::query();
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($priority = $request->query('priority')) {
            $q->where('priority', $priority);
        }
        if ($island = $request->query('island')) {
            $q->where('island', $island);
        }

        $suggestions = $q->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('seo::internal-links.suggestions', [
            'suggestions' => $suggestions,
            'islands' => LinkSuggestion::query()->whereNotNull('island')->distinct()->pluck('island'),
            'filters' => [
                'status' => $status,
                'priority' => (string) $request->query('priority', ''),
                'island' => (string) $request->query('island', ''),
            ],
            'counts' => [
                'pending' => LinkSuggestion::pending()->count(),
                'approved' => LinkSuggestion::approved()->count(),
                'applied' => LinkSuggestion::where('status', 'applied')->count(),
                'rejected' => LinkSuggestion::where('status', 'rejected')->count(),
            ],
        ]);
    }

    /** تولید/به‌روزرسانیِ پیشنهادها طبق قوانینِ جزیره. */
    public function generate()
    {
        $this->raiseLimits();

        try {
            $res = $this->analyzer->generateSuggestions();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('seo.link_suggest_failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'تولیدِ پیشنهادها با خطا مواجه شد: '.$e->getMessage());
        }

        return back()->with('success', $res['created'] === 0
            ? 'پیشنهادِ جدیدی پیدا نشد — همه از قبل ثبت شده‌اند.'
            : "{$res['created']} پیشنهادِ جدید ساخته شد. از صفحهٔ «پیشنهادها» تأیید/رد کنید.");
    }

    public function approve(LinkSuggestion $suggestion)
    {
        abort_unless(in_array($suggestion->status, ['pending', 'rejected'], true), 422);
        $suggestion->update(['status' => 'approved', 'decided_by' => auth()->id()]);

        return back()->with('success', 'پیشنهاد تأیید شد.');
    }

    public function reject(LinkSuggestion $suggestion)
    {
        abort_unless(in_array($suggestion->status, ['pending', 'approved'], true), 422);
        $suggestion->update(['status' => 'rejected', 'decided_by' => auth()->id()]);

        return back()->with('success', 'پیشنهاد رد شد.');
    }

    /** ویرایشِ انکر قبل از اعمال. */
    public function updateAnchor(Request $request, LinkSuggestion $suggestion)
    {
        $data = $request->validate(['anchor' => 'required|string|max:300']);
        abort_unless(in_array($suggestion->status, ['pending', 'approved'], true), 422);
        $suggestion->update(['anchor' => trim($data['anchor'])]);

        return back()->with('success', 'انکر به‌روزرسانی شد.');
    }

    /** اعمالِ یک پیشنهاد (اگر pending بود، همان لحظه تأیید می‌شود). */
    public function apply(LinkSuggestion $suggestion)
    {
        if ($suggestion->status === 'pending') {
            $suggestion->update(['status' => 'approved', 'decided_by' => auth()->id()]);
            $suggestion->refresh();
        }

        $ok = $this->analyzer->applySuggestion($suggestion);
        if (! $ok) {
            return back()->with('error', 'اعمال ممکن نشد — متنِ انکر در محتوای آزادِ صفحه پیدا نشد (شاید قبلاً لینک شده). انکر را ویرایش کنید.');
        }

        SeoChangeLog::record('applied', 'internal_link', $suggestion->page_path.' → '.$suggestion->target_path);

        return back()->with('success', 'لینک اضافه شد: «'.$suggestion->anchor.'» → '.$suggestion->target_path);
    }

    /** اعمالِ دسته‌ایِ همهٔ تأییدشده‌ها. */
    public function applyApproved()
    {
        $this->raiseLimits();

        $ok = 0;
        $fail = 0;
        foreach (LinkSuggestion::approved()->orderBy('id')->get() as $s) {
            $this->analyzer->applySuggestion($s) ? $ok++ : $fail++;
        }

        if ($ok > 0) {
            SeoChangeLog::record('applied', 'internal_link', "bulk: {$ok} links");
        }

        return back()->with($ok > 0 ? 'success' : 'error',
            "اعمال شد: {$ok} لینک".($fail > 0 ? " — {$fail} مورد ناموفق (انکر در متنِ آزاد پیدا نشد)" : ''));
    }

    public function revert(LinkSuggestion $suggestion)
    {
        if (! $this->analyzer->revertSuggestion($suggestion)) {
            return back()->with('error', 'بازگردانی ممکن نیست.');
        }

        SeoChangeLog::record('reverted', 'internal_link', $suggestion->page_path.' → '.$suggestion->target_path);

        return back()->with('success', 'محتوای صفحه به نسخهٔ قبل از افزودنِ لینک برگشت.');
    }

    private function raiseLimits(): void
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
    }
}
