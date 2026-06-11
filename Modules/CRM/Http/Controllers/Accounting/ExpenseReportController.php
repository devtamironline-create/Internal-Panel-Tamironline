<?php

namespace Modules\CRM\Http\Controllers\Accounting;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Concerns\FiltersExpenses;
use Modules\CRM\Models\Expense;
use Modules\CRM\Models\ExpenseCategory;
use Modules\CRM\Models\ExpenseTag;
use Modules\CRM\Models\PaymentAccount;

/**
 * گزارش هزینه‌ها — فاز ۱:
 * تجمیع بر اساس دسته اصلی / زیر دسته / حساب پرداخت / پرداخت‌کننده / تگ،
 * با همان فیلترهای لیست هزینه‌ها.
 */
class ExpenseReportController extends Controller
{
    use FiltersExpenses;

    public const GROUPS = [
        'category'    => 'دسته اصلی',
        'subcategory' => 'زیر دسته',
        'account'     => 'حساب پرداخت',
        'payer'       => 'پرداخت‌کننده',
        'tag'         => 'تگ',
    ];

    public function index(Request $request)
    {
        $group = $request->string('group')->toString();
        if (! array_key_exists($group, self::GROUPS)) {
            $group = 'category';
        }

        $filters = $this->expenseFilters($request);

        $base = Expense::query();
        $this->applyExpenseFilters($base, $filters);

        $grandTotal = (int) (clone $base)->sum('amount');
        $grandCount = (clone $base)->count();

        $rows = $this->aggregate($base, $group);

        // داده‌ی نمودار دایره‌ای — ۱۰ مورد برتر + «سایر»
        $pieRows = $rows->take(10);
        $otherTotal = (int) $rows->slice(10)->sum('total');
        $pie = [
            'labels' => $pieRows->pluck('label')->values()->all(),
            'values' => $pieRows->pluck('total')->map(fn ($v) => (int) $v)->values()->all(),
        ];
        if ($otherTotal > 0) {
            $pie['labels'][] = 'سایر';
            $pie['values'][] = $otherTotal;
        }

        return view('crm::accounting.report', [
            'rows'       => $rows,
            'group'      => $group,
            'groups'     => self::GROUPS,
            'filters'    => $filters,
            'hasFilter'  => $this->hasActiveExpenseFilter($filters),
            'grandTotal' => $grandTotal,
            'grandCount' => $grandCount,
            'pie'        => $pie,
            'lineChart'  => $this->dailyLineChart($base, $group, $filters),
            'categories' => ExpenseCategory::roots()->with('children')->get(),
            'accounts'   => PaymentAccount::orderBy('title')->get(),
            'allTags'    => ExpenseTag::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * نمودار خطی روزانه — سری جداگانه برای هر یک از ۶ گروهِ پرهزینه‌تر
     * (+ «سایر») در پنجرهٔ زمانی فیلتر؛ بدون فیلتر تاریخ: ۳۰ روز اخیر.
     *
     * @return array{dates: array<string>, series: array<array{name:string, data:array<int>}>}
     */
    private function dailyLineChart($base, string $group, array $filters): array
    {
        $fromG = $this->expenseJalaliToGregorian($filters['from_date']) ?: now()->subDays(29)->toDateString();
        $toG   = $this->expenseJalaliToGregorian($filters['to_date']) ?: now()->toDateString();
        if ($fromG > $toG) {
            [$fromG, $toG] = [$toG, $fromG];
        }
        // سقف ۱۸۰ روز تا نمودار روزانه خوانا بماند
        if (\Carbon\Carbon::parse($fromG)->diffInDays($toG) > 180) {
            $fromG = \Carbon\Carbon::parse($toG)->subDays(179)->toDateString();
        }

        [$q, $labelExpr] = $this->labeledQuery($base, $group);
        $raw = $q->whereDate('crm_expenses.paid_at', '>=', $fromG)
            ->whereDate('crm_expenses.paid_at', '<=', $toG)
            ->groupByRaw("DATE(crm_expenses.paid_at), {$labelExpr}")
            ->selectRaw("DATE(crm_expenses.paid_at) as d, {$labelExpr} as label, SUM(crm_expenses.amount) as total")
            ->get();

        // ۶ سری برتر؛ بقیه در «سایر» جمع می‌شوند
        $topLabels = $raw->groupBy('label')
            ->map(fn ($g) => (int) $g->sum('total'))
            ->sortDesc()->take(6)->keys()->all();

        $dates = [];
        $byDate = [];
        $cursor = \Carbon\Carbon::parse($fromG);
        $end = \Carbon\Carbon::parse($toG);
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $dates[$key] = \Morilog\Jalali\Jalalian::fromCarbon($cursor)->format('m/d');
            $byDate[$key] = [];
            $cursor->addDay();
        }

        foreach ($raw as $r) {
            $label = in_array($r->label, $topLabels, true) ? $r->label : 'سایر';
            $byDate[$r->d][$label] = ($byDate[$r->d][$label] ?? 0) + (int) $r->total;
        }

        $seriesLabels = $topLabels;
        if ($raw->pluck('label')->unique()->count() > count($topLabels)) {
            $seriesLabels[] = 'سایر';
        }

        $series = [];
        foreach ($seriesLabels as $label) {
            $series[] = [
                'name' => $label,
                'data' => array_values(array_map(fn ($day) => (int) ($day[$label] ?? 0), $byDate)),
            ];
        }

        return ['dates' => array_values($dates), 'series' => $series];
    }

    /**
     * کوئری با join و عبارت SQL برچسبِ گروه — مشترک بین نمودار خطی و
     * (در آینده) سایر تجمیع‌ها.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: string}
     */
    private function labeledQuery($base, string $group): array
    {
        $q = clone $base;

        return match ($group) {
            'category' => [
                $q->join('crm_expense_categories as child', 'child.id', '=', 'crm_expenses.category_id')
                    ->join('crm_expense_categories as parent', 'parent.id', '=', 'child.parent_id'),
                'parent.name',
            ],
            'subcategory' => [
                $q->join('crm_expense_categories as child', 'child.id', '=', 'crm_expenses.category_id'),
                'child.name',
            ],
            'account' => [
                $q->join('crm_payment_accounts as acc', 'acc.id', '=', 'crm_expenses.payment_account_id'),
                'acc.title',
            ],
            'payer' => [
                $q,
                "COALESCE(NULLIF(TRIM(crm_expenses.payer), ''), '— بدون پرداخت‌کننده —')",
            ],
            'tag' => [
                $q->join('crm_expense_tag as pt', 'pt.expense_id', '=', 'crm_expenses.id')
                    ->join('crm_expense_tags as tag', 'tag.id', '=', 'pt.tag_id'),
                'tag.name',
            ],
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{label:string, total:int, count:int}>
     */
    private function aggregate($base, string $group)
    {
        switch ($group) {
            case 'category':
                // join دو مرحله‌ای تا زیر دسته‌ها به دسته اصلی‌شان جمع شوند
                return (clone $base)
                    ->join('crm_expense_categories as child', 'child.id', '=', 'crm_expenses.category_id')
                    ->join('crm_expense_categories as parent', 'parent.id', '=', 'child.parent_id')
                    ->groupBy('parent.id', 'parent.name')
                    ->selectRaw('parent.name as label, SUM(crm_expenses.amount) as total, COUNT(*) as count')
                    ->orderByDesc('total')
                    ->get();

            case 'subcategory':
                return (clone $base)
                    ->join('crm_expense_categories as child', 'child.id', '=', 'crm_expenses.category_id')
                    ->leftJoin('crm_expense_categories as parent', 'parent.id', '=', 'child.parent_id')
                    ->groupBy('child.id', 'child.name', 'parent.name')
                    ->selectRaw("CONCAT(COALESCE(parent.name, ''), ' / ', child.name) as label, SUM(crm_expenses.amount) as total, COUNT(*) as count")
                    ->orderByDesc('total')
                    ->get();

            case 'account':
                return (clone $base)
                    ->join('crm_payment_accounts as acc', 'acc.id', '=', 'crm_expenses.payment_account_id')
                    ->groupBy('acc.id', 'acc.title')
                    ->selectRaw('acc.title as label, SUM(crm_expenses.amount) as total, COUNT(*) as count')
                    ->orderByDesc('total')
                    ->get();

            case 'payer':
                return (clone $base)
                    ->groupBy('payer')
                    ->selectRaw("COALESCE(NULLIF(TRIM(payer), ''), '— بدون پرداخت‌کننده —') as label, SUM(amount) as total, COUNT(*) as count")
                    ->orderByDesc('total')
                    ->get();

            case 'tag':
                // هزینهٔ چندتگی در هر تگ جداگانه جمع می‌شود (ماهیت گزارش تگ)
                return (clone $base)
                    ->join('crm_expense_tag as pt', 'pt.expense_id', '=', 'crm_expenses.id')
                    ->join('crm_expense_tags as tag', 'tag.id', '=', 'pt.tag_id')
                    ->groupBy('tag.id', 'tag.name')
                    ->selectRaw('tag.name as label, SUM(crm_expenses.amount) as total, COUNT(*) as count')
                    ->orderByDesc('total')
                    ->get();
        }

        return collect();
    }
}
