<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;

/**
 * بازسازی کیف‌پول یک تکنسین از داده‌های WP CRM:
 *   ۱) همه financial postهای WP تکنسین را می‌خواند
 *   ۲) رخدادهای wallet_charge / reward / penalty که در Laravel نیستند
 *      را به crm_tech_wallet_transactions اضافه می‌کند
 *   ۳) balance_after و wallet_balance را با recompute بازنویسی می‌کند
 *
 * نمونه:
 *   php artisan crm:rebuild-tech-wallet 460
 *   php artisan crm:rebuild-tech-wallet 460 --dry-run
 */
class RebuildTechWallet extends Command
{
    protected $signature = 'crm:rebuild-tech-wallet {tech_id : id لاراولی تکنسین}
                            {--dry-run : فقط شمارش، بدون insert/update}';

    protected $description = 'پر کردن تراکنش‌های مالی WP در Laravel + بازسازی موجودی کیف‌پول یک تکنسین';

    public function handle(): int
    {
        $techId = (int) $this->argument('tech_id');
        $tech = Technician::find($techId);
        if (! $tech) {
            $this->error("تکنسین با id={$techId} پیدا نشد.");
            return self::FAILURE;
        }
        if (! $tech->wp_id) {
            $this->error("این تکنسین wp_id ندارد — نمی‌توانیم از WP بخوانیم.");
            return self::FAILURE;
        }

        $this->info("تکنسین: {$tech->firstname_tech} (id={$tech->id}, wp_id={$tech->wp_id})");

        // اتصال موقت به WP DB
        $this->configureWpConnection();
        $wp = DB::connection('wp_crm');
        $prefix = env('WP_DB_PREFIX', 'or_');

        // همه financial postهای تکنسین — با meta payment_status=1 یا wallet=1
        // یا reward_type set
        try {
            $rows = $wp->table($prefix.'posts as p')
                ->where('p.post_type', 'financial')
                ->whereIn('p.post_status', ['publish', 'private'])
                ->whereIn('p.ID', function ($q) use ($prefix, $tech) {
                    $q->select('post_id')
                      ->from($prefix.'postmeta')
                      ->whereIn('meta_key', ['technician_id', 'technician', 'tech_id'])
                      ->where('meta_value', (string) $tech->wp_id);
                })
                ->orWhere('p.post_author', $tech->wp_id)
                ->where('p.post_type', 'financial')
                ->whereIn('p.post_status', ['publish', 'private'])
                ->select('p.ID', 'p.post_title', 'p.post_date', 'p.post_author')
                ->orderBy('p.ID')
                ->get();
        } catch (\Throwable $e) {
            $this->error('خطا در اتصال به WP DB: ' . $e->getMessage());
            $this->warn('credential WP_DB_* را در .env تنظیم کن.');
            return self::FAILURE;
        }

        $this->info("روی WP پیدا شد: {$rows->count()} financial post");

        $inserted = 0;
        $skipped  = 0;
        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        foreach ($rows as $row) {
            $wpId = (int) $row->ID;

            // اگر در Laravel هست skip
            if (WalletTransaction::where('wp_id', $wpId)->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // متاهای این financial را بخوان
            $meta = $wp->table($prefix.'postmeta')
                ->where('post_id', $wpId)
                ->pluck('meta_value', 'meta_key')
                ->toArray();

            // فیلتر: فقط رخدادهایی که payment_status=1 یا wallet=1
            $paymentStatus = (int) ($meta['payment_status'] ?? 0);
            $wallet        = (int) ($meta['wallet'] ?? 0);
            $rewardType    = $meta['reward_type'] ?? null;

            if ($paymentStatus !== 1 && $wallet !== 1 && ($rewardType === null || $rewardType === '')) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // تشخیص نوع و مبلغ
            $type = null;
            $amount = 0;
            $note = (string) ($meta['rewardDesc'] ?? $meta['description'] ?? '');

            if ($wallet === 1) {
                $type = WalletTxType::WalletCharge;
                $amount = (int) ($meta['wallet_pay'] ?? 0); // مثبت — شارژ
            } elseif ($rewardType !== null && $rewardType !== '') {
                if ((int) $rewardType === 1) {
                    $type = WalletTxType::Reward;
                    $amount = (int) ($meta['total_invoice'] ?? 0);
                    if ($amount < 0) $amount = abs($amount); // پاداش مثبت است
                } else {
                    $type = WalletTxType::Penalty;
                    $amount = -1 * abs((int) ($meta['total_invoice'] ?? 0));
                }
            } else {
                // payment_status=1 ولی wallet/reward نیست → فاکتور است، در
                // crm_invoices ذخیره می‌شود نه wallet_transactions. skip.
                $skipped++;
                $bar->advance();
                continue;
            }

            if ($amount === 0) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // پیدا کردن order_id لاراولی در صورت وجود
            $orderId = null;
            if (! empty($meta['order_id'])) {
                $orderWpId = (int) $meta['order_id'];
                $orderId = Order::where('wp_id', $orderWpId)->value('id');
            }

            if ($this->option('dry-run')) {
                $inserted++;
                $bar->advance();
                continue;
            }

            // suppress push back به WP
            app()->instance('crm.suppress_outbound_push', true);

            try {
                WalletTransaction::create([
                    'wp_id'         => $wpId,
                    'technician_id' => $tech->id,
                    'order_id'      => $orderId,
                    'type'          => $type,
                    'amount'        => $amount,
                    'balance_after' => 0, // بعد توسط recompute درست می‌شود
                    'note'          => $note,
                    'created_by'    => null,
                    'created_at'    => $row->post_date,
                    'updated_at'    => now(),
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('crm.rebuild_tech_wallet.insert_failed', [
                    'wp_id' => $wpId,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("درج جدید: {$inserted}");
        $this->info("رد شده: {$skipped}");

        if ($this->option('dry-run')) {
            $this->warn('--dry-run فعال — تغییری روی DB اعمال نشد.');
            return self::SUCCESS;
        }

        // recompute balance
        $this->info('در حال بازخوانی balance_after و wallet_balance...');
        Artisan::call('crm:wallet:recompute-balances', ['--technician' => $tech->id]);
        $tech->refresh();

        $this->info("✓ بازسازی تمام شد.");
        $this->line("  wallet_balance نهایی: " . number_format($tech->wallet_balance) . " تومان");
        $this->line("  تعداد کل تراکنش‌های Laravel: " . WalletTransaction::where('technician_id', $tech->id)->count());

        return self::SUCCESS;
    }

    /**
     * اتصال موقت Laravel به DB وردپرس.
     */
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
