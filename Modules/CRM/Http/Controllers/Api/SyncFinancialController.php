<?php

namespace Modules\CRM\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\CommissionCalculator;
use Throwable;

/**
 * Endpoint سینک رکوردهای مالی از CRM وردپرسی (post_type=financial).
 *
 * در WP CRM یک post_type=financial چندمنظوره است: همان پست بسته به
 * متاهای wallet/refid/reward_type/total_invoice می‌تواند در لاراول
 * یکی از این‌ها باشد:
 *   - فاکتور سفارش      → crm_invoices
 *   - شارژ کیف‌پول       → crm_tech_wallet_transactions (type=credit)
 *   - جایزه/جریمه       → crm_tech_wallet_transactions (reward/penalty)
 *
 * این کنترلر نوع را تشخیص داده و به جدول مناسب می‌نویسد. تطبیق همیشه
 * با wp_id (post_id WP) است.
 */
class SyncFinancialController extends Controller
{
    public function __construct(protected CommissionCalculator $calc)
    {
    }

    /** سینک تکی — POST /api/crm/sync/financial */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $result = $this->upsertOne($data);

        return response()->json(['ok' => true] + $result);
    }

    /** سینک دسته‌ای — POST /api/crm/sync/financials/batch */
    public function batch(Request $request): JsonResponse
    {
        $batchRules = ['items' => 'required|array|min:1|max:100'];
        foreach ($this->rules() as $key => $rule) {
            $batchRules["items.*.{$key}"] = $rule;
        }
        $data = $request->validate($batchRules);

        $created = 0;
        $updated = 0;
        $skipped = [];
        $errors = [];

        foreach ($data['items'] as $i => $item) {
            try {
                $r = $this->upsertOne($item);
                match ($r['action']) {
                    'created' => $created++,
                    'updated' => $updated++,
                    'skipped' => $skipped[] = [
                        'index' => $i,
                        'wp_id' => $r['wp_id'],
                        'reason' => $r['reason'] ?? 'unknown',
                        // یک نگاه سریع به فیلدهای کلیدی برای دیباگ از سمت WP
                        'snapshot' => [
                            'order_wp_id' => $item['order_wp_id'] ?? null,
                            'total_invoice' => $item['total_invoice'] ?? null,
                            'price_customer' => $item['price_customer'] ?? null,
                            'wallet' => $item['wallet'] ?? null,
                            'reward_type' => $item['reward_type'] ?? null,
                        ],
                    ],
                    default => null,
                };
            } catch (Throwable $e) {
                $errors[] = [
                    'index' => $i,
                    'wp_id' => $item['wp_id'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'total' => count($data['items']),
            'created' => $created,
            'updated' => $updated,
            'skipped' => count($skipped),
            'skipped_items' => $skipped,
            'errors' => $errors,
        ]);
    }

    protected function rules(): array
    {
        return [
            'wp_id' => 'required|integer|min:1',

            // مرجع‌ها (wp_id متناظر)
            'order_wp_id' => 'nullable|integer|min:1',
            'customer_wp_id' => 'nullable|integer|min:1',
            'technician_wp_id' => 'nullable|integer|min:1',

            // مالی
            'price_customer' => 'nullable|integer',
            'cost_price' => 'nullable|integer',
            'total_invoice' => 'nullable|integer',
            'wallet' => 'nullable|boolean',
            'wallet_pay' => 'nullable|integer',
            'refid' => 'nullable|string|max:255',
            'reward_type' => 'nullable|integer|in:0,1',
            'reward_desc' => 'nullable|string|max:1000',
            'description' => 'nullable|string',
            'invoice_descripotion' => 'nullable|string',
            'payment_status' => 'nullable|integer',

            // پست — post_date گاهی '0000-00-00 00:00:00' است، پس
            // به‌صورت string می‌گیریم و در upsertInvoice با rescue parse می‌کنیم.
            'post_title' => 'nullable|string|max:255',
            'post_date' => 'nullable|string|max:32',
        ];
    }

    /**
     * @return array{action:'created'|'updated'|'skipped', type:string, id:?int, wp_id:int, ...}
     */
    protected function upsertOne(array $data): array
    {
        $wpId = (int) $data['wp_id'];
        $type = $this->detectType($data);

        if ($type === null) {
            return [
                'action' => 'skipped',
                'type' => 'unknown',
                'id' => null,
                'wp_id' => $wpId,
                'reason' => 'no recognizable financial signature (wallet/reward/order)',
            ];
        }

        return DB::transaction(function () use ($wpId, $data, $type) {
            if ($type === 'invoice') {
                return $this->upsertInvoice($wpId, $data);
            }

            return $this->upsertWalletTx($wpId, $data, $type);
        });
    }

    /**
     * تشخیص نوع از روی متادیتا — ترتیب اولویت:
     *   1) wallet=1 → wallet_credit
     *   2) reward_type ∈ {0,1} → reward/penalty
     *   3) order_wp_id > 0 → invoice (حتی اگر total=0؛ در WP فاکتور
     *      با مبلغ صفر هم وجود دارد و باید سینک شود)
     */
    protected function detectType(array $data): ?string
    {
        $wallet = filter_var($data['wallet'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $rewardType = $data['reward_type'] ?? null;
        $orderWpId = (int) ($data['order_wp_id'] ?? 0);

        if ($wallet) {
            return 'wallet_credit';
        }

        if ($rewardType !== null && $rewardType !== '') {
            return ((int) $rewardType === 1) ? 'reward' : 'penalty';
        }

        if ($orderWpId > 0) {
            return 'invoice';
        }

        return null;
    }

    protected function upsertInvoice(int $wpId, array $data): array
    {
        $orderWpId = (int) $data['order_wp_id'];
        $order = Order::where('wp_id', $orderWpId)->first();
        if (! $order) {
            throw new \RuntimeException("order not synced (wp_id={$orderWpId})");
        }

        $invoice = Invoice::where('wp_id', $wpId)->lockForUpdate()->first();

        $isPaid = ((int) ($data['payment_status'] ?? 0)) === 1;
        $issuedAt = $this->safeDate($data['post_date'] ?? null);

        // در WP CRM، فاکتور برای مشتری price_customer است (جمع کل صورت
        // حساب). total_invoice همان «مانده» پس از کسر هزینه‌هاست. بنابراین
        // مبلغ فاکتور را از price_customer می‌گیریم و در نبود آن fallback
        // به total_invoice می‌کنیم.
        $totalAmount = (int) ($data['price_customer'] ?? 0);
        if ($totalAmount === 0) {
            $totalAmount = (int) ($data['total_invoice'] ?? 0);
        }

        // محاسبه سهم تکنسین/شرکت همان لحظه — اگر سفارش تکنسین داشته باشد
        // و مبلغی هم ست شده باشد، طبق منطق CommissionCalculator با همان
        // مبلغ صریح فاکتور (نه مبلغ سفارش) محاسبه می‌شود.
        $techShare = 0;
        $companyShare = $totalAmount;
        $percent = 0;
        $calcType = null;

        if ($totalAmount > 0 && $order->technician_id) {
            $technician = $order->technician;
            if ($technician) {
                $totals = $this->calc->calculate($order, $technician, $totalAmount);
                $techShare = $totals['tech_share'];
                $companyShare = $totals['company_share'];
                $percent = $totals['percent'];
                $calcType = $totals['calc_type'] !== '' ? $totals['calc_type'] : null;
            }
        }

        $payload = [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'technician_id' => $order->technician_id,
            'total_amount' => $totalAmount,
            'tech_share' => $techShare,
            'company_share' => $companyShare,
            'commission_percent' => $percent,
            'calc_type' => $calcType,
            'status' => $isPaid ? 'paid' : 'issued',
            'issued_at' => $issuedAt,
            'paid_at' => $isPaid ? $issuedAt : null,
        ];

        if ($invoice) {
            $invoice->fill($payload);
            $invoice->save();
            $action = 'updated';
        } else {
            $payload['wp_id'] = $wpId;
            $payload['invoice_code'] = Invoice::generateInvoiceCode();
            $invoice = Invoice::create($payload);
            $action = 'created';
        }

        return [
            'action' => $action,
            'type' => 'invoice',
            'id' => (int) $invoice->id,
            'wp_id' => $wpId,
            'invoice_code' => (string) $invoice->invoice_code,
        ];
    }

    /**
     * upsert تراکنش کیف‌پول. type ∈ {wallet_credit, reward, penalty}.
     * علامت amount از روی WalletTxType::sign() محاسبه می‌شود.
     *
     * منطق هم‌ارز با DebtCalc() در libs/order.php سمت WP:
     *  - فقط رویدادهای پرداخت‌شده (payment_status = 1) به کیف‌پول می‌روند
     *  - شارژ کیف‌پول → امضا با مبلغ wallet_pay
     *  - جایزه/جریمه → امضا با مبلغ total_invoice (نه wallet_pay)
     *
     * balance_after در ابتدا یک اعتبار محلی است؛ بعد از sync کامل،
     * artisan crm:wallet:recompute-balances همهٔ ردیف‌ها را به ترتیب id
     * بازخوانی و running balance را بازنویسی می‌کند تا اطمینان حاصل شود
     * هیچ ناسازگاری در ترتیب باقی نمی‌ماند.
     */
    protected function upsertWalletTx(int $wpId, array $data, string $type): array
    {
        // فیلتر اول: payment_status = 1 — وگرنه این رخداد به کیف‌پول
        // وارد نمی‌شود (در WP فقط رخدادهای پرداخت‌شده محاسبه می‌شوند).
        if ((int) ($data['payment_status'] ?? 0) !== 1) {
            return [
                'action' => 'skipped',
                'type' => 'wallet_' . $type,
                'id' => null,
                'wp_id' => $wpId,
                'reason' => 'payment_status != 1 (unpaid wallet event)',
            ];
        }

        // resolve technician — اول از technician_wp_id، در نبود از order
        $technician = null;
        $order = null;

        if (! empty($data['technician_wp_id'])) {
            $technician = Technician::where('wp_id', (int) $data['technician_wp_id'])->first();
        }
        if (! empty($data['order_wp_id'])) {
            $order = Order::where('wp_id', (int) $data['order_wp_id'])->first();
            if ($order && ! $technician) {
                $technician = $order->technician;
            }
        }

        if (! $technician) {
            throw new \RuntimeException('technician not resolved for wallet transaction');
        }

        // تشخیص enum + علامت — مهم: wallet=1 در WP یعنی «شارژ کیف‌پول»
        // (شرکت → تکنسین، علامت مثبت). به WalletCharge می‌رود نه Credit
        // (که برای ثبت دستی «واریز تکنسین به شرکت» با علامت منفی است).
        $txType = match ($type) {
            'wallet_credit' => WalletTxType::WalletCharge,
            'reward' => WalletTxType::Reward,
            'penalty' => WalletTxType::Penalty,
        };

        // مبلغ از منبع درست — مطابق WP DebtCalc:
        //   wallet_credit → wallet_pay
        //   reward/penalty → total_invoice
        $amountSource = $type === 'wallet_credit'
            ? (int) ($data['wallet_pay'] ?? 0)
            : (int) ($data['total_invoice'] ?? 0);
        $amountAbs = abs($amountSource);
        $signedAmount = $amountAbs * $txType->sign();

        $note = $this->buildNote($data, $type);

        $existing = WalletTransaction::where('wp_id', $wpId)->lockForUpdate()->first();

        if ($existing) {
            // ردیف موجود را به‌روز می‌کنیم — شامل فیلدهای مالی، چون داده
            // قبلی با منطق غلط ذخیره شده بود و باید تصحیح شود. balance_after
            // همین‌جا فعلاً با previousBalance + amount نوشته می‌شود ولی
            // پس از پایان sync، crm:wallet:recompute-balances نهایی‌اش
            // می‌کند تا running total درست از ابتدا تا انتها یک‌جا به‌روز
            // شود.
            $existing->update([
                'order_id' => $order?->id,
                'type' => $txType,
                'amount' => $signedAmount,
                'note' => $note,
            ]);

            return [
                'action' => 'updated',
                'type' => 'wallet_' . $type,
                'id' => (int) $existing->id,
                'wp_id' => $wpId,
                'amount' => $signedAmount,
            ];
        }

        // محاسبه balance_after به‌صورت running total روی همان تکنسین
        $previousBalance = (int) (WalletTransaction::where('technician_id', $technician->id)
            ->orderByDesc('id')
            ->value('balance_after') ?? 0);

        $tx = WalletTransaction::create([
            'wp_id' => $wpId,
            'technician_id' => $technician->id,
            'order_id' => $order?->id,
            'invoice_id' => null,
            'type' => $txType,
            'amount' => $signedAmount,
            'balance_after' => $previousBalance + $signedAmount,
            'note' => $note,
        ]);

        // sync کش wallet_balance روی technician
        $technician->forceFill(['wallet_balance' => $tx->balance_after])->save();

        return [
            'action' => 'created',
            'type' => 'wallet_' . $type,
            'id' => (int) $tx->id,
            'wp_id' => $wpId,
            'amount' => $signedAmount,
        ];
    }

    /** parse امن تاریخ — '0000-00-00...' و رشته‌های نامعتبر را به null می‌بَرد. */
    protected function safeDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (str_starts_with($value, '0000-')) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildNote(array $data, string $type): ?string
    {
        $parts = [];

        if (! empty($data['reward_desc'])) {
            $parts[] = (string) $data['reward_desc'];
        }
        if (! empty($data['description'])) {
            $parts[] = (string) $data['description'];
        }
        if (! empty($data['refid'])) {
            $parts[] = 'refid: ' . $data['refid'];
        }

        return empty($parts) ? null : implode(' — ', $parts);
    }
}
