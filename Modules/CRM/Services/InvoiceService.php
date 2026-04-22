<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;

/**
 * تولید فاکتور از سفارش + ثبت تراکنش کمیسیون در کیف‌پول تکنسین.
 *
 * این سرویس idempotent است: اگر سفارشی قبلاً فاکتور صادر کرده،
 * همان فاکتور برگردانده می‌شود (یک سفارش = یک فاکتور).
 */
class InvoiceService
{
    public function __construct(
        protected CommissionCalculator $calc,
        protected WalletService $wallet,
    ) {
    }

    public function generateForOrder(Order $order, ?int $createdBy = null): ?Invoice
    {
        if ($existing = Invoice::where('order_id', $order->id)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $createdBy) {
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
            ]);

            // ثبت کمیسیون در کیف‌پول تکنسین (اگر تکنسین و سهم غیرصفر)
            if ($technician && $totals['tech_share'] > 0) {
                $this->wallet->recordTransaction(
                    technician: $technician,
                    type: WalletTxType::Commission,
                    amount: $totals['tech_share'],
                    note: 'کمیسیون فاکتور ' . $invoice->invoice_code,
                    order: $order,
                    invoice: $invoice,
                    createdBy: $createdBy,
                );
            }

            return $invoice;
        });
    }
}
