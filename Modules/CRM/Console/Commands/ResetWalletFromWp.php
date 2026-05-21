<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Technician;

/**
 * Reset کامل کیف‌پول تکنسین‌ها بر اساس مانده فعلی WP CRM.
 *
 * منطق:
 *   ۱) برای هر تکنسین، مانده او در WP محاسبه می‌شود طبق فرمول WP:
 *        balance = sum(wallet_pay paid=1 wallet=1)                  // شارژها
 *                + sum(total_invoice paid=1 reward_type=1)           // پاداش‌ها
 *                − sum(total_invoice paid=1 reward_type=0)           // جریمه‌ها
 *                − sum(total_invoice × percent_current / 100)         // سهم شرکت
 *
 *   ۲) با --apply:
 *        - همه wallet_txs و invoices تکنسین در Panel backup می‌شوند
 *        - wallet_txs حذف می‌شوند
 *        - invoices به‌عنوان superseded علامت‌گذاری می‌شوند
 *        - یک تراکنش «موجودی افتتاحیه» با amount=WP balance درج می‌شود
 *        - wallet_balance برابر این مقدار ست می‌شود
 *
 * Backup در storage/app/crm/wallet-reset-{timestamp}.json ذخیره می‌شود.
 *
 * نمونه:
 *   php artisan crm:reset-wallet-from-wp                    # گزارش
 *   php artisan crm:reset-wallet-from-wp --tech=42          # یک تکنسین
 *   php artisan crm:reset-wallet-from-wp --apply
 */
class ResetWalletFromWp extends Command
{
    protected $signature = 'crm:reset-wallet-from-wp
                            {--tech= : فقط یک تکنسین (id Panel)}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'Reset کیف‌پول تکنسین‌ها به مانده فعلی WP — backup خودکار قبل از تغییر';

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

        $query = Technician::query()->whereNotNull('wp_id');
        if ($id = $this->option('tech')) {
            $query->where('id', (int) $id);
        }
        $techs = $query->get(['id', 'wp_id', 'mobile', 'firstname_tech', 'first_name', 'last_name', 'percent', 'tech_per_of_all', 'type_of_calc_tech', 'wallet_balance']);

        if ($techs->isEmpty()) {
            $this->warn('تکنسینی پیدا نشد.');
            return self::SUCCESS;
        }

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — تکنسین‌ها: {$techs->count()}");
        $this->newLine();

        // Backup file
        $backupFile = null;
        $backup = ['timestamp' => now()->toIso8601String(), 'techs' => []];

        $rows = [];
        $bar = $this->output->createProgressBar($techs->count());
        $bar->start();

        foreach ($techs as $tech) {
            $wpBalance = $this->computeWpBalance($wp, $prefix, $tech);
            $currentPanel = (int) $tech->wallet_balance - $this->panelInvoiceDebt($tech->id);

            $diff = $wpBalance - $currentPanel;

            $name = mb_substr($tech->firstname_tech ?: trim($tech->first_name . ' ' . $tech->last_name), 0, 20);

            $rows[] = [
                $tech->id,
                $tech->wp_id,
                $name,
                number_format($currentPanel),
                number_format($wpBalance),
                ($diff >= 0 ? '+' : '') . number_format($diff),
            ];

            if ($apply) {
                $backup['techs'][$tech->id] = [
                    'tech_id' => $tech->id,
                    'wp_id' => $tech->wp_id,
                    'name' => $name,
                    'wallet_txs' => DB::table('crm_tech_wallet_transactions')
                        ->where('technician_id', $tech->id)
                        ->get()->toArray(),
                    'invoices' => DB::table('crm_invoices')
                        ->where('technician_id', $tech->id)
                        ->get()->toArray(),
                    'old_panel_balance' => $currentPanel,
                    'new_balance_from_wp' => $wpBalance,
                ];

                // ۱) حذف wallet_txs
                DB::table('crm_tech_wallet_transactions')
                    ->where('technician_id', $tech->id)
                    ->delete();

                // ۲) superseded کردن invoices
                DB::table('crm_invoices')
                    ->where('technician_id', $tech->id)
                    ->whereNull('superseded_at')
                    ->update(['superseded_at' => now()]);

                // ۳) درج موجودی افتتاحیه — type بستگی به علامت دارد
                $type = $wpBalance >= 0 ? WalletTxType::WalletCharge->value : WalletTxType::Adjustment->value;

                DB::table('crm_tech_wallet_transactions')->insert([
                    'technician_id' => $tech->id,
                    'order_id' => null,
                    'invoice_id' => null,
                    'wp_id' => null,
                    'type' => $type,
                    'amount' => $wpBalance,
                    'balance_after' => $wpBalance,
                    'note' => 'موجودی افتتاحیه از WP CRM (sync ' . now()->format('Y-m-d H:i') . ')',
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ۴) wallet_balance روی تکنسین
                DB::table('crm_technicians')
                    ->where('id', $tech->id)
                    ->update(['wallet_balance' => $wpBalance, 'updated_at' => now()]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['id', 'wp_id', 'نام', 'مانده Panel فعلی', 'مانده WP', 'diff'], $rows);

        if ($apply) {
            // ذخیره backup
            Storage::disk('local')->makeDirectory('crm');
            $path = 'crm/wallet-reset-' . now()->format('Y-m-d_His') . '.json';
            Storage::disk('local')->put($path, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info("✓ Reset انجام شد. {$techs->count()} تکنسین به مانده WP بازگشتند.");
            $this->info("✓ Backup در: storage/app/{$path}");
        } else {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال --apply اضافه کنید:');
            $this->line('php artisan crm:reset-wallet-from-wp --apply');
            $this->newLine();
            $this->warn('⚠ اعمال این عملیات destructive است:');
            $this->line('  - همه wallet_txs تکنسین حذف می‌شوند');
            $this->line('  - همه invoices به superseded می‌روند');
            $this->line('  - یک opening balance tx درج می‌شود = مانده WP');
            $this->line('  - backup قبل از حذف در storage/app/crm/ ذخیره می‌شود');
        }

        return self::SUCCESS;
    }

    /**
     * محاسبه مانده تکنسین در WP طبق فرمول دینامیک:
     *   balance = sum(wallet_pay paid wallet=1)
     *           + sum(total_invoice paid reward=1)
     *           − sum(total_invoice paid reward=0)
     *           − sum(total_invoice_invoice × percent / 100)
     */
    private function computeWpBalance($wp, string $prefix, Technician $tech): int
    {
        // پیدا کردن همه financial postهای تکنسین در WP
        $postIds = $wp->table($prefix.'posts as p')
            ->where('p.post_type', 'financial')
            ->whereIn('p.post_status', ['publish', 'private'])
            ->where(function ($q) use ($prefix, $tech) {
                $q->whereIn('p.ID', function ($sub) use ($prefix, $tech) {
                    $sub->select('post_id')->from($prefix.'postmeta')
                        ->whereIn('meta_key', ['technician_id', 'technician', 'tech_id'])
                        ->where('meta_value', (string) $tech->wp_id);
                })->orWhere('p.post_author', $tech->wp_id);
            })
            ->pluck('p.ID')->all();

        if (empty($postIds)) return 0;

        // metaهای این پست‌ها
        $metas = $wp->table($prefix.'postmeta')
            ->whereIn('post_id', $postIds)
            ->get();

        $metaByPost = [];
        foreach ($metas as $m) {
            $metaByPost[(int) $m->post_id][$m->meta_key] = $m->meta_value;
        }

        // درصد فعلی WP تکنسین
        $userMeta = $wp->table($prefix.'usermeta')
            ->where('user_id', $tech->wp_id)
            ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
            ->pluck('meta_value', 'meta_key')
            ->toArray();
        $isInternal = ($userMeta['type_of_calc_tech'] ?? '') === '1';
        $percent = $isInternal
            ? (int) ($userMeta['tech_per_of_all'] ?? 0)
            : (int) ($userMeta['percent'] ?? 0);

        $balance = 0;
        foreach ($postIds as $pid) {
            $meta = $metaByPost[$pid] ?? [];
            $paymentStatus = (int) ($meta['payment_status'] ?? 0);
            $wallet = (int) ($meta['wallet'] ?? 0);
            $rewardType = $meta['reward_type'] ?? null;
            $orderWpId = (int) ($meta['order_id'] ?? 0);

            // فقط رخدادهای پرداخت‌شده محاسبه می‌شوند
            if ($paymentStatus !== 1 && $wallet !== 1 && ($rewardType === null || $rewardType === '')) {
                continue;
            }

            // ۱) شارژ wallet → balance += wallet_pay
            if ($wallet === 1) {
                $balance += (int) ($meta['wallet_pay'] ?? 0);
                continue;
            }

            // ۲) reward/penalty
            if ($rewardType !== null && $rewardType !== '') {
                $amt = (int) ($meta['total_invoice'] ?? 0);
                if ((int) $rewardType === 1) {
                    $balance += abs($amt);
                } else {
                    $balance -= abs($amt);
                }
                continue;
            }

            // ۳) فاکتور (order_id > 0): company_share = total_invoice × percent / 100
            //    balance -= company_share (تکنسین به شرکت بدهکار است این مقدار)
            if ($orderWpId > 0) {
                $ti = (int) ($meta['total_invoice'] ?? 0);
                if ($ti === 0) {
                    $pc = (int) ($meta['price_customer'] ?? 0);
                    $cp = (int) ($meta['cost_price'] ?? 0);
                    $ti = max(0, $pc - $cp);
                }
                $companyShare = intdiv($ti * max(0, min(100, $percent)), 100);
                $balance -= $companyShare;
                continue;
            }
        }

        return $balance;
    }

    private function panelInvoiceDebt(int $techId): int
    {
        return (int) DB::table('crm_invoices')
            ->where('technician_id', $techId)
            ->whereNull('superseded_at')
            ->sum('company_share');
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
