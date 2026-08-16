<?php

namespace Modules\CustomerApp\Support;

use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;

/**
 * ساخت payload فاکتور برای اپ موبایل — آینهٔ دقیقِ فاکتورِ رسمیِ صادرشده
 * (رسید crm::invoices.print) تا مبلغ و شرحِ اپ با چیزی که مشتری می‌بیند و
 * می‌پردازد یکی باشد.
 *
 * منبع مبلغِ نهایی: crm_invoices.total_amount (همان مبلغی که مشتری می‌پردازد
 * و درگاه دریافت می‌کند).
 *
 * ساخت ردیف‌ها هم‌منطق با رسید:
 *   ۱) اگر invoice_descripotion («متن فاکتور برای مشتری») ست باشد → یک ردیف
 *      با همان متن و مبلغ کل.
 *   ۲) وگرنه OrderItemهای ساختاریافته.
 *   ۳) وگرنه piece_list/customer_price_list (legacy).
 *   ۴) وگرنه یک ردیف «انجام خدمات» با مبلغ کل.
 *
 * واحد پول: همهٔ مبالغِ DB «تومان» هستند و بدون تبدیل برگردانده می‌شوند
 * (درگاه خودش تومان→ریال می‌کند). تقسیم بر ۱۰ اشتباه بود.
 */
final class InvoiceBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Order $order, ?Invoice $invoice): array
    {
        // مبلغ مرجع = total فاکتور رسمی (تومان).
        $grandTotal = (int) ($invoice?->total_amount ?? 0);

        $rows = self::buildRows($order, $grandTotal);
        $subtotal = (int) array_sum(array_column($rows, '_amount'));

        // اگر فاکتوری نیست (draft)، مبلغ کل از مجموع ردیف‌ها.
        $total = $grandTotal > 0 ? $grandTotal : $subtotal;

        $shapedItems = array_map(fn (array $row) => [
            'row' => $row['row'],
            'type' => $row['type'],
            'description' => $row['description'],
            'quantity' => $row['quantity'],
            'unit_price' => $row['_unit'],
            'unit_price_formatted' => self::faMoney($row['_unit']),
            'amount' => $row['_amount'],
            'amount_formatted' => self::faMoney($row['_amount']),
            'warranty_months' => $row['warranty_months'],
        ], $rows);

        $issuedAt = $invoice?->issued_at ?? $invoice?->created_at;
        $paidAt = $invoice?->paid_at;

        return [
            'invoice_number' => $invoice?->invoice_code,
            'order_id' => (int) $order->id,
            'tracking_code' => $order->order_code,
            'issued_at' => $issuedAt?->utc()->toIso8601String(),
            'issued_at_jalali' => self::jalali($issuedAt),
            'status' => $invoice?->status ?? 'draft',
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_mobile,
                'address' => $order->address,
            ],
            'technician' => $order->technician ? [
                'id' => (int) $order->technician->id,
                'name' => $order->technician->display_name,
            ] : null,
            'items' => array_values($shapedItems),
            'totals' => [
                'subtotal' => $subtotal,
                'subtotal_formatted' => self::faMoney($subtotal),
                'discount' => 0,
                'discount_formatted' => self::faMoney(0),
                'discount_code' => null,
                'tax_rate' => 0,
                'tax' => 0,
                'tax_formatted' => self::faMoney(0),
                'total' => $total,
                'total_formatted' => self::faMoney($total),
                'currency' => 'IRT',
            ],
            'payment' => self::buildPayment($invoice, $paidAt),
            'notes' => trim((string) ($order->invoice_descripotion ?? '')) ?: null,
            'pdf_url' => $invoice?->invoice_code
                ? route('crm.invoice.public.pdf', ['invoiceCode' => $invoice->public_token])
                : null,
        ];
    }

    /**
     * ساخت ردیف‌های فاکتور — دقیقاً هم‌منطق با رسیدِ چاپی (print.blade).
     *
     * @return array<int, array<string, mixed>>
     */
    private static function buildRows(Order $order, int $grandTotal): array
    {
        $customerDesc = trim((string) ($order->invoice_descripotion ?? ''));
        $rows = [];
        $i = 1;

        if ($customerDesc !== '') {
            // مطابق رسید: یک ردیف با «متن فاکتور برای مشتری» و مبلغ کل.
            $rows[] = self::row(1, 'service', $customerDesc, 1, $grandTotal, $grandTotal, null);
        } elseif ($order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $rows[] = self::row(
                    $i++,
                    $item->type ?: 'service',
                    (string) $item->title,
                    (int) ($item->quantity ?: 1),
                    (int) ($item->unit_price ?: 0),
                    (int) ($item->total_price ?: 0),
                    $item->warranty_months ? (int) $item->warranty_months : null,
                );
            }
        } else {
            $titles = is_array($order->piece_list) ? $order->piece_list : [];
            $sells = is_array($order->customer_price_list) ? $order->customer_price_list : [];
            foreach ($titles as $idx => $title) {
                $t = is_string($title) ? trim($title) : trim((string) ($title['title'] ?? ''));
                if ($t === '') {
                    continue;
                }
                $unit = (int) ($sells[$idx] ?? 0);
                $rows[] = self::row($i++, 'part', $t, 1, $unit, $unit, null);
            }
        }

        if ($rows === []) {
            $rows[] = self::row(1, 'service', 'انجام خدمات', 1, $grandTotal, $grandTotal, null);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(int $rowNo, string $type, string $desc, int $qty, int $unit, int $amount, ?int $warranty): array
    {
        return [
            'row' => $rowNo,
            'type' => $type,
            'description' => $desc,
            'quantity' => $qty,
            '_unit' => $unit,
            '_amount' => $amount,
            'warranty_months' => $warranty,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPayment(?Invoice $invoice, ?\DateTimeInterface $paidAt): array
    {
        $isPaid = $invoice?->status === 'paid';
        $paymentUrl = null;
        if ($invoice && ! $isPaid && $invoice->status === 'issued') {
            $paymentUrl = route('crm.payment.pay', ['invoiceCode' => $invoice->public_token]);
        }

        return [
            'method' => $isPaid ? 'online' : null,
            'is_paid' => $isPaid,
            'paid_at' => $paidAt?->format(\DateTimeInterface::ATOM),
            'paid_at_jalali' => self::jalali($paidAt),
            'payment_url' => $paymentUrl,
            // قراردادِ صریح برای اپ (درخواستِ تیمِ اپ ۱۴۰۵/۰۵/۲۵):
            //  - public_token همیشه هست (حتی paid/cancelled) تا اپ بتواند
            //    GET /crm/pay/{token}/status را poll کند.
            //  - pay_url فقط وقتی فاکتور قابلِ پرداخت است (همان payment_url؛
            //    payment_url برای سازگاری با نسخهٔ فعلیِ اپ می‌ماند).
            'public_token' => $invoice?->public_token,
            'pay_url' => $paymentUrl,
            // یک‌کلیکه: مستقیم به درگاه بدونِ صفحهٔ پیش‌نمایش — اپ ?return=
            // خودش را به همین اضافه می‌کند.
            'direct_pay_url' => $paymentUrl !== null
                ? route('crm.payment.direct', ['invoiceCode' => $invoice->public_token])
                : null,
        ];
    }

    /**
     * تبدیل تاریخ Carbon به رشته شمسی فرمت‌شده (Y/m/d H:i).
     */
    private static function jalali(?\DateTimeInterface $dt): ?string
    {
        if (! $dt) {
            return null;
        }

        return \Morilog\Jalali\Jalalian::fromCarbon(
            \Carbon\Carbon::instance($dt)->setTimezone('Asia/Tehran')
        )->format('Y/m/d H:i');
    }

    /**
     * عدد integer (تومان) → رشته فارسی با جداکننده‌ی هزارگان + پسوند " تومان".
     * مثلاً 90000 → "۹۰٬۰۰۰ تومان".
     */
    private static function faMoney(?int $toman): ?string
    {
        if ($toman === null) {
            return null;
        }
        $formatted = number_format($toman, 0, '.', '٬');

        return strtr($formatted, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]).' تومان';
    }
}
