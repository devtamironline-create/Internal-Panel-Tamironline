<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\WalletArchiveService;
use Modules\CRM\Services\WalletService;
use Modules\SMS\Services\KavenegarService;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $wallet,
        protected KavenegarService $sms,
    ) {
    }

    public function index(Request $request)
    {
        $technicianId = $request->integer('technician_id');
        $type = $request->string('type')->toString();

        $transactions = WalletTransaction::with(['technician', 'order', 'invoice', 'creator'])
            ->when($technicianId, fn ($q) => $q->where('technician_id', $technicianId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $technicians = Technician::query()
            ->withSum(['invoices' => fn ($q) => $q->where('in_wallet', false)], 'company_share')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'firstname_tech', 'wallet_balance', 'status']);

        // برای کارت‌های بالای صفحه فقط تکنسین‌های فعال (status=active) شمرده می‌شوند.
        $activeTechnicians = $technicians->where('status', 'active');

        // تفکیک: مانده‌های منفی → بدهکار، مانده‌های مثبت → بستانکار،
        // مانده صفر → بدون بدهی. مرتب‌سازی در هر گروه بر اساس قدر مطلق
        // مانده (نزولی) تا تکنسین‌های با بزرگ‌ترین رقم اول دیده شوند.
        $debtors = $activeTechnicians
            ->filter(fn (Technician $t) => (int) $t->true_balance < 0)
            ->sortBy(fn (Technician $t) => (int) $t->true_balance)
            ->values();

        $creditors = $activeTechnicians
            ->filter(fn (Technician $t) => (int) $t->true_balance > 0)
            ->sortByDesc(fn (Technician $t) => (int) $t->true_balance)
            ->values();

        $noDebt = $activeTechnicians
            ->filter(fn (Technician $t) => (int) $t->true_balance === 0)
            ->sortBy(fn (Technician $t) => $t->full_name)
            ->values();

        return view('crm::wallet.index', [
            'transactions' => $transactions,
            'technicians' => $technicians,
            'debtors' => $debtors,
            'creditors' => $creditors,
            'noDebt' => $noDebt,
            'technicianId' => $technicianId,
            'type' => $type,
            'types' => WalletTxType::options(),
        ]);
    }

    /**
     * حذف یک تراکنش کیف‌پول و بازگرداندن مانده تکنسین. عمدی محدود به
     * permission ویژه `delete-wallet-transaction` چون مستقیماً
     * تاریخچهٔ مالی را عوض می‌کند.
     */
    public function destroyTransaction(Technician $technician, WalletTransaction $transaction)
    {
        abort_unless(auth()->user()?->can('delete-wallet-transaction'), 403);

        if ((int) $transaction->technician_id !== (int) $technician->id) {
            abort(404);
        }

        return DB::transaction(function () use ($technician, $transaction) {
            $current = (int) ($technician->wallet_balance ?? 0);
            $technician->forceFill(['wallet_balance' => $current - (int) $transaction->amount])->saveQuietly();

            $note = trim((string) $transaction->note);
            $transaction->delete();

            return back()->with(
                'success',
                'تراکنش حذف شد و مانده تکنسین به‌روزرسانی شد.' . ($note !== '' ? ' (یادداشت قبلی: ' . mb_strimwidth($note, 0, 60, '…') . ')' : '')
            );
        });
    }

    public function show(Technician $technician, WalletArchiveService $archive)
    {
        $transactions = WalletTransaction::with(['order', 'invoice', 'creator'])
            ->where('technician_id', $technician->id)
            ->latest()
            ->paginate(30);

        // تراکنش‌های قدیمی پاک‌شده در reset (فقط نمایش — هرگز در محاسبه
        // وارد نمی‌شود). از فایل JSONL در storage/app/crm خوانده می‌شود.
        $archivedTxs = $archive->getFlatTransactions($technician->id);

        return view('crm::wallet.show', [
            'technician' => $technician,
            'transactions' => $transactions,
            'archivedTxs' => $archivedTxs,
            'types' => WalletTxType::options(),
        ]);
    }

    public function storeTransaction(Request $request, Technician $technician)
    {
        $validated = $request->validate([
            'type' => 'required|in:reward,penalty,payout,credit,adjustment',
            'amount' => 'required|integer|min:1',
            'direction' => 'required_if:type,adjustment|in:credit,debit',
            'note' => 'nullable|string|max:1000',
        ]);

        $type = WalletTxType::from($validated['type']);

        $amount = (int) $validated['amount'];
        $sign = $type->sign();

        if ($type === WalletTxType::Adjustment) {
            $sign = $validated['direction'] === 'credit' ? +1 : -1;
        }

        $this->wallet->recordTransaction(
            technician: $technician,
            type: $type,
            amount: $amount * $sign,
            note: $validated['note'] ?? null,
            createdBy: auth()->id(),
        );

        return back()->with('success', 'تراکنش ثبت شد.');
    }

    /**
     * صفحهٔ «افزودن فاکتور حسابداری» — هم‌ارز add_financial.php در WP CRM.
     * دو فرم در یک صفحه: بستانکاری/بدهکاری و شارژ کیف‌پول. لیست تکنسین
     * فقط external (مطابق WP که type_tech=external فیلتر می‌کند).
     */
    public function addFinancial()
    {
        $technicians = Technician::active()
            ->where('type_tech', 'external')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'firstname_tech', 'mobile']);

        return view('crm::wallet.add-financial', compact('technicians'));
    }

    /**
     * ثبت بستانکاری / بدهکاری — هم‌ارز addFinancialRewardAjax در WP.
     *  reward_type: 1 = بستانکاری (reward, +)، 0 = بدهکاری (penalty, -)
     */
    public function storeReward(Request $request)
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'integer', 'exists:crm_technicians,id'],
            'reward_type' => ['required', 'in:0,1'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'technician_id.required' => 'تکنسین را انتخاب کنید.',
            'reward_type.required' => 'نوع را انتخاب کنید.',
            'amount.required' => 'مبلغ الزامی است.',
        ]);

        $technician = Technician::findOrFail($validated['technician_id']);
        $type = (string) $validated['reward_type'] === '1' ? WalletTxType::Reward : WalletTxType::Penalty;
        $amount = (int) $validated['amount'] * $type->sign();

        $this->wallet->recordTransaction(
            technician: $technician,
            type: $type,
            amount: $amount,
            note: $validated['description'] ?? null,
            createdBy: auth()->id(),
        );

        $label = $type === WalletTxType::Reward ? 'بستانکاری' : 'بدهکاری';
        return back()->with('success', $label . ' برای ' . $technician->full_name . ' ثبت شد.');
    }

    /**
     * شارژ کیف‌پول تکنسین — هم‌ارز addFinancialAjax در WP. SMS اطلاع‌رسانی
     * با template tech_wallet_charge ارسال می‌شود (با مبلغ به عنوان token2).
     */
    public function storeCharge(Request $request)
    {
        $validated = $request->validate([
            'technician_id' => ['required', 'integer', 'exists:crm_technicians,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'technician_id.required' => 'تکنسین را انتخاب کنید.',
            'amount.required' => 'مبلغ الزامی است.',
        ]);

        $technician = Technician::findOrFail($validated['technician_id']);
        $amount = (int) $validated['amount'];

        $this->wallet->recordTransaction(
            technician: $technician,
            type: WalletTxType::WalletCharge,
            amount: $amount,
            note: $validated['description'] ?? null,
            createdBy: auth()->id(),
        );

        // SMS اطلاع‌رسانی شارژ — مطابق WP. خرابی SMS نباید تراکنش را شکست‌بده.
        if ($technician->mobile) {
            try {
                $this->sms->sendTemplate(
                    $technician->mobile,
                    'tech_wallet_charge',
                    [
                        'token' => $technician->first_name ?: $technician->full_name,
                        'token2' => number_format($amount) . 'تومان',
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('crm.wallet.charge_sms_failed', [
                    'technician_id' => $technician->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', 'کیف‌پول ' . $technician->full_name . ' به مبلغ ' . number_format($amount) . ' تومان شارژ شد.');
    }
}
