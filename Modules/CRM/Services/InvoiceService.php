<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;

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
    public function __construct(protected CommissionCalculator $calc)
    {
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

            return Invoice::create([
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
        });
    }
}
