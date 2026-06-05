<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;

/**
 * بازسازی status سفارش‌ها از روی wp_events (order_description_content).
 *
 * علت نیاز: status meta در WP بعد از کار تکنسین silent reset شده.
 * اما لاگ events سالم است. این command آخرین event «تغییر وضعیت»
 * را پیدا می‌کند و status را به همان مقدار ست می‌کند.
 *
 * mapping از نام فارسی به enum:
 *   ثبت سفارش → new
 *   هماهنگ شده → coordinated
 *   باز شده → open
 *   معلق / نامشخص → suspended
 *   انجام کار → completed
 *   کنسل شده → cancelled
 *   رد سفارش → declined
 *   ایاب و ذهاب → transit
 *   مرجوع شده → returned
 *
 * نمونه:
 *   php artisan crm:restore-status-from-events --order=74751
 *   php artisan crm:restore-status-from-events --since=2026-05-14 --until=2026-05-18
 *   php artisan crm:restore-status-from-events --since=... --apply
 */
class RestoreStatusFromEvents extends Command
{
    protected $signature = 'crm:restore-status-from-events
                            {--order= : فقط یک سفارش با id Panel}
                            {--since= : فقط سفارش‌های ساخته‌شده بعد از این تاریخ (Panel.created_at)}
                            {--until= : فقط سفارش‌های قبل از این تاریخ}
                            {--only-diff : فقط آنهایی که Panel status با event آخر متفاوت است}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'بازسازی status سفارش‌ها از روی لاگ wp_events (وقتی status meta در WP reset شده)';

    /** نگاشت نام فارسی به status enum. */
    private array $statusMap = [
        'ثبت سفارش' => OrderStatus::New,
        'جدید' => OrderStatus::New,
        'هماهنگ شده' => OrderStatus::Coordinated,
        'هماهنگ' => OrderStatus::Coordinated,
        'باز شده' => OrderStatus::Open,
        'انتقال به تعمیرگاه' => OrderStatus::Open,
        'معلق' => OrderStatus::Suspended,
        'نامشخص' => OrderStatus::Suspended,
        'وضعیت نامشخص' => OrderStatus::Suspended,
        'انجام کار' => OrderStatus::Completed,
        'پایان سفارش' => OrderStatus::Completed,
        'تکمیل شده' => OrderStatus::Completed,
        'کنسل شده' => OrderStatus::Cancelled,
        'کنسل' => OrderStatus::Cancelled,
        'لغو شده' => OrderStatus::Cancelled,
        'رد سفارش' => OrderStatus::Declined,
        'رد شده' => OrderStatus::Declined,
        'ایاب و ذهاب' => OrderStatus::Transit,
        'مرجوع' => OrderStatus::Returned,
        'مرجوع شده' => OrderStatus::Returned,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $query = Order::query();
        if ($id = $this->option('order')) {
            $query->where('id', (int) $id);
        }
        if ($since = $this->option('since')) {
            $query->where('created_at', '>=', $since);
        }
        if ($until = $this->option('until')) {
            $query->where('created_at', '<', date('Y-m-d', strtotime($until . ' +1 day')));
        }

        $orders = $query->get(['id', 'wp_id', 'order_code', 'status', 'order_description_content']);

        if ($orders->isEmpty()) {
            $this->warn('سفارشی پیدا نشد.');
            return self::SUCCESS;
        }

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — سفارش‌ها: {$orders->count()}");
        $this->newLine();

        $rows = [];
        $changed = 0;
        $sameCount = 0;
        $noEvents = 0;
        $unmapped = 0;

        foreach ($orders as $order) {
            $events = $order->order_description_content ?? [];
            if (! is_array($events) || empty($events)) {
                $noEvents++;
                continue;
            }

            // پیدا کردن آخرین event «تغییر وضعیت» — یا event با status معنی‌دار
            $lastStatusEnum = null;
            $lastStatusLabel = null;
            $lastDate = null;
            foreach (array_reverse($events) as $e) {
                $st = trim((string) ($e['status'] ?? ''));
                if (! $st) continue;
                if (isset($this->statusMap[$st])) {
                    $lastStatusEnum = $this->statusMap[$st];
                    $lastStatusLabel = $st;
                    $lastDate = $e['date'] ?? '';
                    break;
                }
            }

            if (! $lastStatusEnum) {
                $unmapped++;
                continue;
            }

            $currentStatus = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
            if ($currentStatus === $lastStatusEnum->value) {
                $sameCount++;
                if (! $this->option('only-diff')) {
                    // skip to keep table small
                }
                continue;
            }

            $rows[] = [
                $order->id,
                $order->wp_id,
                $order->order_code,
                $currentStatus,
                $lastStatusEnum->value . ' (' . $lastStatusLabel . ')',
                $lastDate,
            ];

            if ($apply) {
                $previous = $currentStatus;
                DB::table('crm_orders')
                    ->where('id', $order->id)
                    ->update(['status' => $lastStatusEnum->value, 'updated_at' => now()]);

                try {
                    OrderStatusLog::create([
                        'order_id' => $order->id,
                        'from_status' => $previous,
                        'to_status' => $lastStatusEnum->value,
                        'note' => 'بازسازی از wp_events — رویداد آخر: ' . $lastStatusLabel,
                        'changed_by' => null,
                        'created_at' => now(),
                    ]);
                } catch (\Throwable) {}
            }

            $changed++;
        }

        if (! empty($rows)) {
            $this->table(['id', 'wp_id', 'code', 'Panel فعلی', 'هدف از events', 'تاریخ event'], $rows);
        }

        $this->newLine();
        $this->info("نیاز به تغییر: {$changed}");
        $this->line("از قبل یکسان: {$sameCount}");
        if ($noEvents > 0) $this->warn("بدون events: {$noEvents}");
        if ($unmapped > 0) $this->warn("event آخر نگاشت نشد: {$unmapped}");

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال --apply اضافه کن.');
        } else {
            $this->info("✓ {$changed} سفارش status بازسازی شد.");
        }

        return self::SUCCESS;
    }
}
