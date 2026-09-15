<?php

namespace Modules\CRM\Http\Controllers\Accounting;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Concerns\FiltersExpenses;
use Modules\CRM\Models\OwnerWithdrawal;
use Modules\CRM\Models\PaymentAccount;
use Morilog\Jalali\Jalalian;

/**
 * برداشتِ سرمایه (مالک/شرکا) — نوعِ دومِ «خروج وجه».
 *
 * عمداً از crm_expenses جداست تا هرگز در جمع/گزارشِ «هزینه» یا محاسبهٔ «سود»
 * وارد نشود. کلِ ماژول پشتِ یک مجوزِ واحدِ مدیریتی (manage-owner-withdrawals)
 * است چون اطلاعاتِ مالیِ مدیریتی محسوب می‌شود.
 *
 * از helperهای عمومیِ FiltersExpenses فقط برای نرمال‌سازیِ مبلغ و تبدیلِ
 * تاریخِ شمسی استفاده می‌شود (فیلترِ اختصاصیِ برداشت این‌جا نوشته شده).
 */
class OwnerWithdrawalController extends Controller
{
    use FiltersExpenses;

    public function index(Request $request)
    {
        $fromG = $this->expenseJalaliToGregorian($request->string('from_date')->toString());
        $toG = $this->expenseJalaliToGregorian($request->string('to_date')->toString());
        $accountId = $request->integer('account_id') ?: null;

        $query = OwnerWithdrawal::query()->with(['account', 'creator:id,first_name,last_name']);
        if ($fromG) {
            $query->whereDate('withdrawn_at', '>=', $fromG);
        }
        if ($toG) {
            $query->whereDate('withdrawn_at', '<=', $toG);
        }
        if ($accountId) {
            $query->where('payment_account_id', $accountId);
        }

        $totalAmount = (int) (clone $query)->sum('amount');
        $totalCount = (clone $query)->count();

        // برداشتِ همین ماهِ شمسی — KPI مستقل از فیلتر (اولِ ماهِ جاریِ شمسی).
        $monthStart = Jalalian::fromFormat('Y/m/d', Jalalian::now()->format('Y/m').'/01')
            ->toCarbon()->startOfDay();
        $monthTotal = (int) OwnerWithdrawal::query()
            ->where('withdrawn_at', '>=', $monthStart)
            ->sum('amount');

        $withdrawals = $query->orderByDesc('withdrawn_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('crm::accounting.withdrawals.index', [
            'withdrawals' => $withdrawals,
            'filters' => [
                'from_date' => $request->string('from_date')->toString(),
                'to_date' => $request->string('to_date')->toString(),
                'account_id' => $accountId,
            ],
            'hasFilter' => (bool) ($fromG || $toG || $accountId),
            'totalAmount' => $totalAmount,
            'totalCount' => $totalCount,
            'monthTotal' => $monthTotal,
            'accounts' => PaymentAccount::orderBy('title')->get(),
        ]);
    }

    public function create()
    {
        return view('crm::accounting.withdrawals.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateWithdrawal($request);
        $data['created_by'] = auth()->id();

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('crm/owner-withdrawals', 'public');
        }

        $withdrawal = OwnerWithdrawal::create($data);

        return redirect()->route('crm.withdrawals.index')
            ->with('success', 'برداشت سرمایه ثبت شد: '.number_format($withdrawal->amount).' تومان');
    }

    public function edit(OwnerWithdrawal $withdrawal)
    {
        return view('crm::accounting.withdrawals.edit', $this->formData() + ['withdrawal' => $withdrawal]);
    }

    public function update(Request $request, OwnerWithdrawal $withdrawal)
    {
        $data = $this->validateWithdrawal($request);

        if ($request->hasFile('attachment')) {
            if ($withdrawal->attachment_path) {
                Storage::disk('public')->delete($withdrawal->attachment_path);
            }
            $data['attachment_path'] = $request->file('attachment')->store('crm/owner-withdrawals', 'public');
        }

        // ویرایش خودکار در ActivityLog با مقادیرِ قبل/بعد لاگ می‌شود (شنوندهٔ سراسری).
        $withdrawal->update($data);

        return redirect()->route('crm.withdrawals.index')->with('success', 'برداشت سرمایه ویرایش شد.');
    }

    public function destroy(OwnerWithdrawal $withdrawal)
    {
        // SoftDelete — سابقهٔ مالی واقعاً پاک نمی‌شود؛ حذف در ActivityLog لاگ می‌شود.
        $withdrawal->delete();

        return redirect()->route('crm.withdrawals.index')->with('success', 'برداشت سرمایه حذف شد (قابلِ بازیابی از سابقه).');
    }

    /** سرو فایل ضمیمه — پشتِ permission. */
    public function attachment(OwnerWithdrawal $withdrawal)
    {
        abort_unless($withdrawal->attachment_path, 404);
        abort_unless(Storage::disk('public')->exists($withdrawal->attachment_path), 404);

        return response()->file(Storage::disk('public')->path($withdrawal->attachment_path));
    }

    // ─── helpers ─────────────────────────────────────────────────

    private function formData(): array
    {
        return [
            'accounts' => PaymentAccount::active()->orderBy('title')->get(),
            'paymentMethods' => OwnerWithdrawal::PAYMENT_METHODS,
        ];
    }

    /** @return array<string, mixed> */
    private function validateWithdrawal(Request $request): array
    {
        $validated = $request->validate([
            'paid_date' => 'required|string|max:12',
            'paid_time' => 'required|date_format:H:i',
            'amount' => 'required|string|max:20',
            'payment_account_id' => 'required|integer|exists:crm_payment_accounts,id',
            'payee' => 'nullable|string|max:190',
            'description' => 'nullable|string|max:500',
            'payment_method' => 'nullable|in:'.implode(',', array_keys(OwnerWithdrawal::PAYMENT_METHODS)),
            'tracking_number' => 'nullable|string|max:100',
            'attachment' => 'nullable|file|max:8192|mimes:jpg,jpeg,png,webp,pdf',
            'note' => 'nullable|string|max:2000',
        ], [
            'paid_date.required' => 'تاریخ برداشت الزامی است.',
            'paid_time.required' => 'ساعت برداشت الزامی است.',
            'paid_time.date_format' => 'ساعت باید به شکل HH:MM باشد.',
            'amount.required' => 'مبلغ الزامی است.',
            'payment_account_id.required' => 'حساب پرداخت الزامی است.',
            'payment_account_id.exists' => 'حساب پرداخت نامعتبر است.',
            'attachment.max' => 'حجم فایل ضمیمه حداکثر ۸ مگابایت است.',
            'attachment.mimes' => 'فرمت‌های مجاز ضمیمه: jpg, png, webp, pdf.',
        ]);

        $gregorianDate = $this->expenseJalaliToGregorian($validated['paid_date']);
        if (! $gregorianDate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'paid_date' => 'تاریخ شمسی نامعتبر است (مثال درست: 1405/03/21).',
            ]);
        }

        $amount = $this->normalizeAmount($validated['amount']);
        if ($amount === null || $amount <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'مبلغ باید عددی بزرگ‌تر از صفر باشد.',
            ]);
        }

        return [
            'withdrawn_at' => $gregorianDate.' '.$validated['paid_time'].':00',
            'amount' => $amount,
            'payment_account_id' => (int) $validated['payment_account_id'],
            'payee' => $validated['payee'] ?? null,
            'description' => $validated['description'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'tracking_number' => $validated['tracking_number'] ?? null,
            'note' => $validated['note'] ?? null,
        ];
    }
}
