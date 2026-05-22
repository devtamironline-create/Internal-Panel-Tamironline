<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;

/**
 * بررسی کامل وضعیت یک سفارش — Panel + WP + status logs.
 * Read-only.
 */
class InspectOrder extends Command
{
    protected $signature = 'crm:inspect-order {order_id : id سفارش در Panel}';
    protected $description = 'نمایش وضعیت سفارش در Panel + WP + status logs (read-only)';

    public function handle(): int
    {
        $order = Order::find($this->argument('order_id'));
        if (! $order) {
            $this->error('سفارش پیدا نشد.');
            return self::FAILURE;
        }

        $this->info('━━━ Panel ─────────────────────────────────');
        $this->table(['فیلد', 'مقدار'], [
            ['id', $order->id],
            ['wp_id', $order->wp_id ?? '—'],
            ['order_code', $order->order_code],
            ['status (فعلی)', $order->status instanceof OrderStatus ? $order->status->label() . ' (' . $order->status->value . ')' : (string) $order->status],
            ['technician_id', $order->technician_id ?? '—'],
            ['created_at', $order->created_at?->format('Y-m-d H:i:s')],
            ['updated_at', $order->updated_at?->format('Y-m-d H:i:s')],
        ]);

        $this->newLine();
        $this->info('━━━ Panel status logs (تاریخچه) ──────────');
        $logs = OrderStatusLog::where('order_id', $order->id)
            ->orderByDesc('created_at')
            ->limit(20)->get();

        if ($logs->isEmpty()) {
            $this->line('  هیچ لاگ Panel-ای ثبت نشده.');
        } else {
            $rows = [];
            foreach ($logs as $l) {
                $rows[] = [
                    $l->created_at?->format('Y-m-d H:i'),
                    $l->from_status ?? '—',
                    $l->to_status,
                    $l->changed_by ?? '—',
                    mb_substr((string) ($l->note ?? ''), 0, 50),
                ];
            }
            $this->table(['date', 'from', 'to', 'by', 'note'], $rows);
        }

        if (! $order->wp_id) {
            return self::SUCCESS;
        }

        // WP side
        $this->configureWpConnection();
        try {
            $wp = DB::connection('wp_crm');
            $wp->getPdo();
        } catch (\Throwable $e) {
            $this->warn('اتصال به WP DB ناموفق.');
            return self::SUCCESS;
        }
        $prefix = env('WP_DB_PREFIX', 'or_');

        $wpPost = $wp->table($prefix.'posts')->where('ID', $order->wp_id)->first();
        $wpMeta = $wp->table($prefix.'postmeta')
            ->where('post_id', $order->wp_id)
            ->whereIn('meta_key', ['status', 'technician_id', 'price_customer', 'cost_price', 'total_invoice'])
            ->pluck('meta_value', 'meta_key')->toArray();

        $wpStatus = $wpMeta['status'] ?? null;
        $wpEnum = $wpStatus !== null ? OrderStatus::fromWpCode((int) $wpStatus) : null;

        $this->newLine();
        $this->info('━━━ WP ──────────────────────────────────');
        $this->table(['فیلد', 'مقدار'], [
            ['ID', $order->wp_id],
            ['post_title', $wpPost?->post_title ?? '—'],
            ['post_date', $wpPost?->post_date ?? '—'],
            ['post_modified', $wpPost?->post_modified ?? '—'],
            ['status_raw (meta)', $wpStatus ?? '—'],
            ['status_label', $wpEnum ? $wpEnum->label() . ' (' . $wpEnum->value . ')' : '—'],
            ['technician_id (meta)', $wpMeta['technician_id'] ?? '—'],
            ['price_customer', isset($wpMeta['price_customer']) ? number_format((int) $wpMeta['price_customer']) : '—'],
            ['total_invoice', isset($wpMeta['total_invoice']) ? number_format((int) $wpMeta['total_invoice']) : '—'],
        ]);

        $this->newLine();
        $panelStatus = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
        $wpStatusVal = $wpEnum?->value;
        if ($panelStatus !== $wpStatusVal) {
            $this->warn("⚠ اختلاف: Panel={$panelStatus} | WP={$wpStatusVal}");
            $this->line('برای sync با WP:');
            $this->line("  php artisan crm:resync-order-statuses-from-wp --since={$order->created_at?->format('Y-m-d')} --until={$order->created_at?->format('Y-m-d')}");
            $this->line('یا با by-wp-date اگر created_at متفاوت است.');
        } else {
            $this->info('✓ Panel و WP وضعیت یکسان دارند.');
        }

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
