<?php

namespace Modules\CustomerApp\Support;

use App\Models\Setting;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;

/**
 * ساخت payload فاکتور برای اپ موبایل.
 *
 * منبع داده:
 *   - crm_invoices: invoice_code / status / issued_at / paid_at / total_amount
 *   - crm_orders.items (HasMany OrderItem): لیست خط‌به‌خط
 *   - اگر items خالی است، fallback به piece_list/customer_price_list JSON روی order
 *   - order.hire (اجرت) و transportation (ایاب‌وذهاب) و discount به‌عنوان آیتم سرویس اضافه می‌شوند
 *
 * Tax: از Setting('invoice_tax_rate') (default 0) خوانده می‌شود.
 * واحد پول: مبالغِ DB (سفارش و فاکتور) همگی «تومان» هستند و مستقیم و بدون
 * تبدیل برگردانده می‌شوند — مطابق پنل، صفحهٔ پرداخت و درگاه (که خودش
 * تومان→ریال می‌کند). تقسیم بر ۱۰ اشتباه بود و مبالغ را ۱۰ برابر کم نشان می‌داد.
 * payment_url به route عمومی موجود /crm/pay/{invoice_code} اشاره می‌کند.
 */
final class InvoiceBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Order $order, ?Invoice $invoice): array
    {
        $items = self::buildItems($order);

        // subtotal از مجموع آیتم‌ها (ریال)
        $subtotalRial = (int) array_sum(array_column($items, '_amount_rial'));

        // discount از order (ریال)
        $discountRial = (int) max(0, $order->discount ?? 0);

        // tax — درصد ضربدر (subtotal - discount)
        $taxRate = (float) Setting::get('invoice_tax_rate', 0);
        $taxableRial = max(0, $subtotalRial - $discountRial);
        $taxRial = (int) round($taxableRial * $taxRate);

        $totalRial = max(0, $subtotalRial - $discountRial + $taxRial);

        $shapedItems = array_map(function (array $row) {
            $unitToman = (int) $row['_unit_price_rial'];
            $amountToman = (int) $row['_amount_rial'];

            return [
                'row' => $row['row'],
                'type' => $row['type'],
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $unitToman,
                'unit_price_formatted' => self::faMoney($unitToman),
                'amount' => $amountToman,
                'amount_formatted' => self::faMoney($amountToman),
                'warranty_months' => $row['warranty_months'],
            ];
        }, $items);

        $subtotalToman = (int) $subtotalRial;
        $discountToman = (int) $discountRial;
        $taxToman = (int) $taxRial;
        $totalToman = (int) $totalRial;

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
                'subtotal' => $subtotalToman,
                'subtotal_formatted' => self::faMoney($subtotalToman),
                'discount' => $discountToman,
                'discount_formatted' => self::faMoney($discountToman),
                'discount_code' => null,
                'tax_rate' => $taxRate,
                'tax' => $taxToman,
                'tax_formatted' => self::faMoney($taxToman),
                'total' => $totalToman,
                'total_formatted' => self::faMoney($totalToman),
                'currency' => 'IRT',
            ],
            'payment' => self::buildPayment($invoice, $paidAt),
            'notes' => trim((string) ($order->invoice_descripotion ?? '')) ?: null,
            'pdf_url' => $invoice?->invoice_code
                ? route('crm.invoice.public', ['invoiceCode' => $invoice->invoice_code])
                : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function buildItems(Order $order): array
    {
        $rows = [];
        $row = 1;

        // اولویت ۱: OrderItem های structured
        if ($order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $rows[] = [
                    'row' => $row++,
                    'type' => $item->type ?: 'service',
                    'description' => $item->title,
                    'quantity' => (int) ($item->quantity ?: 1),
                    '_unit_price_rial' => (int) ($item->unit_price ?: 0),
                    '_amount_rial' => (int) ($item->total_price ?: 0),
                    'warranty_months' => $item->warranty_months ? (int) $item->warranty_months : null,
                ];
            }
        }

        // اولویت ۲: piece_list + customer_price_list JSON (legacy)
        if (empty($rows)) {
            $titles = is_array($order->piece_list) ? $order->piece_list : [];
            $sells = is_array($order->customer_price_list) ? $order->customer_price_list : [];
            foreach ($titles as $i => $title) {
                $titleStr = is_string($title) ? trim($title) : trim((string) ($title['title'] ?? ''));
                if ($titleStr === '') {
                    continue;
                }
                $unit = (int) ($sells[$i] ?? 0);
                $rows[] = [
                    'row' => $row++,
                    'type' => 'part',
                    'description' => $titleStr,
                    'quantity' => 1,
                    '_unit_price_rial' => $unit,
                    '_amount_rial' => $unit,
                    'warranty_months' => null,
                ];
            }
        }

        // اجرت + ایاب‌وذهاب به‌عنوان آیتم
        $hire = (int) ($order->hire ?? 0);
        if ($hire > 0) {
            $rows[] = [
                'row' => $row++,
                'type' => 'labor',
                'description' => 'اجرت تعمیر',
                'quantity' => 1,
                '_unit_price_rial' => $hire,
                '_amount_rial' => $hire,
                'warranty_months' => null,
            ];
        }
        $transport = (int) ($order->transportation ?? 0);
        if ($transport > 0) {
            $rows[] = [
                'row' => $row++,
                'type' => 'service',
                'description' => 'ایاب و ذهاب',
                'quantity' => 1,
                '_unit_price_rial' => $transport,
                '_amount_rial' => $transport,
                'warranty_months' => null,
            ];
        }

        if (empty($rows)) {
            $total = (int) ($order->total_invoice ?? $order->final_price ?? 0);
            $rows[] = [
                'row' => 1,
                'type' => 'service',
                'description' => 'انجام خدمات',
                'quantity' => 1,
                '_unit_price_rial' => $total,
                '_amount_rial' => $total,
                'warranty_months' => null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPayment(?Invoice $invoice, ?\DateTimeInterface $paidAt): array
    {
        $isPaid = $invoice?->status === 'paid';
        $paymentUrl = null;
        if ($invoice && ! $isPaid && $invoice->status === 'issued') {
            $paymentUrl = route('crm.payment.pay', ['invoiceCode' => $invoice->invoice_code]);
        }

        return [
            'method' => $isPaid ? 'online' : null,
            'is_paid' => $isPaid,
            'paid_at' => $paidAt?->format(\DateTimeInterface::ATOM),
            'paid_at_jalali' => self::jalali($paidAt),
            'payment_url' => $paymentUrl,
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
