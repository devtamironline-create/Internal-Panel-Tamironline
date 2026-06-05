<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\SyncLog;

/**
 * تشخیص علت گم شدن سفارش‌های جدید در سینک WP → Panel.
 *
 * تحلیل:
 *   ۱) آمار کلی sync logs (success / failed / skipped)
 *   ۲) لیست wp_idهایی که در WP جدید هستند ولی در Panel نیامده‌اند
 *   ۳) برای هر گم‌شده، آخرین log entry را نشان می‌دهد (اگر تلاش شده)
 *   ۴) دسته‌بندی خطاها بر اساس پیام (کشف الگو)
 *   ۵) چک batch responseها برای دیدن «every other skipped» pattern
 *
 * نمونه:
 *   php artisan crm:diagnose-order-sync
 *   php artisan crm:diagnose-order-sync --hours=6
 *   php artisan crm:diagnose-order-sync --show-payload --hours=2
 */
class DiagnoseOrderSync extends Command
{
    protected $signature = 'crm:diagnose-order-sync
                            {--hours=24 : بازه‌ی بررسی (ساعت)}
                            {--show-payload : نمایش payload خام برای موارد ناموفق}
                            {--limit=50 : حداکثر تعداد ردیف نمایش}';

    protected $description = 'تشخیص علت گم شدن سفارش‌های جدید در سینک WP → Panel';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        $since = now()->subHours($hours);

        $this->info("📊 تحلیل سینک سفارشات ({$hours} ساعت اخیر)");
        $this->newLine();

        // ─── 1) آمار کلی ─────────────────────────────────────
        $this->line('═══ ۱) آمار sync logs inbound order ═══');
        $stats = SyncLog::where('direction', 'inbound')
            ->where('entity_type', 'order')
            ->where('created_at', '>=', $since)
            ->selectRaw('status, http_status, COUNT(*) cnt')
            ->groupBy('status', 'http_status')
            ->orderByDesc('cnt')
            ->get();

        if ($stats->isEmpty()) {
            $this->warn('  هیچ لاگ inbound order در این بازه نیست.');
        } else {
            $rows = $stats->map(fn ($r) => [$r->status, $r->http_status, number_format($r->cnt)])->all();
            $this->table(['status', 'http_status', 'count'], $rows);
        }

        // ─── 2) دسته‌بندی پیام‌های خطا ─────────────────────
        $this->newLine();
        $this->line('═══ ۲) دسته‌بندی پیام‌های خطا / skip ═══');
        $errors = SyncLog::where('direction', 'inbound')
            ->where('entity_type', 'order')
            ->where('created_at', '>=', $since)
            ->whereIn('status', ['failed', 'skipped'])
            ->selectRaw('error_message, COUNT(*) cnt, MIN(wp_id) as sample_wp_id')
            ->groupBy('error_message')
            ->orderByDesc('cnt')
            ->get();

        if ($errors->isEmpty()) {
            $this->info('  ✓ خطا یا skip ثبت‌شده نیست.');
        } else {
            $rows = $errors->map(fn ($r) => [
                number_format($r->cnt),
                $r->sample_wp_id,
                mb_substr($r->error_message ?? '—', 0, 100),
            ])->all();
            $this->table(['count', 'sample wp_id', 'error / reason'], $rows);
        }

        // ─── 3) لاگ‌های batch — چک «every other» pattern ─────
        $this->newLine();
        $this->line('═══ ۳) batch responseها (skipped vs created vs errors) ═══');
        $batches = SyncLog::where('direction', 'inbound')
            ->where('entity_type', 'order')
            ->where('endpoint', 'like', '%orders/batch%')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'http_status', 'response', 'created_at']);

        if ($batches->isEmpty()) {
            $this->warn('  هیچ batch call در این بازه نیست.');
        } else {
            $rows = [];
            $totalIn = 0; $totalCreated = 0; $totalUpdated = 0; $totalErrors = 0;
            foreach ($batches as $b) {
                $resp = is_array($b->response) ? $b->response : (is_string($b->response) ? json_decode($b->response, true) : []);
                $total = (int) ($resp['total'] ?? 0);
                $created = (int) ($resp['created'] ?? 0);
                $updated = (int) ($resp['updated'] ?? 0);
                $errs = isset($resp['errors']) && is_array($resp['errors']) ? count($resp['errors']) : 0;
                $totalIn += $total;
                $totalCreated += $created;
                $totalUpdated += $updated;
                $totalErrors += $errs;
                $miss = $total - $created - $updated - $errs;
                $rows[] = [
                    $b->id,
                    $b->created_at,
                    $total,
                    $created,
                    $updated,
                    $errs,
                    $miss > 0 ? "⚠ $miss" : '0',
                ];
            }
            $this->table(['log_id', 'at', 'total', 'created', 'updated', 'errors', 'unaccounted'], $rows);
            $this->info("  جمع: total=$totalIn  created=$totalCreated  updated=$totalUpdated  errors=$totalErrors");
            if ($totalIn > 0) {
                $missingRatio = round((($totalIn - $totalCreated - $totalUpdated - $totalErrors) / $totalIn) * 100, 1);
                if ($missingRatio > 0) {
                    $this->error("  ⚠ {$missingRatio}% آیتم‌ها در batch response جا افتاده‌اند (احتمالاً skipped ولی شمارش نشده‌اند)");
                }
            }
        }

        // ─── 4) سفارش‌های WP که در پنل نیستند ────────────────
        $this->newLine();
        $this->line('═══ ۴) سفارش‌های WP که در Panel نیستند ═══');
        try {
            $this->configureWpConnection();
            $wp = DB::connection('wp_crm');
            $wp->getPdo();

            $prefix = env('WP_DB_PREFIX', 'or_');
            $wpRecent = $wp->table($prefix . 'posts')
                ->where('post_type', 'orders')
                ->whereIn('post_status', ['publish', 'private', 'draft', 'pending'])
                ->where('post_date', '>=', $since->toDateTimeString())
                ->orderByDesc('ID')
                ->limit(500)
                ->pluck('ID')
                ->all();

            if (empty($wpRecent)) {
                $this->warn('  در این بازه سفارش جدید در WP پیدا نشد.');
            } else {
                $existing = Order::whereIn('wp_id', $wpRecent)->pluck('wp_id')->all();
                $missing = array_values(array_diff($wpRecent, $existing));

                $this->info("  WP در این بازه: " . count($wpRecent));
                $this->info("  در Panel موجود: " . count($existing));
                $this->info("  گم‌شده: " . count($missing));

                if (! empty($missing)) {
                    // بررسی الگوی every-other (تفاضل ID متوالی)
                    sort($missing);
                    $diffs = [];
                    for ($i = 1; $i < count($missing); $i++) {
                        $diffs[] = $missing[$i] - $missing[$i - 1];
                    }
                    if (! empty($diffs)) {
                        $avg = array_sum($diffs) / count($diffs);
                        $this->line("  میانگین فاصله wp_id گم‌شده‌ها: " . round($avg, 2));
                        if ($avg > 1.5 && $avg < 3.5) {
                            $this->warn('  ⚠ الگوی «یکی در میان» تأیید می‌شود — فاصله ~۲ یعنی هر دومی گم می‌شود.');
                        }
                    }

                    // برای ۲۰ تای اول لاگ خود را پیدا کن
                    $sample = array_slice($missing, 0, 20);
                    $logs = SyncLog::whereIn('wp_id', $sample)
                        ->where('entity_type', 'order')
                        ->where('direction', 'inbound')
                        ->where('created_at', '>=', $since)
                        ->orderByDesc('created_at')
                        ->get()
                        ->keyBy('wp_id');

                    $rows = [];
                    foreach ($sample as $wpId) {
                        $log = $logs->get($wpId);
                        $rows[] = [
                            $wpId,
                            $log?->status ?? '— بدون لاگ —',
                            $log?->http_status ?? '—',
                            mb_substr($log?->error_message ?? '', 0, 80),
                        ];
                    }
                    $this->table(['wp_id', 'last log status', 'http', 'error'], $rows);

                    if ($this->option('show-payload')) {
                        $this->newLine();
                        $this->line('--- payload ها برای ۵ مورد اول ---');
                        foreach (array_slice($sample, 0, 5) as $wpId) {
                            $log = $logs->get($wpId);
                            if ($log && $log->payload) {
                                $this->line("\n[wp_id={$wpId}]");
                                $p = is_array($log->payload) ? $log->payload : json_decode($log->payload, true);
                                $this->line(json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->error('  اتصال WP DB ناموفق: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('✓ تمام. برای ایمپورت گم‌شده‌ها:  php artisan crm:pull-new-orders-from-wp --apply');

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
