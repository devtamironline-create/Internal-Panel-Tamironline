<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\WalletTransaction;

/**
 * تولید فاکتور از سفارش — هم‌سو با مدل مالی WP CRM.
 *
 * بر خلاف نسخه قبلی، این سرویس هیچ تراکنش کیف‌پول از نوع Commission
 * ثبت نمی‌کند. در پنل WP، سهم تکنسین (tech_share) به‌عنوان «اعتبار»
 * در کیف‌پول وارد نمی‌شود — تکنسین آن را به‌صورت نقدی از مشتری
 * می‌گیرد. کیف‌پول فقط شامل: شارژ شرکت → تکنسین، پاداش، جریمه،
 * پرداخت‌ها/برداشت‌ها است. مانده نهایی = wallet_balance − sum(company_share).
 *
 * اگر در نسخه قبلی Commission تراکنش‌هایی ساخته شده‌اند، باید با
 * crm:invoices:recompute پاک‌سازی شوند تا مانده با WP همخوان شود.
 *
 * idempotent: اگر سفارش از قبل فاکتور دارد، همان برمی‌گردد.
 */
class InvoiceService
{
    public function __construct(protected CommissionCalculator $calc) {}

    public function generateForOrder(Order $order, ?int $createdBy = null, bool $forceRegenerate = false): ?Invoice
    {
        // اگر سفارش از قبل فاکتور active دارد:
        //   - بدون forceRegenerate: همان برمی‌گردد (idempotent برای double-click)
        //   - با forceRegenerate=true: فاکتور قبلی superseded و فاکتور جدید
        //     با مقادیر فعلی Order ساخته می‌شود. این برای موقعی است که
        //     تکنسین قیمت/قطعات را عوض کرده و دوباره Complete زده.
        $existing = Invoice::where('order_id', $order->id)->first();
        if ($existing && ! $forceRegenerate) {
            return $existing;
        }

        $invoice = DB::transaction(function () use ($order, $createdBy, $existing) {
            if ($existing) {
                // فقط فاکتور قبلی را superseded می‌کنیم تا از لیست
                // فعال خارج شود. **wallet tx آن را دست نمی‌زنیم** —
                // طبق درخواست، فاکتور جدید کاملاً مجزا ثبت می‌شود و
                // commission قبلی روی بدهی تکنسین باقی می‌ماند.
                // تاریخچهٔ کامل (فاکتور قدیمی + tx قدیمی + فاکتور جدید
                // + tx جدید) همگی در DB می‌مانند و قابل مشاهده‌اند.
                Invoice::withoutGlobalScope('active')
                    ->where('order_id', $order->id)
                    ->whereNull('superseded_at')
                    ->update(['superseded_at' => now()]);
            }

            $technician = $order->technician;

            $totals = $technician
                ? $this->calc->calculate($order, $technician)
                : ['total' => (int) ($order->final_price ?? $order->items_subtotal ?? 0),
                    'tech_share' => 0, 'company_share' => (int) ($order->final_price ?? $order->items_subtotal ?? 0),
                    'percent' => 0, 'calc_type' => null];

            $invoice = Invoice::create([
                'invoice_code' => Invoice::generateInvoiceCode(),
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'technician_id' => $order->technician_id,
                'total_amount' => $totals['total'],
                'tech_share' => $totals['tech_share'],
                'company_share' => $totals['company_share'],
                'calc_type' => $totals['calc_type'],
                'commission_percent' => $totals['percent'],
                'status' => 'issued',
                'issued_at' => now(),
                'created_by' => $createdBy,
                'in_wallet' => false, // ابتدا false، بعد از ساختن wallet tx → true
            ]);

            // ─── ثبت تراکنش کیف‌پول برای سهم شرکت ─────────────────
            // برای فاکتورهای جدید، یک wallet tx با مقدار -company_share
            // ثبت می‌شود تا اثرش روی کیف‌پول تکنسین قابل ردیابی باشد.
            // invoice_debt این فاکتورها صفر است (در getInvoiceDebt
            // فیلتر می‌شود) تا double-count نشود.
            if ($invoice->technician_id && (int) $invoice->company_share > 0) {
                $last = (int) (WalletTransaction::where('technician_id', $invoice->technician_id)
                    ->orderByDesc('id')->value('balance_after') ?? 0);
                $amount = -1 * (int) $invoice->company_share;

                WalletTransaction::create([
                    'technician_id' => $invoice->technician_id,
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'wp_id' => null,
                    'type' => WalletTxType::Commission->value,
                    'amount' => $amount,
                    'balance_after' => $last + $amount,
                    'note' => 'سهم شرکت از فاکتور '.$invoice->invoice_code,
                    'created_by' => $createdBy,
                ]);

                // wallet_balance تکنسین را به‌روز کن
                \Modules\CRM\Models\Technician::where('id', $invoice->technician_id)
                    ->update(['wallet_balance' => $last + $amount]);

                $invoice->update(['in_wallet' => true]);
            }

            return $invoice;
        });

        // ─── ارسال خودکار پیامک «صدور فاکتور» به مشتری ──────────────
        // فقط بعد از موفقیت transaction، و فقط برای فاکتورهای اولِ یک
        // سفارش (یعنی existing=null) — برای تکمیل مجدد سفارش بازگشتی
        // پیامک دوباره ارسال نمی‌شود (مشتری قبلاً اطلاع داشته).
        if ($invoice && ! $existing) {
            $this->fireInvoiceSms($invoice);
        }

        return $invoice;
    }

    /**
     * ارسال پیامک «customer_invoice_issued» با لینک عمومی فاکتور.
     * در صورت غیرفعال بودن تمپلیت یا خطای کاوه‌نگار، سایلنت رد می‌شود
     * (لاگ ولی فاکتور بدون مشکل ساخته شده).
     */
    protected function fireInvoiceSms(Invoice $invoice): void
    {
        $log = \Illuminate\Support\Facades\Log::channel(config('logging.default'));
        try {
            $order = $invoice->order;
            if (! $order) {
                $log->warning('Auto invoice SMS skipped: order not found', ['invoice_id' => $invoice->id]);

                return;
            }

            $mobile = $order->customer_mobile ?: $order->customer?->mobile;
            if (! $mobile) {
                $log->warning('Auto invoice SMS skipped: customer has no mobile', ['order_id' => $order->id, 'invoice_id' => $invoice->id]);

                return;
            }

            $trigger = \Modules\CRM\Enums\SmsTrigger::CustomerInvoiceIssued;
            $template = \Modules\CRM\Models\SmsTemplate::where('trigger_key', $trigger->value)->first();
            if (! $template) {
                $log->warning('Auto invoice SMS skipped: template row missing', ['trigger' => $trigger->value]);

                return;
            }
            if (! $template->is_active) {
                $log->warning('Auto invoice SMS skipped: template is inactive', ['trigger' => $trigger->value]);

                return;
            }
            if (empty($template->kavenegar_template)) {
                $log->warning('Auto invoice SMS skipped: kavenegar_template empty', ['trigger' => $trigger->value]);

                return;
            }

            $order->loadMissing('customer');
            $vars = [
                'customer_name' => $order->customer_name ?: $order->customer?->display_name ?: '',
                'order_code' => (string) ($order->order_code ?? ''),
                'amount' => (string) (int) $invoice->total_amount,
                'invoice_code' => (string) $invoice->invoice_code,
                'receipt_url' => $invoice->publicUrl(),
            ];
            $tokens = $template->renderTokens($vars);

            $sms = app(\Modules\SMS\Services\KavenegarService::class);
            $result = $sms->sendTemplate($mobile, $template->kavenegar_template, $tokens);

            \Modules\CRM\Models\SmsLog::create([
                'order_id' => $order->id,
                'trigger_key' => $trigger->value,
                'recipient_mobile' => $mobile,
                'recipient_role' => 'customer',
                'body' => $template->kavenegar_template.' | '.json_encode($tokens, JSON_UNESCAPED_UNICODE),
                'status' => $result['success'] ? 'success' : 'failed',
                'response' => $result['success'] ? null : ($result['message'] ?? null),
                'sent_by' => null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Auto invoice SMS failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
