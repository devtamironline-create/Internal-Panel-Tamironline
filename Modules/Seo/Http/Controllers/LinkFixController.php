<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\LinkFixChange;
use Modules\Seo\Models\LinkFixRule;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Services\ContentLinkFixer;

/**
 * اصلاحِ لینک‌های داخلیِ اشتباه (سطحِ دیتابیس) — مقالات/دستگاه‌ها/برندها/صفحاتِ ترکیبی.
 * جریان: واردکردنِ قواعد → اسکن (بدونِ تغییر) → پیش‌نمایش → اعمال → بازگردانی در صورتِ نیاز.
 */
class LinkFixController extends Controller
{
    public function __construct(private readonly ContentLinkFixer $fixer) {}

    public function index(Request $request)
    {
        $filter = (string) $request->query('filter', '');

        $query = LinkFixRule::query()->orderByDesc('matches_count')->orderBy('id');
        if ($filter === 'pending') {
            $query->where('status', 'pending')->where('needs_review', false);
        } elseif ($filter === 'applied') {
            $query->where('status', 'applied');
        } elseif ($filter === 'review') {
            $query->where('needs_review', true);
        } elseif ($filter === 'matched') {
            $query->where('status', 'pending')->where('needs_review', false)->where('matches_count', '>', 0);
        }

        $rules = $query->paginate(50)->withQueryString();

        $stats = [
            'total' => LinkFixRule::count(),
            'pending' => LinkFixRule::where('status', 'pending')->where('needs_review', false)->count(),
            'matched' => LinkFixRule::where('status', 'pending')->where('needs_review', false)->where('matches_count', '>', 0)->count(),
            'applied' => LinkFixRule::where('status', 'applied')->count(),
            'review' => LinkFixRule::where('needs_review', true)->count(),
            'changes' => LinkFixChange::count(),
            'seed_available' => is_file($this->seedPath()),
        ];

        return view('seo::link-fixes.index', compact('rules', 'stats', 'filter'));
    }

    /** واردکردنِ قواعد از فایلِ برنامه‌نویس (JSONِ داخلِ ریپو) — idempotent. */
    public function import()
    {
        $path = $this->seedPath();
        if (! is_file($path)) {
            return back()->with('error', 'فایلِ قواعد (link-fix-rules.json) پیدا نشد.');
        }

        $rows = json_decode((string) file_get_contents($path), true);
        if (! is_array($rows)) {
            return back()->with('error', 'فایلِ قواعد معتبر نیست.');
        }

        $created = 0;
        foreach ($rows as $row) {
            $old = trim((string) ($row['old_url'] ?? ''));
            if ($old === '') {
                continue;
            }
            $exists = LinkFixRule::where('old_url_hash', sha1(ContentLinkFixer::normalizedKey($old)))->exists();
            if ($exists) {
                continue; // شاملِ دوقلوهای http/https و اسلشِ انتهایی
            }
            LinkFixRule::create([
                'old_url' => $old,
                'action' => ($row['action'] ?? 'replace') === 'unlink' ? 'unlink' : 'replace',
                'new_url' => $row['new_url'] ?? null,
                'note' => mb_substr((string) ($row['note'] ?? ''), 0, 500) ?: null,
                'source' => $row['source'] ?? 'file',
                'needs_review' => (bool) ($row['needs_review'] ?? false),
            ]);
            $created++;
        }

        SeoChangeLog::record('imported', 'link_fix_rules', $created.' قاعده از فایلِ برنامه‌نویس وارد شد.');

        return back()->with('success', $created.' قاعدهٔ جدید وارد شد (تکراری‌ها رد شدند). حالا «اسکنِ همه» را بزنید.');
    }

    /** افزودنِ قاعدهٔ دستی. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'old_url' => ['required', 'string', 'max:700'],
            'action' => ['required', 'in:replace,unlink'],
            'new_url' => ['nullable', 'string', 'max:700', 'required_if:action,replace'],
        ], [
            'new_url.required_if' => 'برای جایگزینی، آدرسِ مقصد الزامی است.',
        ]);

        if (LinkFixRule::where('old_url_hash', sha1(ContentLinkFixer::normalizedKey($data['old_url'])))->exists()) {
            return back()->with('error', 'قاعده‌ای برای این URL (با هر scheme/اسلش) از قبل وجود دارد.');
        }

        LinkFixRule::create([
            'old_url' => trim($data['old_url']),
            'action' => $data['action'],
            'new_url' => $data['action'] === 'replace' ? trim((string) $data['new_url']) : null,
            'source' => 'manual',
        ]);

        return back()->with('success', 'قاعده اضافه شد. اسکن کنید تا موارد پیدا شوند.');
    }

    /** اسکنِ همهٔ قواعدِ pending — تک‌گذر، هیچ تغییری اعمال نمی‌شود. */
    public function scanAll()
    {
        $this->raiseLimits();

        try {
            $res = $this->fixer->scanAllPending();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('seo.link_fix_scan_failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'اسکن با خطا مواجه شد: '.$e->getMessage());
        }

        return back()->with('success', "اسکن تمام شد: {$res['scanned']} قاعده بررسی شد، {$res['with_matches']} قاعده مورد دارد. از «فقط دارای مورد» شروع کنید.");
    }

    /** پیش‌نمایشِ یک قاعده — اسکنِ زنده با جزئیات. */
    public function show(LinkFixRule $rule)
    {
        $this->raiseLimits();

        try {
            $result = $this->fixer->scan($rule);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('seo.link_fix_preview_failed', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);

            return redirect()->route('seo.admin.link-fixes.index')
                ->with('error', 'پیش‌نمایش با خطا مواجه شد: '.$e->getMessage());
        }
        $changes = $rule->changes()->orderByDesc('id')->limit(100)->get();

        return view('seo::link-fixes.show', [
            'rule' => $rule->refresh(),
            'matches' => $result['matches'],
            'skippedSelf' => $result['skipped_self'],
            'changes' => $changes,
            'typeLabels' => ContentLinkFixer::TYPE_LABELS,
        ]);
    }

    /** اعمالِ یک قاعده. */
    public function apply(Request $request, LinkFixRule $rule)
    {
        if ($rule->needs_review) {
            return back()->with('error', 'این قاعده «نیازمند بررسی» است (مقصدِ متفاوت در صفحاتِ مختلف) — ابتدا مقصد را مشخص/ویرایش کنید.');
        }

        $this->raiseLimits();

        try {
            $res = $this->fixer->apply($rule, auth()->id());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('seo.link_fix_apply_failed', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'اعمال با خطا مواجه شد: '.$e->getMessage());
        }

        SeoChangeLog::record('applied', 'link_fix', $rule->old_url.' → '.($rule->new_url ?? 'حذف لینک').' ('.$res['applied'].' مورد)');

        $msg = "اعمال شد: {$res['applied']} مورد در {$res['records']} رکورد.";
        if ($res['skipped_self'] > 0) {
            $msg .= " ({$res['skipped_self']} رکورد به‌دلیلِ self-link رد شد.)";
        }

        return back()->with('success', $msg);
    }

    /** اعمالِ همهٔ قواعدِ آماده (pending، بدونِ نیاز به بررسی، دارای مورد) — تک‌گذر. */
    public function applyAll()
    {
        $this->raiseLimits();

        try {
            $res = $this->fixer->applyAllPending(auth()->id());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('seo.link_fix_apply_failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'اعمالِ گروهی با خطا مواجه شد: '.$e->getMessage().' — تغییراتِ ثبت‌شده تا این لحظه در «تاریخچه» قابلِ مشاهده و بازگردانی‌اند.');
        }

        SeoChangeLog::record('applied', 'link_fix_bulk', $res['rules'].' قاعده / '.$res['applied'].' مورد / '.$res['records'].' رکورد');

        return back()->with('success', "اعمالِ گروهی تمام شد: {$res['applied']} مورد در {$res['records']} رکورد. همه‌چیز از «تاریخچهٔ تغییرات» قابلِ بازگردانی است.");
    }

    /** افزایشِ سقفِ زمان/حافظه برای عملیاتِ سنگین (الگوی SiteCrawler). */
    private function raiseLimits(): void
    {
        @set_time_limit(0);
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw !== '-1' && (int) $raw < 512) {
            @ini_set('memory_limit', '512M');
        }
    }

    /** ویرایشِ قاعده (برای موارد «نیازمند بررسی» یا اصلاحِ مقصد). */
    public function update(Request $request, LinkFixRule $rule)
    {
        $data = $request->validate([
            'action' => ['required', 'in:replace,unlink'],
            'new_url' => ['nullable', 'string', 'max:700', 'required_if:action,replace'],
        ]);

        $rule->update([
            'action' => $data['action'],
            'new_url' => $data['action'] === 'replace' ? trim((string) $data['new_url']) : null,
            'needs_review' => false,
        ]);

        return back()->with('success', 'قاعده به‌روزرسانی شد و از حالتِ «نیازمند بررسی» خارج شد.');
    }

    public function destroy(LinkFixRule $rule)
    {
        if ($rule->status === 'applied') {
            return back()->with('error', 'قاعدهٔ اعمال‌شده حذف نمی‌شود (تاریخچه/بازگردانی به آن وابسته است).');
        }
        $rule->delete();

        return back()->with('success', 'قاعده حذف شد.');
    }

    /** فهرستِ تغییراتِ اعمال‌شده + بازگردانی. */
    public function changes(Request $request)
    {
        $changes = LinkFixChange::with('rule')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('seo::link-fixes.changes', [
            'changes' => $changes,
            'typeLabels' => ContentLinkFixer::TYPE_LABELS,
        ]);
    }

    public function revert(LinkFixChange $change)
    {
        if ($change->reverted_at) {
            return back()->with('error', 'این تغییر قبلاً بازگردانی شده است.');
        }

        $ok = $this->fixer->revert($change);
        if (! $ok) {
            return back()->with('error', 'رکوردِ مقصد پیدا نشد؛ بازگردانی ممکن نیست.');
        }

        SeoChangeLog::record('reverted', 'link_fix', $change->entity_type.'#'.$change->entity_id.'.'.$change->field);

        return back()->with('success', 'فیلد به نسخهٔ قبل از اصلاح بازگردانده شد.');
    }

    /**
     * نرمال‌سازیِ دامنه (http/www → https بدونِ www) — mode=scan فقط می‌شمارد،
     * mode=apply اعمال می‌کند (با ثبتِ تغییرات و قابلیتِ بازگردانی).
     */
    public function normalizeDomain(Request $request)
    {
        $this->raiseLimits();
        $apply = $request->input('mode') === 'apply';

        try {
            $res = $this->fixer->domainNormalize($apply, auth()->id());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('seo.domain_normalize_failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'نرمال‌سازی دامنه با خطا مواجه شد: '.$e->getMessage());
        }

        $byType = collect($res['by_type'])
            ->map(fn ($n, $t) => (ContentLinkFixer::TYPE_LABELS[$t] ?? $t).': '.$n)
            ->implode('، ');

        if (! $apply) {
            return back()->with('success', $res['total'] === 0
                ? 'اسکن دامنه تمام شد: هیچ لینکِ http/www داخلی پیدا نشد. ✅'
                : "اسکن دامنه: {$res['total']} موردِ http/www در {$res['records']} رکورد پیدا شد ({$byType}). برای اصلاح، «اصلاحِ دامنه» را بزنید.");
        }

        SeoChangeLog::record('applied', 'link_fix', 'domain-normalize: '.$res['total'].' occurrences');

        return back()->with('success', $res['total'] === 0
            ? 'چیزی برای اصلاح نبود — همهٔ لینک‌ها از قبل https و بدونِ www هستند. ✅'
            : "نرمال‌سازی دامنه اعمال شد: {$res['total']} مورد در {$res['records']} رکورد ({$byType}). تغییرات از «تاریخچهٔ تغییرات» قابلِ بازگردانی است.");
    }

    /**
     * ساختِ قاعدهٔ اصلاح از ریدایرکت‌های فعالِ 301/308 (فقط exact): لینکِ داخلی
     * به URLِ ریدایرکتی → مقصدِ نهایی (زنجیره تا عمقِ ۵ دنبال می‌شود).
     * قواعدِ ساخته‌شده pending هستند — مثل بقیه اول اسکن، بعد اعمال.
     */
    public function importFromRedirects()
    {
        $this->raiseLimits();

        $host = ContentLinkFixer::canonicalHost();
        $base = 'https://'.$host;
        $abs = fn (string $u) => str_starts_with($u, 'http') ? $u : $base.'/'.ltrim($u, '/');

        $redirects = \Modules\Seo\Models\SeoRedirect::query()
            ->active()
            ->where('match_type', 'exact')
            ->whereIn('status_code', [301, 308])
            ->whereNotNull('target')
            ->get(['source', 'target']);

        // نگاشت برای دنبال‌کردنِ زنجیره: source نرمال → target
        $map = [];
        foreach ($redirects as $r) {
            $map[ContentLinkFixer::normalizedKey($abs((string) $r->source))] = (string) $r->target;
        }

        $created = 0;
        $skipped = 0;
        foreach ($redirects as $r) {
            $old = $abs((string) $r->source);

            // دنبال‌کردنِ زنجیره تا مقصدِ نهایی (حداکثر ۵ گام، با گاردِ حلقه).
            $target = (string) $r->target;
            $seen = [ContentLinkFixer::normalizedKey($old) => true];
            for ($i = 0; $i < 5; $i++) {
                $key = ContentLinkFixer::normalizedKey($abs($target));
                if (! isset($map[$key]) || isset($seen[$key])) {
                    break;
                }
                $seen[$key] = true;
                $target = $map[$key];
            }
            $new = $abs($target);

            if (ContentLinkFixer::normalizedKey($old) === ContentLinkFixer::normalizedKey($new)) {
                $skipped++;

                continue;
            }

            $hash = sha1(ContentLinkFixer::normalizedKey($old));
            if (LinkFixRule::where('old_url_hash', $hash)->exists()) {
                $skipped++;

                continue;
            }

            LinkFixRule::create([
                'old_url' => $old,
                'old_url_hash' => $hash,
                'new_url' => $new,
                'action' => 'replace',
                'note' => 'ساخته‌شده از ریدایرکت (لینکِ داخلی نباید به URLِ ریدایرکتی برود)',
                'source' => 'redirect',
                'needs_review' => false,
                'status' => 'pending',
            ]);
            $created++;
        }

        if ($created === 0) {
            return back()->with('success', "قاعدهٔ جدیدی لازم نبود ({$skipped} موردِ تکراری/بی‌اثر رد شد).");
        }

        return back()->with('success', "{$created} قاعده از ریدایرکت‌ها ساخته شد ({$skipped} رد شد). حالا «اسکنِ همه» و بعد «اعمالِ همه» را بزنید.");
    }

    private function seedPath(): string
    {
        return module_path('Seo', 'Database/data/link-fix-rules.json');
    }
}
