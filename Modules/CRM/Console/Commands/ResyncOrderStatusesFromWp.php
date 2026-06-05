<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;

/**
 * Resync وضعیت سفارش‌ها از WP CRM به Laravel — برای حالتی که laravelManaged
 * filter جلوی sync دوره‌ای را گرفته بود و الان وضعیت‌های Laravel با WP
 * تفاوت دارد. این command فقط فیلد `status` را از WP postmeta می‌خواند
 * و در صورت تفاوت با Laravel به‌روزرسانی می‌کند.
 *
 * مزایا نسبت به full resync:
 *   - فقط یک فیلد می‌خواند، نه تمام payload
 *   - سریع‌تر (~۱۰x)
 *   - بدون درگیر کردن taxonomy/parts/financial
 *
 * نمونه:
 *   php artisan crm:resync-order-statuses-from-wp                  # همه
 *   php artisan crm:resync-order-statuses-from-wp --limit=1000
 *   php artisan crm:resync-order-statuses-from-wp --since=2026-05-01
 *   php artisan crm:resync-order-statuses-from-wp --dry-run        # فقط شمارش
 */
class ResyncOrderStatusesFromWp extends Command
{
    protected $signature = 'crm:resync-order-statuses-from-wp
                            {--limit= : حداکثر تعداد سفارش (پیش‌فرض همه)}
                            {--offset=0 : شروع از این offset (برای ادامه)}
                            {--since= : فقط سفارش‌های جدیدتر از این تاریخ (YYYY-MM-DD)}
                            {--until= : فقط سفارش‌های قدیمی‌تر از این تاریخ (YYYY-MM-DD، شامل خود تاریخ)}
                            {--dry-run : فقط شمارش، بدون تغییر}';

    protected $description = 'بازخوانی فیلد status سفارش‌ها از WP CRM (سریع — فقط یک فیلد)';

    public function handle(): int
    {
        $this->configureWpConnection();
        $prefix = env('WP_DB_PREFIX', 'or_');

        try {
            $wp = DB::connection('wp_crm');
            $wp->getPdo(); // test
        } catch (\Throwable $e) {
            $this->error('اتصال به WP DB ممکن نشد: ' . $e->getMessage());
            return self::FAILURE;
        }

        // باز کردن فیلتر: همه سفارش‌های Laravel که wp_id دارند
        $base = Order::query()->whereNotNull('wp_id');

        if ($since = $this->option('since')) {
            $base->where('created_at', '>=', $since);
        }
        if ($until = $this->option('until')) {
            // until شامل کل آن روز است
            $base->where('created_at', '<', date('Y-m-d', strtotime($until . ' +1 day')));
        }
        $offset = (int) $this->option('offset');
        if ($offset > 0) {
            $base->where('id', '>', $offset);
        }
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $total = (clone $base)->count();
        if ($limit) $total = min($total, $limit);
        $this->info("سفارش‌های هدف: {$total}");

        if ($total === 0 || $this->option('dry-run')) {
            if ($this->option('dry-run')) {
                $this->warn('--dry-run فعال — بدون تغییر خروج.');
            }
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $changed = 0;
        $skipped = 0;
        $missing = 0;
        $processed = 0;

        // suppress push back — این تغییرات نباید به WP echo شوند
        app()->instance('crm.suppress_outbound_push', true);

        $base->select(['id', 'wp_id', 'status'])
            ->orderBy('id')
            ->chunkById(500, function ($orders) use ($wp, $prefix, $bar, &$changed, &$skipped, &$missing, &$processed, $limit, $total) {
                $wpIds = $orders->pluck('wp_id')->all();

                // یک query bulk برای همه statusهای این chunk
                $statuses = $wp->table($prefix.'postmeta')
                    ->whereIn('post_id', $wpIds)
                    ->where('meta_key', 'status')
                    ->pluck('meta_value', 'post_id')
                    ->toArray();

                foreach ($orders as $order) {
                    if ($processed >= $total) return false;
                    $processed++;

                    $wpStatusRaw = $statuses[$order->wp_id] ?? null;
                    if ($wpStatusRaw === null) {
                        $missing++;
                        $bar->advance();
                        continue;
                    }

                    $newStatus = OrderStatus::fromWpCode((int) $wpStatusRaw);
                    if (! $newStatus) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    $currentStatus = $order->status instanceof OrderStatus
                        ? $order->status->value
                        : (string) $order->status;

                    if ($newStatus->value === $currentStatus) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }

                    // به‌روزرسانی + لاگ
                    DB::table('crm_orders')
                        ->where('id', $order->id)
                        ->update(['status' => $newStatus->value, 'updated_at' => now()]);

                    try {
                        OrderStatusLog::create([
                            'order_id' => $order->id,
                            'from_status' => $currentStatus,
                            'to_status' => $newStatus->value,
                            'note' => 'sync from WP (resync-order-statuses-from-wp)',
                            'changed_by' => null,
                            'created_at' => now(),
                        ]);
                    } catch (\Throwable $e) {
                        // لاگ نشد، مهم نیست
                    }

                    $changed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("سفارش‌های پردازش‌شده: {$processed}");
        $this->info("تغییر یافت: {$changed}");
        $this->line("بدون تغییر: {$skipped}");
        if ($missing > 0) $this->warn("روی WP وجود نداشت: {$missing}");

        return self::SUCCESS;
    }

    private function configureWpConnection(): void
    {
        config(['database.connections.wp_crm' => [
            'driver'    => 'mysql',
            'host'      => env('WP_DB_HOST', '127.0.0.1'),
            'port'      => (int) env('WP_DB_PORT', 3306),
            'database'  => env('WP_DB_NAME', 'crmtamironline_db_new'),
            'username'  => env('WP_DB_USER', 'crmtamironline_db_new'),
            'password'  => env('WP_DB_PASS', 'Rayanew_0935'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);
    }
}
