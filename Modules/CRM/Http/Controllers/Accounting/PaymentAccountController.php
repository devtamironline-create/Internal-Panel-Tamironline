<?php

namespace Modules\CRM\Http\Controllers\Accounting;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Expense;
use Modules\CRM\Models\PaymentAccount;

/**
 * مدیریت حساب‌های پرداخت شرکت — فقط مدیر (manage-crm-costs).
 */
class PaymentAccountController extends Controller
{
    public function index()
    {
        $accounts = PaymentAccount::withCount('expenses')
            ->orderBy('title')
            ->get();

        return view('crm::accounting.accounts.index', [
            'accounts' => $accounts,
            'types'    => PaymentAccount::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateAccount($request);

        PaymentAccount::create($validated + ['is_active' => true]);

        return back()->with('success', 'حساب «' . $validated['title'] . '» ساخته شد.');
    }

    public function update(Request $request, PaymentAccount $account)
    {
        $validated = $this->validateAccount($request);
        $validated['is_active'] = $request->boolean('is_active');

        $account->update($validated);

        return back()->with('success', 'حساب ویرایش شد.');
    }

    public function destroy(PaymentAccount $account)
    {
        if ($account->expenses()->exists()) {
            return back()->with('error', 'این حساب در هزینه‌های ثبت‌شده استفاده شده و قابل حذف نیست — می‌توانید غیرفعالش کنید.');
        }

        $account->delete();

        return back()->with('success', 'حساب حذف شد.');
    }

    private function validateAccount(Request $request): array
    {
        return $request->validate([
            'title'       => 'required|string|max:190',
            'type'        => 'required|in:' . implode(',', array_keys(PaymentAccount::TYPES)),
            'description' => 'nullable|string|max:500',
        ], [
            'title.required' => 'عنوان حساب را وارد کنید.',
            'type.required'  => 'نوع حساب را انتخاب کنید.',
            'type.in'        => 'نوع حساب نامعتبر است.',
        ]);
    }
}
