<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletArchiveTx;

/**
 * ایمپورت تراکنش‌های آرشیوی کیف‌پول از WP CRM به جدول
 * crm_wallet_archive_txs — فقط برای نمایش تاریخی.
 *
 * ⚠️ این داده‌ها هرگز در محاسبات وارد نمی‌شوند. مقصد یک جدول مستقل
 * (crm_wallet_archive_txs) است که هیچ جای دیگر در پنل خوانده نمی‌شود.
 *
 * منبع: WP `or_posts` با post_type='financial'.
 * متاهای مرتبط (هم‌سو با SyncFinancialController و WpPushService):
 *   - wallet=='1'        → wallet_charge (مبلغ = wallet_pay)
 *   - reward_type=='1'  → reward         (مبلغ = total_invoice)
 *   - reward_type=='2'  → penalty        (مبلغ = total_invoice)
 *   - total_invoice > 0 + بدون wallet/reward_type → commission (سهم شرکت
 *     از فاکتور). مبلغ منفی = total_invoice × percent / 100 محاسبه‌شده
 *     از روی تکنسین جاری (چون WP postmeta percent تاریخی نگه نمی‌دارد).
 *
 * idempotent: ایندکس unique روی wp_post_id جلوی duplicate را می‌گیرد.
 * می‌توان هر چندبار اجرا کرد، هر بار فقط رکوردهای جدید اضافه می‌شوند.
 *
 * نمونه:
 *   php artisan crm:wallet:import-archive-from-wp                    # dry-run
 *   php artisan crm:wallet:import-archive-from-wp --since=2024-01-01
 *   php artisan crm:wallet:import-archive-from-wp --tech=436
 *   php artisan crm:wallet:import-archive-from-wp --apply
 *   php artisan crm:wallet:import-archive-from-wp --apply --limit=50000
 */
class ImportWalletArchiveFromWp extends Command
{
    protected $signature = 'crm:wallet:import-archive-from-wp
                            {--since= : فقط بعد از این تاریخ (YYYY-MM-DD)}
                            {--limit=20000 : حداکثر تعداد پست بررسی‌شده}
                            {--tech= : فقط برای یک تکنسین (panel id)}
                            {--debug-skipped : چاپ متاهای ۵ مورد اول skip شده (برای تشخیص نوع)}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'ایمپورت تراکنش‌های آرشیوی کیف‌پول از WP به جدول crm_wallet_archive_txs (فقط نمایش — خارج از محاسبه)';

    public function handle(): int
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
        $limit = max(1, (int) $this->option('limit'));
        $since = $this->option('since');
        $techPanelId = $this->option('tech') ? (int) $this->option('tech') : null;

        // اگر --tech داده شده، wp_id متناظر را پیدا کن تا فقط فاینانشال‌های
        // همان تکنسین (متای technician_wp_id) را بکشیم. سرعت بسیار بیشتر.
        $techWpId = null;
        if ($techPanelId) {
            $tech = Technician::find($techPanelId);
            if (! $tech) {
                $this->error("تکنسین panel id={$techPanelId} پیدا نشد.");
                return self::FAILURE;
            }
            $techWpId = (int) $tech->wp_id;
            if (! $techWpId) {
                $this->error("تکنسین {$techPanelId} wp_id ندارد.");
                return self::FAILURE;
            }
            $this->info("محدود به تکنسین: {$techPanelId} (wp_id={$techWpId})");
        }

        // ─── کوئری پست‌های financial در WP ─────────────────────────
        $postsQ = $wp->table($prefix . 'posts')
            ->where('post_type', 'financial');

        if ($since) {
            $postsQ->where('post_date', '>=', $since . ' 00:00:00');
        }

        // اگر تکنسین مشخص شده، با post_author و یا با postmeta فیلتر کن.
        // post_author در پلاگین مالی WP معمولاً همان technician_wp_id است.
        if ($techWpId) {
            $postsQ->where(function ($q) use ($wp, $prefix, $techWpId) {
                $q->where('post_author', $techWpId)
                  ->orWhereExists(function ($sub) use ($wp, $prefix, $techWpId) {
                      $sub->selectRaw('1')->from($prefix . 'postmeta as pm')
                          ->whereColumn('pm.post_id', $prefix . 'posts.ID')
                          ->where('pm.meta_key', 'technician_wp_id')
                          ->where('pm.meta_value', (string) $techWpId);
                  });
            });
        }

        $postIds = $postsQ->orderBy('ID')->limit($limit)->pluck('ID')->all();

        if (empty($postIds)) {
            $this->warn('هیچ post_type=financial در WP پیدا نشد.');
            return self::SUCCESS;
        }

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . ' — financial postهای WP بررسی‌شده: ' . count($postIds));

        // قبلاً ایمپورت‌شده‌ها را شمارش می‌کنیم — ولی همه را re-process می‌کنیم
        // تا فیلدهای ممکن گم‌شده (مثل order_wp_id) به‌روز شوند (upsert).
        // ایمپورت idempotent است: upsert بر اساس wp_post_id unique.
        $existing = WalletArchiveTx::whereIn('wp_post_id', $postIds)
            ->pluck('wp_post_id')
            ->all();
        $toProcess = $postIds; // همه را re-process کن (upsert رکورد موجود را به‌روز می‌کند)
        $this->line('از قبل ایمپورت‌شده: ' . count($existing) . ' (در صورت تغییر، به‌روز می‌شوند)');
        $this->line('کل برای پردازش (insert/update): ' . count($toProcess));

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:wallet:import-archive-from-wp --apply' . ($techPanelId ? " --tech={$techPanelId}" : '') . ($since ? " --since={$since}" : ''));
            return self::SUCCESS;
        }

        // map از technician_wp_id → panel id (cache برای سرعت)
        $techWpToPanel = Technician::whereNotNull('wp_id')
            ->pluck('id', 'wp_id')
            ->all();

        // map از panel id → [percent, tech_per_of_all, type_of_calc_tech] برای
        // محاسبه‌ی company_share تراکنش‌های نوع commission. این داده‌ی فعلی
        // تکنسین است (WP postmeta percent تاریخی نگه نمی‌دارد، پس نزدیک‌ترین
        // برآورد ممکن است).
        $techConfig = Technician::query()
            ->select(['id', 'percent', 'tech_per_of_all', 'type_of_calc_tech'])
            ->get()
            ->mapWithKeys(fn ($t) => [(int) $t->id => [
                'percent' => max(0, min(100, (int) ($t->percent ?? 0))),
                'tech_per_of_all' => max(0, min(100, (int) ($t->tech_per_of_all ?? 0))),
                'calc_type' => (string) ($t->type_of_calc_tech ?? ''),
            ]])
            ->all();

        $inserted = 0;
        $skipped = 0;
        $skippedSamples = []; // برای --debug-skipped

        $bar = $this->output->createProgressBar(count($toProcess));
        $bar->start();

        foreach (array_chunk($toProcess, 200) as $chunk) {
            $posts = $wp->table($prefix . 'posts')
                ->whereIn('ID', $chunk)
                ->get(['ID', 'post_author', 'post_date', 'post_status'])
                ->keyBy('ID');

            $metas = $wp->table($prefix . 'postmeta')
                ->whereIn('post_id', $chunk)
                ->get(['post_id', 'meta_key', 'meta_value']);

            $metaByPost = [];
            foreach ($metas as $m) {
                $metaByPost[(int) $m->post_id][(string) $m->meta_key] = (string) $m->meta_value;
            }

            $batchInsert = [];
            foreach ($posts as $pid => $post) {
                $meta = $metaByPost[(int) $pid] ?? [];

                // تشخیص نوع تراکنش
                $row = $this->buildArchiveRow((int) $pid, $post, $meta, $techWpToPanel, $techConfig);
                if (! $row) {
                    $skipped++;
                    if ($this->option('debug-skipped') && count($skippedSamples) < 5) {
                        $skippedSamples[] = [
                            'wp_post_id' => $pid,
                            'meta' => $meta,
                        ];
                    }
                    $bar->advance();
                    continue;
                }
                $batchInsert[] = $row;
                $bar->advance();
            }

            if (! empty($batchInsert)) {
                // upsert بر اساس wp_post_id — رکوردهای موجود به‌روز می‌شوند،
                // جدیدها insert می‌شوند. این جلوی duplicate را می‌گیرد و
                // فیلدهای گم‌شده در run‌های قبلی (مثل order_wp_id) را پر می‌کند.
                DB::table('crm_wallet_archive_txs')->upsert(
                    $batchInsert,
                    ['wp_post_id'],                     // unique by
                    [                                    // columns to update if exists
                        'technician_id', 'technician_wp_id', 'order_wp_id',
                        'type', 'amount', 'note', 'refid', 'payment_status',
                        'wp_created_at',
                        // imported_at را به‌روز نمی‌کنیم تا تاریخ ایمپورت اول حفظ شود
                    ]
                );
                $inserted += count($batchInsert);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ پردازش شد (insert/update): {$inserted}");
        if ($skipped > 0) {
            $this->line("  رد شده (نوع نامشخص یا داده ناقص): {$skipped}");
        }

        if (! empty($skippedSamples)) {
            $this->newLine();
            $this->line('— نمونه‌های skip شده —');
            foreach ($skippedSamples as $idx => $s) {
                $this->line(($idx + 1) . ") wp_post_id={$s['wp_post_id']}:");
                foreach ($s['meta'] as $k => $v) {
                    $v = mb_substr((string) $v, 0, 80);
                    $this->line("    {$k} → {$v}");
                }
                $this->newLine();
            }
        }

        $this->newLine();
        $this->comment('⚠ این داده‌ها در محاسبات کیف‌پول وارد نمی‌شوند — فقط نمایش در تب «تاریخچهٔ قدیمی».');

        return self::SUCCESS;
    }

    /**
     * @param array<int,array{percent:int, tech_per_of_all:int, calc_type:string}> $techConfig
     * @return array<string,mixed>|null  null = رد کن (نوع تراکنش از روی متاها مشخص نشد)
     */
    private function buildArchiveRow(int $pid, $post, array $meta, array $techWpToPanel, array $techConfig): ?array
    {
        $now = now();

        // technician_wp_id از متا یا fallback به post_author
        $techWpId = (int) ($meta['technician_wp_id'] ?? $post->post_author ?? 0) ?: null;

        // order_wp_id — کلید واقعی postmeta در WP «order_id» است (نه order_wp_id).
        // هر دو را امتحان می‌کنیم برای پشتیبانی از payloadهای مختلف.
        $orderWpId = null;
        if (! empty($meta['order_id'])) {
            $orderWpId = (int) $meta['order_id'];
        } elseif (! empty($meta['order_wp_id'])) {
            $orderWpId = (int) $meta['order_wp_id'];
        }
        if ($orderWpId === 0) $orderWpId = null;
        $paymentStatus = (int) ($meta['payment_status'] ?? 0);
        $refid = $meta['refid'] ?? null;
        $description = $meta['description'] ?? null;
        $rewardDesc = $meta['rewardDesc'] ?? null;
        $techPanelId = $techWpId ? ($techWpToPanel[$techWpId] ?? null) : null;

        // تشخیص type + amount از روی متاها
        $type = null;
        $amount = 0;
        $extraNoteParts = [];

        if (! empty($meta['wallet']) && (string) $meta['wallet'] === '1') {
            // شارژ کیف‌پول
            $type = 'wallet_charge';
            $amount = abs((int) ($meta['wallet_pay'] ?? 0));
        } elseif (! empty($meta['reward_type'])) {
            $rt = (string) $meta['reward_type'];
            if ($rt === '1') {
                $type = 'reward';
                $amount = abs((int) ($meta['total_invoice'] ?? 0));
            } elseif ($rt === '2') {
                $type = 'penalty';
                $amount = -abs((int) ($meta['total_invoice'] ?? 0));
            }
        } elseif (! empty($meta['total_invoice']) && (int) $meta['total_invoice'] > 0) {
            // فاکتور — تراکنش منفی commission (سهم شرکت). محاسبه با درصد
            // فعلی تکنسین (WP percent تاریخی نگه نمی‌دارد). هم‌سو با
            // InvoiceService::generateForOrder که amount = -company_share
            // می‌نویسد.
            $totalInvoice = abs((int) $meta['total_invoice']);
            $cfg = $techPanelId ? ($techConfig[$techPanelId] ?? null) : null;

            if (! $cfg) {
                // تکنسین در پنل نیست یا تنظیمی ندارد → نمی‌توان company_share
                // محاسبه کرد، رد کن.
                return null;
            }

            // هم‌سازی با CommissionCalculator:
            //   - internal (calc_type=='1') → company_share = total * tech_per_of_all / 100
            //   - external (پیش‌فرض) → company_share = total * percent / 100
            $isInternal = ($cfg['calc_type'] === '1' || $cfg['calc_type'] === 'internal');
            $usedPercent = $isInternal ? $cfg['tech_per_of_all'] : $cfg['percent'];
            $companyShare = intdiv($totalInvoice * $usedPercent, 100);

            if ($companyShare === 0) {
                return null;
            }

            $type = 'commission';
            $amount = -$companyShare; // منفی: کسر از کیف‌پول تکنسین

            $extraNoteParts[] = 'سهم شرکت از فاکتور';
            $extraNoteParts[] = 'مبلغ فاکتور: ' . number_format($totalInvoice);
            $extraNoteParts[] = 'درصد: ' . $usedPercent . '%';
            if ($isInternal) {
                $extraNoteParts[] = '(internal)';
            }
        }

        if (! $type || $amount === 0) {
            return null;
        }

        $noteParts = $extraNoteParts;
        if ($description) $noteParts[] = $description;
        if ($rewardDesc) $noteParts[] = $rewardDesc;
        if ($refid) $noteParts[] = 'refid: ' . $refid;
        $note = ! empty($noteParts) ? implode(' — ', $noteParts) : null;

        return [
            'wp_post_id' => $pid,
            'technician_id' => $techPanelId,
            'technician_wp_id' => $techWpId,
            'order_wp_id' => $orderWpId,
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
            'refid' => $refid ? mb_substr((string) $refid, 0, 100) : null,
            'payment_status' => $paymentStatus,
            'wp_created_at' => $post->post_date ?: null,
            'imported_at' => $now,
        ];
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
