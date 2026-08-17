<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Payment;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\MellatService;
use Modules\CRM\Services\ZibalService;

/**
 * کیف‌پولِ اپِ تکنسین — تراکنش‌ها + شارژ از درگاه (Zibal/ملت).
 * هم‌ترازِ Tech\DashboardController::wallet / walletRechargeInitiate.
 */
class WalletController extends Controller
{
    /** GET /v1/technician/wallet?type=&page= */
    public function index(Request $request): JsonResponse
    {
        $tech = $request->user();

        $base = WalletTransaction::query()
            ->where('technician_id', $tech->id)
            ->where('type', '!=', WalletTxType::Adjustment->value);

        $stats = [
            'commission_sum' => (int) (clone $base)->where('type', WalletTxType::Commission->value)->sum('amount'),
            'reward_sum' => (int) (clone $base)->where('type', WalletTxType::Reward->value)->sum('amount'),
            'penalty_sum' => (int) (clone $base)->where('type', WalletTxType::Penalty->value)->sum('amount'),
            'charge_sum' => (int) (clone $base)->where('type', WalletTxType::WalletCharge->value)->sum('amount'),
        ];

        $query = (clone $base)->with(['order:id,order_code']);
        $type = (string) $request->query('type', '');
        if ($type !== '' && WalletTxType::tryFrom($type)) {
            $query->where('type', $type);
        }

        $tx = $query->latest()->paginate(15)->withQueryString();
        $tech->loadSum(['invoices' => fn ($q) => $q->where('in_wallet', false)], 'company_share');

        return response()->json([
            'success' => true,
            'data' => collect($tx->items())->map(fn (WalletTransaction $t) => [
                'id' => (int) $t->id,
                'type' => $t->type?->value,
                'type_label' => $t->type?->label(),
                'amount' => (int) $t->amount,
                'description' => $t->description,
                'order_code' => $t->order?->order_code,
                'created_at' => $t->created_at?->utc()->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $tx->currentPage(),
                'last_page' => $tx->lastPage(),
                'per_page' => $tx->perPage(),
                'total' => $tx->total(),
            ],
            'stats' => $stats,
            // balance = «اعتبار» کیف‌پول (همان عددِ پنل ادمین — running sum
            // تراکنش‌ها)؛ true_balance = balance منهای بدهی فاکتوری.
            'balance' => (int) ($tech->wallet_balance ?? 0),
            'true_balance' => (int) $tech->true_balance,
            'invoice_debt' => (int) ($tech->invoice_debt ?? 0),
        ]);
    }

    /** POST /v1/technician/wallet/recharge — شروعِ پرداختِ شارژ. */
    public function recharge(Request $request, ZibalService $zibal, MellatService $mellat): JsonResponse
    {
        $tech = $request->user();

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:500000', 'max:50000000'],
            // لینکِ برگشت به اپ بعد از پرداخت — از allowlist عبور می‌کند.
            'return_url' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.min' => 'حداقل مبلغ شارژ ۵۰۰٬۰۰۰ تومان است.',
            'amount.max' => 'حداکثر مبلغ شارژ ۵۰٬۰۰۰٬۰۰۰ تومان است.',
        ]);

        $amount = (int) $validated['amount'];
        $returnUrl = \Modules\CRM\Support\PaymentReturnUrl::sanitize($validated['return_url'] ?? null);
        $callbackUrl = route('crm.payment.callback');
        $gateway = CrmSetting::get('payment_gateway', 'zibal');
        $techName = trim((string) $tech->display_name) ?: ('تکنسین #'.$tech->id);

        if ($gateway === 'mellat') {
            if (! $mellat->isConfigured()) {
                throw ValidationException::withMessages(['amount' => 'درگاه ملت تنظیم نشده است.']);
            }
            $orderId = (int) (now()->format('ymdHis').random_int(10, 99));
            $response = $mellat->request(amount: $amount, callbackUrl: $callbackUrl, orderId: $orderId);

            Payment::create([
                'technician_id' => $tech->id, 'gateway' => 'mellat', 'purpose' => 'wallet_charge',
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
                'data' => [
                    'gateway' => 'mellat',
                    'method' => 'POST',
                    'start_pay_url' => $response['startPayUrl'],
                    'ref_id' => $response['refId'],
                ],
            ]);
        }

        // Zibal (پیش‌فرض) — redirectِ GET
        if (! $zibal->isConfigured()) {
            throw ValidationException::withMessages(['amount' => 'درگاه پرداخت تنظیم نشده است.']);
        }
        $orderId = 'TWC-'.$tech->id.'-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        $response = $zibal->request(
            amount: $amount, callbackUrl: $callbackUrl, orderId: $orderId,
            mobile: $tech->mobile, description: 'شارژ کیف‌پول — '.$techName,
        );

        Payment::create([
            'technician_id' => $tech->id, 'gateway' => 'zibal', 'purpose' => 'wallet_charge',
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
}
