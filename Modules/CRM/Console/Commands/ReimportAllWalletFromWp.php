<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * دو کار را یکجا انجام می‌دهد:
 *
 *   ۱) حذف opening balanceهای WP (تراکنش‌هایی با note شامل «موجودی افتتاحیه از WP CRM»)
 *   ۲) برای هر تکنسین، تمام wallet eventهای WP (شارژ/پاداش/جریمه) را
 *      وارد Panel می‌کند — invoiceها وارد نمی‌شوند چون superseded هستند.
 *
 * فقط wallet eventها وارد می‌شوند (نه فاکتورها):
 *   - wallet=1 → WalletCharge
 *   - reward_type=1 → Reward
 *   - reward_type=0 → Penalty
 *
 * نمونه:
 *   php artisan crm:reimport-all-wallet-from-wp                 # dry-run
 *   php artisan crm:reimport-all-wallet-from-wp --tech=42       # یک تکنسین
 *   php artisan crm:reimport-all-wallet-from-wp --apply --recompute
 */
class ReimportAllWalletFromWp extends Command
{
    protected $signature = 'crm:reimport-all-wallet-from-wp
                            {--tech= : فقط یک تکنسین (id Panel)}
                            {--apply : اعمال (پیش‌فرض dry-run)}
                            {--recompute : بعد، wallet:recompute-balances اجرا شود}';

    protected $description = 'حذف opening balanceها + import همه wallet eventها از WP CRM';

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

        // ───────────── گام ۱: شمارش/حذف opening balanceها ────────
        $openingQuery = DB::table('crm_tech_wallet_transactions')
            ->where('note', 'LIKE', '%موجودی افتتاحیه%');

        if ($techId = $this->option('tech')) {
            $openingQuery->where('technician_id', (int) $techId);
        }

        $openingCount = (clone $openingQuery)->count();
        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — Opening balanceها: {$openingCount} (حذف خواهد شد)");

        if ($apply && $openingCount > 0) {
            (clone $openingQuery)->delete();
            $this->line("  ✓ {$openingCount} opening balance حذف شد.");
        }

        // ───────────── گام ۲: ایمپورت wallet eventهای WP ─────────
        $techsQuery = Technician::query()->whereNotNull('wp_id');
        if ($techId) {
            $techsQuery->where('id', (int) $techId);
        }
        $techs = $techsQuery->get(['id', 'wp_id', 'firstname_tech', 'first_name', 'last_name']);

        $this->info("تکنسین‌های هدف: {$techs->count()}");
        $this->newLine();

        app()->instance('crm.suppress_outbound_push', true);

        $totalImported = 0;
        $totalSkipped = 0;
        $totalAmount = 0;
        $perTechStats = [];

        $bar = $this->output->createProgressBar($techs->count());
        $bar->start();

        foreach ($techs as $tech) {
            $stats = $this->importTechWalletFromWp($wp, $prefix, $tech, $apply);
            $totalImported += $stats['imported'];
            $totalSkipped += $stats['skipped'];
            $totalAmount += $stats['amount'];

            if ($stats['imported'] > 0) {
                $perTechStats[] = [
                    $tech->id,
                    $tech->wp_id,
                    mb_substr($tech->firstname_tech ?: trim($tech->first_name . ' ' . $tech->last_name), 0, 25),
                    $stats['imported'],
                    number_format($stats['amount']),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (! empty($perTechStats)) {
            $this->info('ایمپورت‌های هر تکنسین:');
            $this->table(['id', 'wp_id', 'نام', 'تعداد', 'جمع amount'], $perTechStats);
        }

        $this->newLine();
        $this->info("کل ایمپورت: {$totalImported} تراکنش");
        $this->info("جمع amount: " . number_format($totalAmount));
        if ($totalSkipped > 0) {
            $this->line("رد شده (قبلاً در Panel بود): {$totalSkipped}");
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:reimport-all-wallet-from-wp --apply --recompute');
        } else {
            if ($this->option('recompute')) {
                $this->newLine();
                $this->info('در حال recompute balances...');
                if ($techId) {
                    Artisan::call('crm:wallet:recompute-balances', ['--technician' => (int) $techId]);
                } else {
                    Artisan::call('crm:wallet:recompute-balances');
                }
                $this->info('✓ موجودی‌ها بازخوانی شدند.');
            } else {
                $this->warn('گام بعدی: php artisan crm:wallet:recompute-balances');
            }
        }

        return self::SUCCESS;
    }

    /**
     * ایمپورت wallet eventهای یک تکنسین از WP DB.
     *
     * @return array{imported:int, skipped:int, amount:int}
     */
    private function importTechWalletFromWp($wp, string $prefix, Technician $tech, bool $apply): array
    {
        // پیدا کردن همه financial postهای تکنسین — دو روش جداگانه
        $ids1 = $wp->table($prefix.'postmeta')
            ->whereIn('meta_key', [
                'technician_id', 'technician', 'tech_id',
                'tech', 'to_tech', 'tech_user_id', 'tech_user',
            ])
            ->where('meta_value', (string) $tech->wp_id)
            ->pluck('post_id')->all();

        $ids2 = $wp->table($prefix.'posts')
            ->where('post_type', 'financial')
            ->where('post_author', $tech->wp_id)
            ->pluck('ID')->all();

        $allIds = array_unique(array_merge((array) $ids1, (array) $ids2));
        if (empty($allIds)) {
            return ['imported' => 0, 'skipped' => 0, 'amount' => 0];
        }

        $posts = $wp->table($prefix.'posts')
            ->whereIn('ID', $allIds)
            ->where('post_type', 'financial')
            ->whereIn('post_status', ['publish', 'private', 'draft', 'pending'])
            ->select('ID', 'post_date')
            ->get()
            ->keyBy('ID');

        if ($posts->isEmpty()) {
            return ['imported' => 0, 'skipped' => 0, 'amount' => 0];
        }

        $metas = $wp->table($prefix.'postmeta')
            ->whereIn('post_id', $posts->keys()->all())
            ->get();
        $metaByPost = [];
        foreach ($metas as $m) {
            $metaByPost[(int) $m->post_id][$m->meta_key] = $m->meta_value;
        }

        $imported = 0;
        $skipped = 0;
        $amountSum = 0;

        foreach ($posts as $pid => $post) {
            $meta = $metaByPost[(int) $pid] ?? [];
            $paymentStatus = (int) ($meta['payment_status'] ?? 0);
            $wallet = (int) ($meta['wallet'] ?? 0);
            $rewardType = $meta['reward_type'] ?? null;

            // فقط wallet/reward/penalty — invoiceها (order_id > 0 بدون این‌ها) skip
            $type = null;
            $amount = 0;

            if ($wallet === 1) {
                $type = WalletTxType::WalletCharge;
                $amount = (int) ($meta['wallet_pay'] ?? 0);
            } elseif ($rewardType !== null && $rewardType !== '') {
                if ((int) $rewardType === 1) {
                    $type = WalletTxType::Reward;
                    $amount = abs((int) ($meta['total_invoice'] ?? 0));
                } else {
                    $type = WalletTxType::Penalty;
                    $amount = -1 * abs((int) ($meta['total_invoice'] ?? 0));
                }
            } else {
                // invoice یا چیز دیگری — این command فقط wallet می‌برد
                continue;
            }

            if ($amount === 0) {
                continue;
            }

            // فیلتر: فقط رخدادهای پرداخت‌شده (مطابق منطق WP DebtCalc)
            if ($paymentStatus !== 1 && $wallet !== 1 && ($rewardType === null || $rewardType === '')) {
                continue;
            }

            // اگر قبلاً با wp_id درج شده، skip
            if (DB::table('crm_tech_wallet_transactions')->where('wp_id', (int) $pid)->exists()) {
                $skipped++;
                continue;
            }

            $orderId = null;
            if (! empty($meta['order_id'])) {
                $orderId = Order::where('wp_id', (int) $meta['order_id'])->value('id');
            }

            if ($apply) {
                DB::table('crm_tech_wallet_transactions')->insert([
                    'wp_id' => (int) $pid,
                    'technician_id' => $tech->id,
                    'order_id' => $orderId,
                    'invoice_id' => null,
                    'type' => $type->value,
                    'amount' => $amount,
                    'balance_after' => 0, // by recompute
                    'note' => mb_substr((string) ($meta['rewardDesc'] ?? $meta['description'] ?? ''), 0, 1000),
                    'created_by' => null,
                    'created_at' => $this->safeDate($post->post_date) ?? now(),
                    'updated_at' => now(),
                ]);
            }

            $imported++;
            $amountSum += $amount;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'amount' => $amountSum];
    }

    private function safeDate(?string $v): ?string
    {
        if (! $v || str_starts_with($v, '0000-')) return null;
        try {
            return \Carbon\Carbon::parse($v)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
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
