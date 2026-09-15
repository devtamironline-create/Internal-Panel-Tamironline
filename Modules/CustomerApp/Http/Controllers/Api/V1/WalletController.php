<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Enums\CustomerWalletTxType;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\CustomerWalletTransaction;
use Modules\CRM\Models\CustomerWithdrawalRequest;
use Modules\CRM\Models\Payment;
use Modules\CRM\Services\CustomerWalletService;
use Modules\CRM\Services\MellatService;
use Modules\CRM\Services\ZibalService;

/**
 * کیف‌پولِ اپِ مشتری — موجودی + تراکنش‌ها، شارژ از درگاه، و درخواستِ برداشت.
 * هم‌الگو با کیف‌پولِ اپِ تکنسین.
 */
class WalletController extends Controller
{
    public const MIN_TOPUP = 10000;

    public const MAX_TOPUP = 50000000;

    public const DEFAULT_MIN_WITHDRAW = 100000;

    public function __construct(private readonly CustomerWalletService $wallet) {}

    /** GET /v1/customer/wallet — موجودی + تراکنش‌ها. */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $query = CustomerWalletTransaction::query()
            ->where('customer_id', $customer->id)
            ->with(['order:id,order_code']);

        $type = (string) $request->query('type', '');
        if ($type !== '' && CustomerWalletTxType::tryFrom($type)) {
            $query->where('type', $type);
        }

        $tx = $query->latest()->paginate(20)->withQueryString();

        return response()->json([
            'data' => collect($tx->items())->map(fn (CustomerWalletTransaction $t) => [
                'id' => (int) $t->id,
                'type' => $t->type,
                'type_label' => $t->typeLabel(),
                'amount' => (int) $t->amount,
                'balance_after' => (int) $t->balance_after,
                'note' => $t->note,
                'order_code' => $t->order?->order_code,
                'created_at' => $t->created_at?->utc()->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $tx->currentPage(),
                'last_page' => $tx->lastPage(),
                'per_page' => $tx->perPage(),
                'total' => $tx->total(),
            ],
            'balance' => (int) ($customer->wallet_balance ?? 0),
            'min_withdraw' => $this->minWithdraw(),
        ])->header('Cache-Control', 'no-store');
    }

    /** GET /v1/customer/referral — کد معرف + آمار. */
    public function referral(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $rewardSum = (int) CustomerWalletTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', CustomerWalletTxType::ReferralReward->value)
            ->sum('amount');

        return response()->json([
            'data' => [
                'referral_code' => $customer->referralCode(),          // = موبایل
                'referrals_count' => (int) $customer->referrals()->count(),
                'reward_earned' => $rewardSum,
                'reward_per_referral' => app(\Modules\CRM\Services\ReferralService::class)->rewardAmount(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /** POST /v1/customer/wallet/topup — شروعِ شارژ از درگاه. */
    public function topup(Request $request, ZibalService $zibal, MellatService $mellat): JsonResponse
    {
        $customer = $this->customer($request);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:'.self::MIN_TOPUP, 'max:'.self::MAX_TOPUP],
            'return_url' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.min' => 'حداقل مبلغ شارژ '.number_format(self::MIN_TOPUP).' تومان است.',
            'amount.max' => 'حداکثر مبلغ شارژ '.number_format(self::MAX_TOPUP).' تومان است.',
        ]);

        $amount = (int) $validated['amount'];
        $returnUrl = \Modules\CRM\Support\PaymentReturnUrl::sanitize($validated['return_url'] ?? null);
        $callbackUrl = route('crm.payment.callback');
        $gateway = CrmSetting::get('payment_gateway', 'zibal');
        $name = trim((string) $customer->full_name) ?: ('مشتری #'.$customer->id);

        if ($gateway === 'mellat') {
            if (! $mellat->isConfigured()) {
                throw ValidationException::withMessages(['amount' => 'درگاه ملت تنظیم نشده است.']);
            }
            $orderId = (int) (now()->format('ymdHis').random_int(10, 99));
            $response = $mellat->request(amount: $amount, callbackUrl: $callbackUrl, orderId: $orderId);

            Payment::create([
                'customer_id' => $customer->id, 'gateway' => 'mellat', 'purpose' => 'customer_wallet_topup',
                'amount' => $amount, 'track_id' => (string) $orderId, 'return_url' => $returnUrl,
                'status' => $response['success'] ? 'pending' : 'failed',
                'result_message' => $response['message'] ?? null,
                'gateway_response' => ['refId' => $response['refId'] ?? null, 'raw' => $response['raw'] ?? null],
                'requested_at' => now(),
            ]);

            if (! $response['success']) {
                throw ValidationException::withMessages(['amount' => $response['message'] ?? 'خطا در شروع پرداخت ملت.']);
            }

            return response()->json([
                'success' => true,
                'data' => ['gateway' => 'mellat', 'method' => 'POST', 'start_pay_url' => $response['startPayUrl'], 'ref_id' => $response['refId']],
            ]);
        }

        if (! $zibal->isConfigured()) {
            throw ValidationException::withMessages(['amount' => 'درگاه پرداخت تنظیم نشده است.']);
        }
        $orderId = 'CWC-'.$customer->id.'-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        $response = $zibal->request(
            amount: $amount, callbackUrl: $callbackUrl, orderId: $orderId,
            mobile: $customer->mobile, description: 'شارژ کیف‌پول — '.$name,
        );

        Payment::create([
            'customer_id' => $customer->id, 'gateway' => 'zibal', 'purpose' => 'customer_wallet_topup',
            'amount' => $amount, 'track_id' => $response['trackId'] ?? null, 'return_url' => $returnUrl,
            'status' => $response['success'] ? 'pending' : 'failed',
            'result_message' => $response['message'] ?? null,
            'gateway_response' => $response['raw'] ?? null, 'requested_at' => now(),
        ]);

        if (! $response['success']) {
            throw ValidationException::withMessages(['amount' => $response['message'] ?? 'خطا در شروع پرداخت.']);
        }

        return response()->json([
            'success' => true,
            'data' => ['gateway' => 'zibal', 'method' => 'GET', 'payment_url' => $response['paymentUrl']],
        ]);
    }

    /** POST /v1/customer/wallet/withdraw — ثبتِ درخواستِ برداشت (رزرو موجودی). */
    public function withdraw(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $min = $this->minWithdraw();
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:'.$min],
            'sheba' => ['nullable', 'string', 'max:34'],
            'card_number' => ['nullable', 'string', 'max:30'],
            'account_holder' => ['nullable', 'string', 'max:190'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.min' => 'حداقل مبلغ برداشت '.number_format($min).' تومان است.',
        ]);

        if (blank($validated['sheba'] ?? null) && blank($validated['card_number'] ?? null)) {
            throw ValidationException::withMessages(['card_number' => 'شماره کارت یا شبا را وارد کنید.']);
        }

        $amount = (int) $validated['amount'];
        if ($amount > (int) $customer->wallet_balance) {
            throw ValidationException::withMessages(['amount' => 'موجودیِ کیف‌پول کافی نیست.']);
        }

        $req = \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $amount, $validated) {
            // رزرو: مبلغ همین حالا از کیف‌پول کسر می‌شود تا دوباره خرج نشود.
            $tx = $this->wallet->debit($customer, CustomerWalletTxType::Withdrawal, $amount, [
                'note' => 'درخواست برداشت وجه',
            ]);

            return CustomerWithdrawalRequest::create([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'status' => CustomerWithdrawalRequest::STATUS_PENDING,
                'card_number' => $validated['card_number'] ?? null,
                'sheba' => $validated['sheba'] ?? null,
                'account_holder' => $validated['account_holder'] ?? null,
                'customer_note' => $validated['note'] ?? null,
                'debit_tx_id' => $tx->id,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'درخواست برداشت ثبت شد و پس از بررسی واریز می‌شود.',
            'data' => ['id' => $req->id, 'amount' => $req->amount, 'status' => $req->status],
            'balance' => (int) $customer->fresh()->wallet_balance,
        ]);
    }

    /** GET /v1/customer/wallet/withdrawals — لیستِ درخواست‌های برداشت. */
    public function withdrawals(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $rows = CustomerWithdrawalRequest::query()
            ->where('customer_id', $customer->id)
            ->latest()->paginate(20);

        return response()->json([
            'data' => collect($rows->items())->map(fn (CustomerWithdrawalRequest $r) => [
                'id' => (int) $r->id,
                'amount' => (int) $r->amount,
                'status' => $r->status,
                'status_label' => $r->statusLabel(),
                'card_number' => $r->card_number,
                'sheba' => $r->sheba,
                'admin_note' => $r->admin_note,
                'created_at' => $r->created_at?->utc()->toIso8601String(),
                'processed_at' => $r->processed_at?->utc()->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function minWithdraw(): int
    {
        $v = (int) (CrmSetting::get('customer_min_withdrawal') ?? self::DEFAULT_MIN_WITHDRAW);

        return $v > 0 ? $v : self::DEFAULT_MIN_WITHDRAW;
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user();
        abort_unless($customer instanceof Customer, 403, 'دسترسی نامعتبر.');

        return $customer;
    }
}
