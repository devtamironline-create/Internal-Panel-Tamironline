<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;

/**
 * نمایش side-by-side status سفارش‌ها در WP vs Panel — برای پیدا
 * کردن اختلافات. هیچ تغییری اعمال نمی‌کند.
 *
 * نمونه:
 *   php artisan crm:diff-order-statuses --since=2026-05-14 --until=2026-05-18
 *   php artisan crm:diff-order-statuses --since=2026-05-14 --by-wp-date
 *   php artisan crm:diff-order-statuses --only-diff
 */
class DiffOrderStatuses extends Command
{
    protected $signature = 'crm:diff-order-statuses
                            {--since= : YYYY-MM-DD}
                            {--until= : YYYY-MM-DD (شامل)}
                            {--by-wp-date : فیلتر بر اساس post_date WP به جای created_at Panel}
                            {--only-diff : فقط اختلاف‌ها نشان داده شوند}
                            {--limit=200 : حداکثر تعداد}';

    protected $description = 'مقایسه status سفارش‌ها بین Panel و WP (read-only)';

    public function handle(): int
    {
        $this->configureWpConnection();
        try {
            $wp = DB::connection('wp_crm');
            $wp->getPdo();
        } catch (\Throwable $e) {
            $this->error('اتصال به WP DB ناموفق.');
            return self::FAILURE;
        }
        $prefix = env('WP_DB_PREFIX', 'or_');

        $since = $this->option('since');
        $until = $this->option('until');
        $byWpDate = (bool) $this->option('by-wp-date');
        $limit = (int) $this->option('limit');

        // پیدا کردن سفارش‌ها — یا با Panel.created_at یا WP.post_date
        if ($byWpDate) {
            $wpQuery = $wp->table($prefix.'posts')->where('post_type', 'orders');
            if ($since) $wpQuery->where('post_date', '>=', $since . ' 00:00:00');
            if ($until) $wpQuery->where('post_date', '<', date('Y-m-d', strtotime($until . ' +1 day')));
            $wpIds = $wpQuery->limit($limit)->pluck('ID')->all();
            $orders = Order::whereIn('wp_id', $wpIds)->get(['id', 'wp_id', 'order_code', 'status', 'created_at']);
        } else {
            $q = Order::query()->whereNotNull('wp_id');
            if ($since) $q->where('created_at', '>=', $since);
            if ($until) $q->where('created_at', '<', date('Y-m-d', strtotime($until . ' +1 day')));
            $orders = $q->limit($limit)->get(['id', 'wp_id', 'order_code', 'status', 'created_at']);
        }

        if ($orders->isEmpty()) {
            $this->warn('سفارشی در این بازه نیست.');
            return self::SUCCESS;
        }

        $this->info("تعداد سفارش: {$orders->count()}");

        // WP statusها
        $wpStatuses = $wp->table($prefix.'postmeta')
            ->whereIn('post_id', $orders->pluck('wp_id')->all())
            ->where('meta_key', 'status')
            ->pluck('meta_value', 'post_id')->toArray();

        $rows = [];
        $sameCount = 0;
        $diffCount = 0;
        $missingCount = 0;

        foreach ($orders as $o) {
            $wpRaw = $wpStatuses[$o->wp_id] ?? null;
            $wpEnum = $wpRaw !== null ? OrderStatus::fromWpCode((int) $wpRaw) : null;
            $wpLabel = $wpEnum ? $wpEnum->label() . " ({$wpEnum->value})" : "—";
            $panelLabel = $o->status instanceof OrderStatus ? $o->status->label() . " ({$o->status->value})" : (string) $o->status;

            $isSame = $wpEnum && ($wpEnum->value === ($o->status instanceof OrderStatus ? $o->status->value : (string) $o->status));
            $isMissing = $wpRaw === null;

            if ($isMissing) {
                $missingCount++;
                $flag = '✗ WP ندارد';
            } elseif ($isSame) {
                $sameCount++;
                $flag = '✓';
            } else {
                $diffCount++;
                $flag = '⚠ متفاوت';
            }

            if ($this->option('only-diff') && $isSame) continue;

            $rows[] = [
                $o->id,
                $o->wp_id,
                $o->order_code,
                $o->created_at?->format('Y-m-d'),
                $panelLabel,
                $wpLabel,
                'WP_raw=' . ($wpRaw ?? '—'),
                $flag,
            ];
        }

        $this->table(
            ['id', 'wp_id', 'code', 'created', 'Panel', 'WP', 'meta', 'flag'],
            $rows
        );

        $this->newLine();
        $this->info("یکسان: {$sameCount}");
        if ($diffCount > 0) $this->warn("اختلاف: {$diffCount}");
        if ($missingCount > 0) $this->warn("WP status ندارد: {$missingCount}");

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
