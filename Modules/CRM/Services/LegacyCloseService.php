<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Morilog\Jalali\Jalalian;

/**
 * بستن retro-active یک سفارش بر اساس لاگ پنل WP — بدون ساخت Invoice/WalletTx.
 *
 * مرجع کاربرد:
 *   - Console: RetroCloseOrdersFromLog (bulk)
 *   - Web:    OrderController::retroClose (تک سفارش با دکمه)
 *
 * ⚠️ این سرویس DB::table()->update() استفاده می‌کند تا model events
 *    fire نشوند → هیچ outbound push، InvoiceService، WalletTransaction.
 */
class LegacyCloseService
{
    /**
     * استخراج اعداد مالی + تاریخ از order_description_content.
     *
     * @return array{
     *     completed_at:?string,
     *     date_jalali:?string,
     *     price_customer:int,
     *     cost_price:int,
     *     total_invoice:int,
     *     tech_share:int,
     *     company_share:int
     * }|null  null اگر لاگ انجام کار با عدد پیدا نشد
     */
    public function extractFromOrder(Order $order): ?array
    {
        $events = $order->wp_events;
        if (empty($events) || ! is_array($events)) {
            return null;
        }

        $completedEvent = null;
        $invoiceEvent = null;

        foreach ($events as $ev) {
            $status = mb_strtolower((string) ($ev['status'] ?? ''));
            $subject = (string) ($ev['subject'] ?? '');
            $content = (string) ($ev['content'] ?? '');

            if (
                str_contains($status, 'انجام کار')
                || str_contains($subject, 'انجام کار')
                || str_contains($subject, 'تغییر وضعیت سفارش به انجام')
            ) {
                $completedEvent = $ev;
            }

            if (
                str_contains($subject, 'ایجاد فاکتور')
                || str_contains($subject, 'صدور فاکتور')
                || str_contains($content, 'جمع کل صورت حساب')
                || str_contains($content, 'هزینه قطعات')
            ) {
                $invoiceEvent = $ev;
            }
        }

        if (! $completedEvent && ! $invoiceEvent) {
            return null;
        }

        $combinedContent = '';
        if ($invoiceEvent) {
            $combinedContent .= (string) ($invoiceEvent['content'] ?? '');
        }
        if ($completedEvent && $completedEvent !== $invoiceEvent) {
            $combinedContent .= "\n" . (string) ($completedEvent['content'] ?? '');
        }

        $priceCustomer = $this->extractAmount($combinedContent, ['جمع کل صورت حساب', 'جمع کل صورت‌حساب', 'مبلغ کل', 'price_customer']);
        $costPrice = $this->extractAmount($combinedContent, ['هزینه قطعات مصرفی', 'هزینه قطعات', 'cost_price']);
        $totalInvoice = $this->extractAmount($combinedContent, ['مانده کل', 'مانده فاکتور', 'total_invoice']);
        $techShareLog = $this->extractAmount($combinedContent, ['سهم تکنسین', 'tech_share']);
        $companyShareLog = $this->extractAmount($combinedContent, ['سهم شرکت', 'company_share']);

        if ($totalInvoice === 0 && $priceCustomer > 0) {
            $totalInvoice = max(0, $priceCustomer - $costPrice);
        }

        if ($techShareLog === 0 && $companyShareLog > 0 && $totalInvoice > 0) {
            $techShareLog = max(0, $totalInvoice - $companyShareLog);
        }
        if ($companyShareLog === 0 && $techShareLog > 0 && $totalInvoice > 0) {
            $companyShareLog = max(0, $totalInvoice - $techShareLog);
        }

        if ($priceCustomer === 0 && $totalInvoice === 0) {
            return null;
        }

        $eventDate = $completedEvent['date'] ?? $invoiceEvent['date'] ?? null;

        return [
            'completed_at' => $this->parseEventDate($eventDate),
            'date_jalali' => $eventDate ? $this->toJalaliDate($eventDate) : null,
            'price_customer' => $priceCustomer,
            'cost_price' => $costPrice,
            'total_invoice' => $totalInvoice,
            'tech_share' => $techShareLog,
            'company_share' => $companyShareLog,
        ];
    }

    /**
     * اعمال تغییرات روی سفارش — DB::table مستقیم برای بایپس model events.
     * هیچ Invoice یا WalletTransaction ساخته نمی‌شود.
     */
    public function applyToOrder(Order $order, array $extracted): void
    {
        // محض احتیاط، outbound push را معلق کن (DB::table از مدل بایپس
        // می‌کند ولی برای هرگونه observer آینده هم اضافه می‌گذاریم).
        app()->instance('crm.suppress_outbound_push', true);

        DB::table('crm_orders')
            ->where('id', $order->id)
            ->update([
                'status' => OrderStatus::Completed->value,
                'completed_at' => $extracted['completed_at'] ?? now(),
                'price_customer' => $extracted['price_customer'],
                'cost_price' => $extracted['cost_price'],
                'total_invoice' => $extracted['total_invoice'],
                'final_price' => $extracted['price_customer'],
                'is_legacy_closed' => 1,
                'legacy_tech_share' => $extracted['tech_share'],
                'legacy_company_share' => $extracted['company_share'],
                'updated_at' => now(),
            ]);
    }

    private function extractAmount(string $text, array $keywords): int
    {
        $text = strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        foreach ($keywords as $kw) {
            $pattern = '/' . preg_quote($kw, '/') . '\s*[:\-—–]?\s*([0-9,]+)/u';
            if (preg_match($pattern, $text, $m)) {
                return (int) str_replace(',', '', $m[1]);
            }
        }
        return 0;
    }

    private function parseEventDate(?string $date): ?string
    {
        if (! $date) return null;

        try {
            return \Carbon\Carbon::parse($date)->toDateTimeString();
        } catch (\Throwable $e) {
            // try Jalali
        }

        $latin = strtr($date, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        try {
            return Jalalian::fromFormat('Y/m/d H:i', $latin)->toCarbon()->toDateTimeString();
        } catch (\Throwable $e) {
            try {
                return Jalalian::fromFormat('Y/m/d', $latin)->toCarbon()->toDateTimeString();
            } catch (\Throwable $e) {
                return null;
            }
        }
    }

    private function toJalaliDate(?string $date): ?string
    {
        if (! $date) return null;
        try {
            return Jalalian::fromCarbon(\Carbon\Carbon::parse($date))->format('Y/m/d H:i');
        } catch (\Throwable $e) {
            return $date;
        }
    }
}
