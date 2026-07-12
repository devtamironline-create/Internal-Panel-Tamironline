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
            $exists = LinkFixRule::where('old_url_hash', sha1($old))->exists();
            if ($exists) {
                continue;
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

        if (LinkFixRule::where('old_url_hash', sha1(trim($data['old_url'])))->exists()) {
            return back()->with('error', 'قاعده‌ای برای این URL از قبل وجود دارد.');
        }

        LinkFixRule::create([
            'old_url' => trim($data['old_url']),
            'action' => $data['action'],
            'new_url' => $data['action'] === 'replace' ? trim((string) $data['new_url']) : null,
            'source' => 'manual',
        ]);

        return back()->with('success', 'قاعده اضافه شد. اسکن کنید تا موارد پیدا شوند.');
    }

    /** اسکنِ همهٔ قواعدِ pending — هیچ تغییری اعمال نمی‌شود. */
    public function scanAll()
    {
        @set_time_limit(0);
        $scanned = 0;
        $withMatches = 0;
        foreach (LinkFixRule::pending()->cursor() as $rule) {
            $res = $this->fixer->scan($rule);
            $scanned++;
            if ($res['total'] > 0) {
                $withMatches++;
            }
        }

        return back()->with('success', "اسکن تمام شد: {$scanned} قاعده بررسی شد، {$withMatches} قاعده مورد دارد. از «فقط دارای مورد» شروع کنید.");
    }

    /** پیش‌نمایشِ یک قاعده — اسکنِ زنده با جزئیات. */
    public function show(LinkFixRule $rule)
    {
        @set_time_limit(0);
        $result = $this->fixer->scan($rule);
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

        @set_time_limit(0);
        $res = $this->fixer->apply($rule, auth()->id());

        SeoChangeLog::record('applied', 'link_fix', $rule->old_url.' → '.($rule->new_url ?? 'حذف لینک').' ('.$res['applied'].' مورد)');

        $msg = "اعمال شد: {$res['applied']} مورد در {$res['records']} رکورد.";
        if ($res['skipped_self'] > 0) {
            $msg .= " ({$res['skipped_self']} رکورد به‌دلیلِ self-link رد شد.)";
        }

        return back()->with('success', $msg);
    }

    /** اعمالِ همهٔ قواعدِ آماده (pending، بدونِ نیاز به بررسی، دارای مورد). */
    public function applyAll()
    {
        @set_time_limit(0);
        $appliedRules = 0;
        $appliedTotal = 0;
        foreach (LinkFixRule::pending()->where('needs_review', false)->where('matches_count', '>', 0)->cursor() as $rule) {
            $res = $this->fixer->apply($rule, auth()->id());
            if ($res['applied'] > 0) {
                $appliedRules++;
                $appliedTotal += $res['applied'];
            }
        }

        SeoChangeLog::record('applied', 'link_fix_bulk', $appliedRules.' قاعده / '.$appliedTotal.' مورد');

        return back()->with('success', "اعمالِ گروهی تمام شد: {$appliedTotal} مورد در قالبِ {$appliedRules} قاعده. همه‌چیز قابلِ بازگردانی است.");
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

    private function seedPath(): string
    {
        return module_path('Seo', 'Database/data/link-fix-rules.json');
    }
}
