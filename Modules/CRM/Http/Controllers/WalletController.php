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
     *
     * منطق:
     *   ۱) تراکنش حذف می‌شود
     *   ۲) wallet_balance تکنسین از روی **مجموع همهٔ تراکنش‌های باقی‌مانده**
     *      دوباره محاسبه می‌شود (نه delta) تا dirft انباشته نشود
     *   ۳) یک ردیف audit با amount=0 و یادداشت توضیحی ثبت می‌شود تا
     *      تاریخچهٔ حذف برای همیشه باقی بماند
     */
    public function destroyTransaction(Technician $technician, WalletTransaction $transaction)
    {
        abort_unless(auth()->user()?->can('delete-wallet-transaction'), 403);

        if ((int) $transaction->technician_id !== (int) $technician->id) {
            abort(404);
        }

        return DB::transaction(function () use ($technician, $transaction) {
            // snapshot قبل از حذف برای audit log
            $deletedAmount = (int) $transaction->amount;
            $deletedType = $transaction->type instanceof WalletTxType
                ? $transaction->type->value
                : (string) $transaction->type;
            $deletedTypeLabel = $transaction->type instanceof WalletTxType
                ? $transaction->type->label()
                : $deletedType;
            $deletedNote = trim((string) $transaction->note);
            $deletedId = $transaction->id;
            $deletedInvoiceId = $transaction->invoice_id;
            $deletedOrderId = $transaction->order_id;
            $deletedAt = \Morilog\Jalali\Jalalian::now()->format('Y/m/d H:i');
            $whoUser = auth()->user();
            $whoName = trim(($whoUser->first_name ?? '') . ' ' . ($whoUser->last_name ?? '')) ?: ('user#' . auth()->id());

            // ۱) حذف
            $transaction->delete();

            // ۲) بازمحاسبهٔ ستون «موجودی» (balance_after) برای *تمام*
            //    تراکنش‌های باقی‌ماندهٔ این تکنسین به ترتیب id (یعنی
            //    ترتیب ساخت). این کار مقادیر قدیمیِ stale را که از
            //    باگ‌های قدیمی مانده بود همگام می‌کند، طوری که هر ردیف
            //    دقیقاً مجموع تراکنش‌های قبل از خودش + amount خودش را
            //    نشان دهد.
            $running = 0;
            $rows = WalletTransaction::where('technician_id', $technician->id)
                ->orderBy('id')
                ->get();
            foreach ($rows as $tx) {
                $running += (int) $tx->amount;
                if ((int) $tx->balance_after !== $running) {
                    $tx->forceFill(['balance_after' => $running])->saveQuietly();
                }
            }
            $newBalance = $running;

            // ۳) به‌روزرسانی wallet_balance تکنسین
            $technician->forceFill(['wallet_balance' => $newBalance])->saveQuietly();

            // ۴) ردیف audit (amount=0 تا balance تغییر نکند)
            WalletTransaction::create([
                'technician_id' => $technician->id,
                'order_id' => $deletedOrderId,
                'invoice_id' => $deletedInvoiceId,
                'wp_id' => null,
                'type' => WalletTxType::Adjustment->value,
                'amount' => 0,
                'balance_after' => $newBalance,
                'note' => sprintf(
                    '[حذف توسط %s در %s] تراکنش #%d حذف شد — مبلغ: %s، نوع: %s%s',
                    $whoName, $deletedAt, $deletedId,
                    number_format($deletedAmount),
                    $deletedTypeLabel,
                    $deletedNote !== '' ? ' — یادداشت قبلی: ' . $deletedNote : ''
                ),
                'created_by' => auth()->id(),
            ]);

            return back()->with(
                'success',
                'تراکنش حذف شد، ستون «موجودی» همهٔ ردیف‌ها بازمحاسبه شد و ردیف audit ثبت شد.'
            );
        });
    }

    /**
     * ارسال OTP برای تأیید حذف کامل (hard delete) تراکنش — فقط به
     * موبایل کاربر فعلی، با TTL کوتاه و گیت permission سخت‌گیر.
     */
    public function requestHardDeleteOtp()
    {
        abort_unless(auth()->user()?->can('hard-delete-wallet-transaction'), 403);

        $user = auth()->user();
        $mobile = (string) ($user->mobile ?? '');
        if (! preg_match('/^09[0-9]{9}$/', $mobile)) {
            return response()->json(['success' => false, 'message' => 'شماره موبایل معتبر روی حساب شما ثبت نشده.'], 422);
        }

        // محدودسازی نرخ — حداکثر هر ۶۰ ثانیه یک OTP
        $rateKey = "admin_hard_delete_otp_last_{$user->id}";
        if ($last = cache($rateKey)) {
            $wait = 60 - (time() - (int) $last);
            if ($wait > 0) {
                return response()->json(['success' => false, 'message' => "لطفاً {$wait} ثانیه صبر کنید."], 429);
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        cache()->put("admin_hard_delete_otp_{$user->id}", [
            'code' => $code,
            'attempts' => 0,
        ], now()->addMinutes(2));
        cache()->put($rateKey, time(), 60);

        // از همان تمپلیتِ تأییدشدهٔ Kavenegar (`verify`) استفاده می‌شود —
        // دقیقاً همان الگوی OTP لاگین اپراتور و تکنسین. این روش از
        // verify-lookup با sender معتبر استفاده می‌کند و proxy SMS را
        // هم خودش بر اساس تنظیمات اعمال می‌کند.
        $template = config('sms.templates.otp', 'verify');
        try {
            $result = $this->sms->sendTemplate($mobile, $template, ['token' => $code]);
        } catch (\Throwable $e) {
            Log::warning('Hard-delete OTP SMS exception', ['user' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'خطای ارسال پیامک: ' . $e->getMessage()], 502);
        }

        if (empty($result['success'])) {
            Log::warning('Hard-delete OTP SMS failed', ['user' => $user->id, 'result' => $result]);
            return response()->json(['success' => false, 'message' => 'ارسال پیامک ناموفق: ' . ($result['message'] ?? 'خطای ناشناخته')], 502);
        }

        return response()->json([
            'success' => true,
            'message' => 'کد تأیید به موبایل ' . substr($mobile, 0, 4) . '***' . substr($mobile, -3) . ' ارسال شد.',
        ]);
    }

    /**
     * حذف کامل تراکنش پس از تأیید OTP — هیچ ردیف audit ساخته نمی‌شود
     * و رکورد دقیقاً از DB پاک می‌شود (برخلاف destroyTransaction).
     */
    public function hardDeleteTransaction(Request $request, Technician $technician, WalletTransaction $transaction)
    {
        abort_unless(auth()->user()?->can('hard-delete-wallet-transaction'), 403);

        if ((int) $transaction->technician_id !== (int) $technician->id) {
            abort(404);
        }

        $validated = $request->validate([
            'otp' => 'required|string|regex:/^\d{6}$/',
        ], ['otp.required' => 'کد تأیید الزامی است.', 'otp.regex' => 'کد باید ۶ رقم باشد.']);

        $userId = auth()->id();
        $cacheKey = "admin_hard_delete_otp_{$userId}";
        $entry = cache()->get($cacheKey);
        if (! is_array($entry) || empty($entry['code'])) {
            return back()->with('error', 'کد تأیید منقضی شده. دوباره درخواست بدهید.');
        }

        $attempts = (int) ($entry['attempts'] ?? 0);
        if ($attempts >= 5) {
            cache()->forget($cacheKey);
            return back()->with('error', 'تعداد تلاش بیش از حد. کد جدید درخواست کنید.');
        }

        if (! hash_equals((string) $entry['code'], (string) $validated['otp'])) {
            cache()->put($cacheKey, ['code' => $entry['code'], 'attempts' => $attempts + 1], now()->addMinutes(2));
            return back()->with('error', 'کد تأیید اشتباه است.');
        }

        // کد درست — مصرف کن
        cache()->forget($cacheKey);

        return DB::transaction(function () use ($technician, $transaction) {
            $transaction->delete();

            // بازمحاسبهٔ balance_after تمام ردیف‌های باقی‌مانده + wallet_balance
            $running = 0;
            $rows = WalletTransaction::where('technician_id', $technician->id)
                ->orderBy('id')->get();
            foreach ($rows as $tx) {
                $running += (int) $tx->amount;
                if ((int) $tx->balance_after !== $running) {
                    $tx->forceFill(['balance_after' => $running])->saveQuietly();
                }
            }
            $technician->forceFill(['wallet_balance' => $running])->saveQuietly();

            return back()->with('success', 'تراکنش به‌صورت کامل و دائمی حذف شد.');
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
