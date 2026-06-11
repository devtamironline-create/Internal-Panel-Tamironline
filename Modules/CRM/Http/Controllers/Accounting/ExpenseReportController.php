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

        return view('crm::accounting.report', [
            'rows'       => $rows,
            'group'      => $group,
            'groups'     => self::GROUPS,
            'filters'    => $filters,
            'hasFilter'  => $this->hasActiveExpenseFilter($filters),
            'grandTotal' => $grandTotal,
            'grandCount' => $grandCount,
            'categories' => ExpenseCategory::roots()->with('children')->get(),
            'accounts'   => PaymentAccount::orderBy('title')->get(),
            'allTags'    => ExpenseTag::orderBy('name')->get(['id', 'name']),
        ]);
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
