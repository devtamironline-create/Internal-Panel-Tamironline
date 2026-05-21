<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Http\Controllers\Api\SyncOrderController;
use Modules\CRM\Models\Order;

/**
 * Pull سفارش‌های جدید از WP CRM که در Panel نیستند. مفید برای موقعی
 * که WP plugin push نکرده ولی سفارش‌ها در WP موجودند.
 *
 * منطق:
 *   ۱) همه post_type=orders در WP که wp_id آنها در Panel نیست را پیدا می‌کند
 *   ۲) postmeta هر سفارش را به فرمت SyncOrderController می‌فرستد
 *   ۳) همان upsertOne داخلی فراخوانی می‌شود (reuse منطق inbound)
 *
 * نمونه:
 *   php artisan crm:pull-new-orders-from-wp                  # dry-run
 *   php artisan crm:pull-new-orders-from-wp --since=2026-05-20
 *   php artisan crm:pull-new-orders-from-wp --apply
 */
class PullNewOrdersFromWp extends Command
{
    protected $signature = 'crm:pull-new-orders-from-wp
                            {--wp-id= : فقط یک سفارش خاص با این wp_id}
                            {--since= : فقط سفارش‌های بعد از این تاریخ (YYYY-MM-DD)}
                            {--limit=500 : حداکثر تعداد}
                            {--force : حتی اگر در Panel موجود است، دوباره وارد کن}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'Pull سفارش‌های WP که در Panel نیستند';

    public function handle(SyncOrderController $controller): int
    {
        $this->configureWpConnection();
        try {
            $wp = DB::connection('wp_crm');
            $wp->getPdo();
        } catch (\Throwable $e) {
            $this->error('اتصال به WP DB ناموفق: ' . $e->getMessage());
            return self::FAILURE;
        }
        $prefix = env('WP_DB_PREFIX', 'or_');
        $apply = (bool) $this->option('apply');

        $query = $wp->table($prefix.'posts')
            ->where('post_type', 'orders')
            ->whereIn('post_status', ['publish', 'private', 'draft', 'pending']);

        if ($wpId = $this->option('wp-id')) {
            $query->where('ID', (int) $wpId);
        }
        if ($since = $this->option('since')) {
            $query->where('post_date', '>=', $since . ' 00:00:00');
        }

        $allWpIds = $query->orderByDesc('ID')->limit((int) $this->option('limit'))->pluck('ID')->all();

        if (empty($allWpIds)) {
            $this->warn('سفارشی در WP پیدا نشد.');
            return self::SUCCESS;
        }

        // exclude existing (مگر --force)
        if ($this->option('force')) {
            $missingIds = $allWpIds;
            $existingWpIds = [];
        } else {
            $existingWpIds = Order::whereIn('wp_id', $allWpIds)->pluck('wp_id')->all();
            $missingIds = array_diff($allWpIds, $existingWpIds);
        }

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — سفارش WP بررسی‌شده: " . count($allWpIds));
        $this->info("از قبل در Panel: " . count($existingWpIds));
        $this->info("گم‌شده (قابل ایمپورت): " . count($missingIds));

        if (empty($missingIds)) {
            $this->info('✓ همه سفارش‌های WP در Panel موجودند.');
            return self::SUCCESS;
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:pull-new-orders-from-wp --apply');
            return self::SUCCESS;
        }

        // suppress outbound push
        app()->instance('crm.suppress_outbound_push', true);

        $created = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar(count($missingIds));
        $bar->start();

        foreach (array_chunk($missingIds, 50) as $chunk) {
            $posts = $wp->table($prefix.'posts')->whereIn('ID', $chunk)->get()->keyBy('ID');
            $metas = $wp->table($prefix.'postmeta')->whereIn('post_id', $chunk)->get();
            $metaByPost = [];
            foreach ($metas as $m) {
                $metaByPost[(int) $m->post_id][$m->meta_key] = $m->meta_value;
            }

            foreach ($posts as $pid => $post) {
                $data = $this->buildPayloadFromWp($pid, $post, $metaByPost[$pid] ?? []);

                try {
                    // مستقیم upsertOne استفاده کن — همان منطق inbound endpoint
                    $req = new \Illuminate\Http\Request();
                    $req->merge($data);
                    $controller->upsert($req);
                    $created++;
                } catch (\Throwable $e) {
                    $failed++;
                    \Illuminate\Support\Facades\Log::warning('pull_new_orders.failed', [
                        'wp_id' => $pid, 'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ ایجاد شد: {$created}");
        if ($failed > 0) $this->warn("✗ شکست خورد: {$failed} (در laravel.log جزئیات)");

        return self::SUCCESS;
    }

    /** ساخت payload شبیه آنچه WP plugin می‌فرستد. */
    private function buildPayloadFromWp(int $pid, $post, array $meta): array
    {
        $payload = [
            'wp_id' => $pid,
            'post_title' => (string) ($post->post_title ?? ''),
            'post_status' => (string) ($post->post_status ?? 'publish'),
            'post_date' => (string) ($post->post_date ?? null),
            'post_modified' => (string) ($post->post_modified ?? null),
            'post_author' => (int) ($post->post_author ?? 0),
        ];

        // همه postmeta را به‌صورت خام عبور می‌دهیم — کنترلر validation انجام می‌دهد
        foreach ($meta as $key => $value) {
            // سعی به decode JSON برای array fields
            if (in_array($key, ['piece_list', 'customer_price_list', 'buy_price_list'], true)) {
                $decoded = json_decode($value, true);
                $payload[$key] = is_array($decoded) ? $decoded : (is_string($value) ? maybe_unserialize_php($value) : []);
            } elseif (in_array($key, ['order_description_content', 'order_note_content', 'log_return'], true)) {
                $decoded = json_decode($value, true);
                $payload[$key] = is_array($decoded) ? $decoded : [];
            } else {
                $payload[$key] = $value;
            }
        }

        return $payload;
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

if (! function_exists('maybe_unserialize_php')) {
    function maybe_unserialize_php($data) {
        if (is_string($data) && preg_match('/^(a|s|i|d|b|N):/', $data)) {
            try { return @unserialize($data); } catch (\Throwable) {}
        }
        return [];
    }
}
