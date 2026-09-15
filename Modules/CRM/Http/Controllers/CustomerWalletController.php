<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ActivityLog\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\CustomerWalletTxType;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\CustomerWithdrawalRequest;
use Modules\CRM\Services\CustomerWalletService;

/**
 * مدیریتِ کیف‌پولِ مشتری و درخواست‌های برداشت (پنل ادمین).
 * کلِ کنترلر پشتِ مجوزِ manage-customer-wallet است (در روت‌ها).
 */
class CustomerWalletController extends Controller
{
    public function __construct(private readonly CustomerWalletService $wallet) {}

    /** فهرستِ درخواست‌های برداشت (پیش‌فرض: در انتظار). */
    public function withdrawals(Request $request)
    {
        $status = $request->string('status')->toString() ?: CustomerWithdrawalRequest::STATUS_PENDING;

        $requests = CustomerWithdrawalRequest::query()
            ->with('customer:id,first_name,last_name,mobile,wallet_balance')
            ->when(in_array($status, ['pending', 'paid', 'rejected', 'canceled'], true),
                fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)->withQueryString();

        $counts = CustomerWithdrawalRequest::query()
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('crm::customer-wallet.withdrawals', compact('requests', 'status', 'counts'));
    }

    /** تاییدِ درخواست = واریزِ واقعی انجام شده (مبلغ قبلاً رزرو/کسر شده). */
    public function approveWithdrawal(Request $request, CustomerWithdrawalRequest $withdrawal)
    {
        abort_unless($withdrawal->isPending(), 422, 'این درخواست قبلاً پردازش شده است.');

        $validated = $request->validate(['admin_note' => 'nullable|string|max:1000']);

        $withdrawal->update([
            'status' => CustomerWithdrawalRequest::STATUS_PAID,
            'admin_note' => $validated['admin_note'] ?? null,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        ActivityLog::record('updated', 'تاییدِ برداشتِ مشتری', [
            'entity' => CustomerWithdrawalRequest::class,
            'entity_label' => 'برداشت مشتری',
            'entity_id' => $withdrawal->id,
            'entity_title' => number_format($withdrawal->amount).' تومان — '.($withdrawal->customer?->mobile ?? ''),
        ]);

        return back()->with('success', 'درخواست تایید و به‌عنوان واریزشده ثبت شد.');
    }

    /** ردِ درخواست = بازگرداندنِ مبلغِ رزروشده به کیف‌پولِ مشتری. */
    public function rejectWithdrawal(Request $request, CustomerWithdrawalRequest $withdrawal)
    {
        abort_unless($withdrawal->isPending(), 422, 'این درخواست قبلاً پردازش شده است.');

        $validated = $request->validate(['admin_note' => 'required|string|max:1000'], [
            'admin_note.required' => 'دلیلِ رد را بنویسید.',
        ]);

        DB::transaction(function () use ($withdrawal, $validated) {
            $customer = Customer::find($withdrawal->customer_id);
            $refund = $customer
                ? $this->wallet->credit($customer, CustomerWalletTxType::WithdrawalRefund, (int) $withdrawal->amount, [
                    'note' => 'بازگشتِ برداشتِ ردشده #'.$withdrawal->id,
                    'created_by' => auth()->id(),
                    'meta' => ['withdrawal_id' => $withdrawal->id],
                ])
                : null;

            $withdrawal->update([
                'status' => CustomerWithdrawalRequest::STATUS_REJECTED,
                'admin_note' => $validated['admin_note'],
                'refund_tx_id' => $refund?->id,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
            ]);
        });

        ActivityLog::record('updated', 'ردِ برداشتِ مشتری (بازگشتِ وجه)', [
            'entity' => CustomerWithdrawalRequest::class,
            'entity_label' => 'برداشت مشتری',
            'entity_id' => $withdrawal->id,
            'entity_title' => number_format($withdrawal->amount).' تومان — '.($withdrawal->customer?->mobile ?? ''),
        ]);

        return back()->with('success', 'درخواست رد و مبلغ به کیف‌پولِ مشتری بازگردانده شد.');
    }

    /** تراکنش‌های کیف‌پولِ یک مشتری + فرمِ تعدیلِ دستی. */
    public function show(Customer $customer)
    {
        $transactions = $customer->walletTransactions()
            ->with('creator:id,first_name,last_name')
            ->latest()->paginate(30);

        return view('crm::customer-wallet.show', compact('customer', 'transactions'));
    }

    /** تعدیلِ دستیِ موجودی توسطِ ادمین (+/−). */
    public function adjust(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'direction' => 'required|in:credit,debit',
            'amount' => 'required|integer|min:1',
            'note' => 'required|string|max:500',
        ], [
            'note.required' => 'علتِ تعدیل را بنویسید.',
        ]);

        $signed = $validated['direction'] === 'credit'
            ? (int) $validated['amount']
            : -(int) $validated['amount'];

        $this->wallet->record(
            $customer,
            CustomerWalletTxType::Adjustment,
            $signed,
            note: $validated['note'],
            createdBy: auth()->id(),
        );

        ActivityLog::record('updated', 'تعدیلِ دستیِ کیف‌پولِ مشتری', [
            'entity' => Customer::class,
            'entity_label' => 'کیف‌پول مشتری',
            'entity_id' => $customer->id,
            'entity_title' => ($validated['direction'] === 'credit' ? '+' : '−').number_format((int) $validated['amount']).' — '.$customer->mobile,
        ]);

        return back()->with('success', 'موجودیِ کیف‌پول تعدیل شد.');
    }
}
