<?php

namespace Modules\CRM\Support;

use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;

/**
 * «مبلغِ سفارش با فاکتورِ صادرشده نمی‌خواند» — تشخیصِ مرکزی.
 *
 * روی صفحهٔ سفارش دو عدد کنارِ هم می‌نشیند و از دو منبعِ متفاوت می‌آیند:
 * کارتِ «فاکتور سفارش» همین حالا محاسبه می‌شود، فاکتور یک snapshot از
 * لحظهٔ صدور است. دو مسیرِ قانونی می‌توانند جدایشان کنند: صدورِ دستیِ
 * زودهنگام (پیش از نهایی‌شدنِ مبلغ) و تکمیلِ «پیش‌نویس» وقتی فاکتور از
 * قبل هست. هر دو حالا هنگام وقوع به اپراتور هشدار می‌دهند.
 *
 * سومین علتِ سابق — تغییرِ درصدِ تکنسین — از ریشه بسته شد:
 * financialSummary وقتی فاکتوری هست درصد را از خودِ فاکتور می‌خواند، نه
 * از پروفایلِ امروزِ تکنسین.
 *
 * تصمیمِ مصوب: سیستم خودش چیزی را اصلاح نمی‌کند — فقط هرجا این دو عدد
 * دیده می‌شوند صریح هشدار می‌دهد تا اپراتور تصمیم بگیرد. اصلاحِ خودکار
 * یعنی دست‌بردن در کیف‌پول و بدهیِ تکنسین، و آن باید آگاهانه باشد.
 *
 * این کلاس تنها منبعِ حقیقتِ «آیا مغایرت هست» است؛ ویوها، کنترلرها و
 * دستورِ تشخیص همه از همین‌جا می‌خوانند تا تعریفشان یکی بماند.
 */
final class InvoiceMismatch
{
    /** برچسبِ فارسیِ هر علت. */
    public const REASONS = [
        'legacy' => 'سفارشِ قدیمی — اعداد از لاگ ووکامرس/وردپرس خوانده می‌شوند',
        'draft' => 'سفارش به‌صورت «پیش‌نویس» تکمیل شده، پس فاکتور بازصادر نشد',
        'edited_after_issue' => 'مبلغ سفارش بعد از صدور فاکتور تغییر کرده',
        'amount' => 'مبلغ سفارش با مبلغ فاکتور یکی نیست',
        'shares' => 'مبلغ یکی است ولی سهم تکنسین/شرکت با فاکتور نمی‌خواند — احتمالاً وضعیت سفارش بعد از صدور عوض شده',
    ];

    /**
     * مغایرتِ یک سفارش، یا null اگر نبود.
     *
     * @return array{
     *     order_total:int, invoice_total:int, order_tech:int, invoice_tech:int,
     *     order_company:int, invoice_company:int, difference:int,
     *     reason:string, reason_label:string, invoice_code:string|null
     * }|null
     */
    public static function check(Order $order): ?array
    {
        $invoice = self::activeInvoiceOf($order);
        if (! $invoice) {
            return null;
        }

        $fin = $order->financialSummary();

        $orderTotal = (int) $fin['customer_total'];
        $invoiceTotal = (int) $invoice->total_amount;
        $orderTech = (int) $fin['tech_share'];
        $invoiceTech = (int) $invoice->tech_share;
        $orderCompany = (int) $fin['company_share'];
        $invoiceCompany = (int) $invoice->company_share;

        if ($orderTotal === $invoiceTotal
            && $orderTech === $invoiceTech
            && $orderCompany === $invoiceCompany) {
            return null;
        }

        $reason = self::reasonFor($order, $invoice, $orderTotal, $invoiceTotal);

        // سفارشِ بازگشتیِ جمع‌شونده چند فاکتورِ فعال دارد؛ مقایسهٔ بالا با
        // «آخرین» فاکتور (کارِ آخر) است و مجموعِ فعال‌ها جدا گزارش می‌شود
        // تا بنر گمراه‌کننده نباشد (تصمیمِ ۱۴۰۵/۰۵/۲۹: «مجموع دو فاکتور»).
        $actives = $order->relationLoaded('invoices')
            ? $order->invoices->whereNull('superseded_at')
            : Invoice::where('order_id', $order->id)->get();

        return [
            'active_count' => $actives->count(),
            'active_sum' => (int) $actives->sum('total_amount'),
            'order_total' => $orderTotal,
            'invoice_total' => $invoiceTotal,
            'order_tech' => $orderTech,
            'invoice_tech' => $invoiceTech,
            'order_company' => $orderCompany,
            'invoice_company' => $invoiceCompany,
            'difference' => $orderTotal - $invoiceTotal,
            'reason' => $reason,
            'reason_label' => self::REASONS[$reason] ?? $reason,
            'invoice_code' => $invoice->invoice_code,
        ];
    }

    /** فقط «هست یا نیست» — برای بج‌های لیست که جزئیات نمی‌خواهند. */
    public static function exists(Order $order): bool
    {
        return self::check($order) !== null;
    }

    /**
     * فاکتورِ فعالِ سفارش.
     *
     * اگر رابطه از قبل لود شده باشد از حافظه می‌خواند تا در لیست‌ها
     * کوئریِ N+1 نسازد.
     */
    public static function activeInvoiceOf(Order $order): ?Invoice
    {
        // «آخرین» فاکتورِ فعال، نه اولین — روی سفارشِ بازگشتیِ جمع‌شونده
        // چند فاکتورِ فعال هست و مبلغِ سفارش (price_customer) متعلق به
        // آخرین تکمیل است؛ مقایسه با فاکتورِ کارِ اول مغایرتِ کاذب می‌سازد.
        if ($order->relationLoaded('invoices')) {
            $actives = $order->invoices->whereNull('superseded_at')->sortBy('id');

            return $actives->last() ?? $order->invoices->sortBy('id')->last();
        }

        return Invoice::where('order_id', $order->id)->latest('id')->first();
    }

    private static function reasonFor(Order $order, Invoice $invoice, int $orderTotal, int $invoiceTotal): string
    {
        if ($order->is_legacy_closed) {
            return 'legacy';
        }

        if ($orderTotal !== $invoiceTotal) {
            if ($order->save_as_draft) {
                return 'draft';
            }

            if ($invoice->issued_at && $order->updated_at
                && $invoice->issued_at->lessThan($order->updated_at)) {
                return 'edited_after_issue';
            }

            return 'amount';
        }

        // درصد این‌جا بررسی نمی‌شود: financialSummary وقتی فاکتوری هست
        // درصد را از خودِ فاکتور می‌خواند، پس تغییرِ درصدِ تکنسین دیگر
        // نمی‌تواند این دو را جدا کند. اگر با مبلغِ برابر سهم‌ها فرق
        // داشتند، دلیلش تغییرِ وضعیت است (مثلاً رفتن به «ایاب و ذهاب»
        // که ۱۰۰٪ به تکنسین می‌دهد).
        return 'shares';
    }
}
