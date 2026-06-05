<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\LegacyCloseService;

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
                            {--refresh : سفارش‌های is_legacy_closed را هم پردازش کن (به‌روزرسانی legacy_tech_share و سایر فیلدها)}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'بستن retro-active سفارش‌هایی که در لاگ WP رویداد انجام کار دارند، بدون ساخت Invoice/WalletTx';

    public function handle(LegacyCloseService $legacyClose): int
    {
        $apply = (bool) $this->option('apply');
        $since = $this->option('since');
        $until = $this->option('until');
        $orderCode = $this->option('order-code');
        $orderId = $this->option('order-id');

        $refresh = (bool) $this->option('refresh');
        // وقتی --order-code یا --order-id داده شده، کاربر صراحتاً همان سفارش
        // را خواسته؛ پس حتی اگر قبلاً legacy_closed شده باشد، اجازه پردازش
        // مجدد می‌دهیم (برای به‌روزرسانی legacy_tech_share و سایر فیلدها).
        $explicitTarget = $orderCode || $orderId;
        $allowRefresh = $refresh || $explicitTarget;

        $query = Order::query()->whereNotNull('order_description_content');

        if (! $allowRefresh) {
            $query->where('status', '!=', OrderStatus::Completed->value)
                  ->where('is_legacy_closed', false);
        } else {
            // در حالت refresh: فقط سفارش‌هایی که یا قبلاً legacy_closed شده‌اند
            // یا هنوز Completed نیستند. (تا فاکتور‌های واقعی پنل را دست نزنیم.)
            $query->where(function ($q) {
                $q->where('is_legacy_closed', true)
                  ->orWhere('status', '!=', OrderStatus::Completed->value);
            });
        }

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

        $query->orderBy('id')->chunkById(100, function ($orders) use (&$closed, &$skipped, &$detailRows, $apply, $bar, $legacyClose) {
            foreach ($orders as $order) {
                $extracted = $legacyClose->extractFromOrder($order);
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
                    number_format($extracted['tech_share']),
                    number_format($extracted['company_share']),
                ];

                if ($apply) {
                    $legacyClose->applyToOrder($order, $extracted);
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
                ['id', 'order_code', 'تاریخ', 'price_customer', 'cost_price', 'total_invoice', 'سهم تکنسین', 'سهم شرکت'],
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

}
