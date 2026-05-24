<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Morilog\Jalali\Jalalian;

/**
 * بستن سفارش‌های قدیمی بر اساس لاگ پنل WP — بدون ساخت Invoice یا WalletTx.
 *
 * هدف: سفارش‌هایی که در WP CRM بسته شده‌اند ولی در پنل Laravel هنوز
 * status=new دارند ولی در order_description_content یک رویداد با
 * status="انجام کار" + اعداد مالی دارند.
 *
 * عملیات (idempotent):
 *   ۱) parse لاگ‌ها برای پیدا کردن رویداد انجام کار + اعداد
 *   ۲) extract: price_customer، cost_price، total_invoice، tech_share
 *   ۳) update مستقیم crm_orders (با DB::table — model events بایپس):
 *      - status = Completed
 *      - completed_at = تاریخ رویداد
 *      - price_customer, cost_price, total_invoice, final_price از لاگ
 *      - is_legacy_closed = 1
 *
 * ⚠️ هیچ Invoice/WalletTransaction ساخته نمی‌شود. فلگ is_legacy_closed
 *    باعث می‌شود دکمهٔ «صدور فاکتور» در UI مخفی شود و anomaly detector
 *    این سفارش‌ها را flag نکند.
 *
 * نمونه:
 *   php artisan crm:retro-close-orders-from-log                      # dry-run همه
 *   php artisan crm:retro-close-orders-from-log --since=2026-05-14
 *   php artisan crm:retro-close-orders-from-log --since=2026-05-14 --until=2026-05-19
 *   php artisan crm:retro-close-orders-from-log --order-code=ORD-2605-00513
 *   php artisan crm:retro-close-orders-from-log --since=2026-05-14 --apply
 */
class RetroCloseOrdersFromLog extends Command
{
    protected $signature = 'crm:retro-close-orders-from-log
                            {--since= : فقط سفارش‌های ایجاد شده بعد از این تاریخ میلادی (YYYY-MM-DD)}
                            {--until= : فقط سفارش‌های ایجاد شده قبل از این تاریخ}
                            {--order-code= : فقط یک سفارش خاص با این کد}
                            {--order-id= : فقط یک سفارش با panel id}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'بستن retro-active سفارش‌هایی که در لاگ WP رویداد انجام کار دارند، بدون ساخت Invoice/WalletTx';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $since = $this->option('since');
        $until = $this->option('until');
        $orderCode = $this->option('order-code');
        $orderId = $this->option('order-id');

        $query = Order::query()
            ->where('status', '!=', OrderStatus::Completed->value)
            ->where('is_legacy_closed', false)
            ->whereNotNull('order_description_content');

        if ($orderCode) {
            $query->where('order_code', $orderCode);
        }
        if ($orderId) {
            $query->where('id', (int) $orderId);
        }
        if ($since) {
            $query->where('created_at', '>=', $since . ' 00:00:00');
        }
        if ($until) {
            $query->where('created_at', '<=', $until . ' 23:59:59');
        }

        $total = (clone $query)->count();
        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — سفارش‌های کاندید (status≠Completed، is_legacy_closed=false): {$total}");

        if ($total === 0) {
            $this->info('✓ هیچ کاندیدی نیست.');
            return self::SUCCESS;
        }

        // suppress outbound push (محض احتیاط — DB::table از مدل بایپس می‌کند، ولی این هم اضافه)
        app()->instance('crm.suppress_outbound_push', true);

        $closed = 0;
        $skipped = 0;
        $detailRows = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById(100, function ($orders) use (&$closed, &$skipped, &$detailRows, $apply, $bar) {
            foreach ($orders as $order) {
                $extracted = $this->extractFromLog($order);
                if (! $extracted) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $detailRows[] = [
                    $order->id,
                    $order->order_code,
                    $extracted['date_jalali'] ?? '—',
                    number_format($extracted['price_customer']),
                    number_format($extracted['cost_price']),
                    number_format($extracted['total_invoice']),
                ];

                if ($apply) {
                    // DB::table استفاده می‌کنیم تا model events fire نشوند
                    // (مهم برای جلوگیری از push outbound و هر گونه side-effect).
                    DB::table('crm_orders')
                        ->where('id', $order->id)
                        ->update([
                            'status' => OrderStatus::Completed->value,
                            'completed_at' => $extracted['completed_at'] ?? now(),
                            'price_customer' => $extracted['price_customer'],
                            'cost_price' => $extracted['cost_price'],
                            'total_invoice' => $extracted['total_invoice'],
                            'final_price' => $extracted['price_customer'], // نمایش در «خلاصه مالی»
                            'is_legacy_closed' => 1,
                            'updated_at' => now(),
                        ]);
                }

                $closed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if (! empty($detailRows)) {
            $this->line('— نمونهٔ ۲۰ مورد اول —');
            $this->table(
                ['id', 'order_code', 'تاریخ بسته‌شدن', 'price_customer', 'cost_price', 'total_invoice'],
                array_slice($detailRows, 0, 20)
            );
            if (count($detailRows) > 20) {
                $this->line('... و ' . (count($detailRows) - 20) . ' مورد دیگر.');
            }
        }

        $this->info("بسته شد: {$closed}");
        if ($skipped > 0) {
            $this->line("رد شد (لاگ انجام کار با اعداد پیدا نشد): {$skipped}");
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $args = '';
            if ($since) $args .= " --since={$since}";
            if ($until) $args .= " --until={$until}";
            if ($orderCode) $args .= " --order-code={$orderCode}";
            $this->line('php artisan crm:retro-close-orders-from-log' . $args . ' --apply');
        } else {
            $this->newLine();
            $this->info('⚠ هیچ Invoice یا WalletTransaction ساخته نشد. فلگ is_legacy_closed=true روی این سفارش‌ها ست شده.');
        }

        return self::SUCCESS;
    }

    /**
     * استخراج اعداد مالی + تاریخ بستن از order_description_content.
     *
     * @return array{
     *     completed_at:?string,
     *     date_jalali:?string,
     *     price_customer:int,
     *     cost_price:int,
     *     total_invoice:int
     * }|null
     */
    private function extractFromLog(Order $order): ?array
    {
        $events = $order->wp_events; // array

        if (empty($events) || ! is_array($events)) {
            return null;
        }

        $completedEvent = null;
        $invoiceEvent = null;

        foreach ($events as $ev) {
            $status = mb_strtolower((string) ($ev['status'] ?? ''));
            $subject = (string) ($ev['subject'] ?? '');
            $content = (string) ($ev['content'] ?? '');

            // رویداد تغییر وضعیت به انجام کار
            if (
                str_contains($status, 'انجام کار')
                || str_contains($subject, 'انجام کار')
                || str_contains($subject, 'تغییر وضعیت سفارش به انجام')
            ) {
                $completedEvent = $ev;
            }

            // رویداد ایجاد فاکتور (شامل اعداد مالی)
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

        // پارس اعداد از content (هر دو رویداد)
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

        // اگر total_invoice پیدا نشد، محاسبه کن
        if ($totalInvoice === 0 && $priceCustomer > 0) {
            $totalInvoice = max(0, $priceCustomer - $costPrice);
        }

        // اگر هیچ عددی پیدا نشد، رد کن (شرط user: «لاگ انجام کار با عدد»)
        if ($priceCustomer === 0 && $totalInvoice === 0) {
            return null;
        }

        $eventDate = $completedEvent['date'] ?? $invoiceEvent['date'] ?? null;
        $completedAt = $this->parseEventDate($eventDate);

        return [
            'completed_at' => $completedAt,
            'date_jalali' => $eventDate ? $this->toJalaliDate($eventDate) : null,
            'price_customer' => $priceCustomer,
            'cost_price' => $costPrice,
            'total_invoice' => $totalInvoice,
        ];
    }

    /**
     * استخراج عدد بعد از یکی از کلیدواژه‌های مالی در متن. اعداد ممکن
     * است با کاما باشند («1,234,567»). ارقام فارسی هم پشتیبانی می‌شود.
     */
    private function extractAmount(string $text, array $keywords): int
    {
        // ارقام فارسی → لاتین
        $text = strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        foreach ($keywords as $kw) {
            // pattern: kw + (هر چیزی غیر از عدد، مثل ":" یا "—") + numbers with optional commas
            $pattern = '/' . preg_quote($kw, '/') . '\s*[:\-—–]?\s*([0-9,]+)/u';
            if (preg_match($pattern, $text, $m)) {
                return (int) str_replace(',', '', $m[1]);
            }
        }
        return 0;
    }

    /** parse تاریخ event به datetime — می‌تواند ISO، Jalali، یا سایر باشد. */
    private function parseEventDate(?string $date): ?string
    {
        if (! $date) return null;

        // اول تلاش با Carbon (ISO / Y-m-d / Y-m-d H:i:s)
        try {
            return \Carbon\Carbon::parse($date)->toDateTimeString();
        } catch (\Throwable $e) {
            // ignore
        }

        // اگر Jalali بود (مثل 1405/02/28 14:26)
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
