<?php

namespace Modules\CRM\Http\Controllers\Accounting;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Concerns\FiltersExpenses;
use Modules\CRM\Models\Expense;
use Modules\CRM\Models\ExpenseCategory;
use Modules\CRM\Models\ExpenseTag;
use Modules\CRM\Models\PaymentAccount;
use Morilog\Jalali\Jalalian;

/**
 * ثبت و مدیریت هزینه‌ها — فاز ۱ حسابداری.
 *
 * - ثبت: هر کاربر با view-crm-costs (بدون نیاز به تأیید).
 * - ویرایش: فقط manage-crm-costs.
 * - حذف: فقط manage-crm-costs و فقط تا ۲۴ ساعت پس از ثبت.
 */
class ExpenseController extends Controller
{
    use FiltersExpenses;

    public function index(Request $request)
    {
        $filters = $this->expenseFilters($request);

        $query = Expense::query()
            ->with(['category.parent', 'account', 'tags', 'creator:id,first_name,last_name']);
        $this->applyExpenseFilters($query, $filters);

        $totalAmount = (int) (clone $query)->sum('amount');
        $totalCount  = (clone $query)->count();

        $expenses = $query->orderByDesc('paid_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('crm::accounting.expenses.index', [
            'expenses'    => $expenses,
            'filters'     => $filters,
            'hasFilter'   => $this->hasActiveExpenseFilter($filters),
            'totalAmount' => $totalAmount,
            'totalCount'  => $totalCount,
            'categories'  => ExpenseCategory::roots()->with('children')->get(),
            'accounts'    => PaymentAccount::orderBy('title')->get(),
            'allTags'     => ExpenseTag::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('crm::accounting.expenses.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateExpense($request);
        $data['created_by'] = auth()->id();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('crm/expenses', 'public');
        }

        $expense = Expense::create($data);
        $this->syncTags($expense, $request->input('tags', ''));

        return redirect()->route('crm.costs.index')
            ->with('success', 'هزینه ثبت شد: ' . number_format($expense->amount) . ' تومان — ' . $expense->description);
    }

    public function edit(Expense $expense)
    {
        $expense->load('tags');

        return view('crm::accounting.expenses.edit', $this->formData() + ['expense' => $expense]);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $this->validateExpense($request);

        if ($request->hasFile('attachment')) {
            if ($expense->attachment_path) {
                Storage::disk('public')->delete($expense->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('crm/expenses', 'public');
        }

        $expense->update($data);
        $this->syncTags($expense, $request->input('tags', ''));

        return redirect()->route('crm.costs.index')
            ->with('success', 'هزینه ویرایش شد.');
    }

    public function destroy(Expense $expense)
    {
        // قانون فاز ۱: حذف فقط تا ۲۴ ساعت پس از ثبت.
        if (! $expense->isDeletable()) {
            return back()->with('error', 'بیش از ۲۴ ساعت از ثبت این هزینه گذشته — حذف مجاز نیست؛ فقط مشاهده و ویرایش ممکن است.');
        }

        if ($expense->attachment_path) {
            Storage::disk('public')->delete($expense->attachment_path);
        }
        $expense->delete();

        return redirect()->route('crm.costs.index')->with('success', 'هزینه حذف شد.');
    }

    /** سرو فایل ضمیمه — پشت permission، بدون نیاز به symlink. */
    public function attachment(Expense $expense)
    {
        abort_unless($expense->attachment_path, 404);
        abort_unless(Storage::disk('public')->exists($expense->attachment_path), 404);

        return response()->file(Storage::disk('public')->path($expense->attachment_path));
    }

    // ─── helpers ─────────────────────────────────────────────────

    private function formData(): array
    {
        return [
            'categories'     => ExpenseCategory::roots()->with('children')->get(),
            'accounts'       => PaymentAccount::active()->orderBy('title')->get(),
            'paymentMethods' => Expense::PAYMENT_METHODS,
            'allTags'        => ExpenseTag::orderBy('name')->pluck('name'),
        ];
    }

    private function validateExpense(Request $request): array
    {
        $validated = $request->validate([
            'paid_date'          => 'required|string|max:12',
            'paid_time'          => 'required|date_format:H:i',
            'amount'             => 'required|string|max:20',
            'category_id'        => 'required|integer|exists:crm_expense_categories,id',
            'child_id'           => 'required|integer|exists:crm_expense_categories,id',
            'payment_account_id' => 'required|integer|exists:crm_payment_accounts,id',
            'description'        => 'required|string|max:500',
            'payee'              => 'nullable|string|max:190',
            'payer'              => 'nullable|string|max:190',
            'payment_method'     => 'nullable|in:' . implode(',', array_keys(Expense::PAYMENT_METHODS)),
            'tracking_number'    => 'nullable|string|max:100',
            'attachment'         => 'nullable|file|max:8192|mimes:jpg,jpeg,png,webp,pdf',
            'note'               => 'nullable|string|max:2000',
            'tags'               => 'nullable|string|max:1000',
        ], [
            'paid_date.required'          => 'تاریخ هزینه الزامی است.',
            'paid_time.required'          => 'ساعت ثبت/پرداخت الزامی است.',
            'paid_time.date_format'       => 'ساعت باید به شکل HH:MM باشد.',
            'amount.required'             => 'مبلغ الزامی است.',
            'category_id.required'        => 'دسته اصلی الزامی است.',
            'child_id.required'           => 'زیر دسته الزامی است.',
            'payment_account_id.required' => 'حساب پرداخت الزامی است.',
            'description.required'        => 'شرح هزینه الزامی است.',
            'attachment.max'              => 'حجم فایل ضمیمه حداکثر ۸ مگابایت است.',
            'attachment.mimes'            => 'فرمت‌های مجاز ضمیمه: jpg, png, webp, pdf.',
        ]);

        // تاریخ شمسی + ساعت → datetime میلادی
        $gregorianDate = $this->expenseJalaliToGregorian($validated['paid_date']);
        if (! $gregorianDate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'paid_date' => 'تاریخ شمسی نامعتبر است (مثال درست: 1405/03/21).',
            ]);
        }

        // مبلغ با جداکننده/ارقام فارسی → integer
        $amount = $this->normalizeAmount($validated['amount']);
        if ($amount === null || $amount <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'مبلغ باید عددی بزرگ‌تر از صفر باشد.',
            ]);
        }

        // زیر دسته باید واقعاً فرزندِ دسته اصلی انتخاب‌شده باشد.
        $child = ExpenseCategory::find($validated['child_id']);
        if (! $child || (int) $child->parent_id !== (int) $validated['category_id']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'child_id' => 'زیر دسته انتخاب‌شده متعلق به دسته اصلی نیست.',
            ]);
        }

        return [
            'paid_at'            => $gregorianDate . ' ' . $validated['paid_time'] . ':00',
            'amount'             => $amount,
            'category_id'        => (int) $validated['child_id'],
            'payment_account_id' => (int) $validated['payment_account_id'],
            'description'        => $validated['description'],
            'payee'              => $validated['payee'] ?? null,
            'payer'              => $validated['payer'] ?? null,
            'payment_method'     => $validated['payment_method'] ?? null,
            'tracking_number'    => $validated['tracking_number'] ?? null,
            'note'               => $validated['note'] ?? null,
        ];
    }

    /** تگ‌های ورودی (جداشده با کاما/ویرگول فارسی) → sync با firstOrCreate. */
    private function syncTags(Expense $expense, ?string $raw): void
    {
        $names = collect(preg_split('/[,،]+/u', (string) $raw))
            ->map(fn ($s) => trim($s))
            ->filter(fn ($s) => $s !== '')
            ->unique()
            ->take(20);

        $ids = $names->map(fn ($name) => ExpenseTag::firstOrCreate(['name' => $name])->id);

        $expense->tags()->sync($ids->all());
    }
}
