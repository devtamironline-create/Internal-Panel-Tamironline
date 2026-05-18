<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\WpPushService;

/**
 * Push دستی تراکنش‌های کیف‌پول به WP — برای پر کردن صفحهٔ کیف‌پول WP CRM
 * که اکثر تکنسین‌ها در آن نمایش داده نمی‌شوند چون tx آنها push نشده.
 *
 * تنظیمات wallet_sync_direction به‌صورت پیش‌فرض wp_to_laravel است که
 * push را برای ۱۳۵ تکنسین می‌بندد. این command با --force آن را
 * نادیده می‌گیرد و به‌صورت یک‌بار push اجباری می‌زند.
 *
 * نمونه‌ها:
 *   php artisan crm:resync-wallet-transactions --dry-run
 *   php artisan crm:resync-wallet-transactions             # فقط wp_id IS NULL
 *   php artisan crm:resync-wallet-transactions --all       # همه (شامل wp_id-دار)
 *   php artisan crm:resync-wallet-transactions --force     # نادیده گرفتن direction
 *   php artisan crm:resync-wallet-transactions --type=wallet_charge
 *   php artisan crm:resync-wallet-transactions --limit=100
 */
class ResyncWalletTransactions extends Command
{
    protected $signature = 'crm:resync-wallet-transactions
                            {--all : همه شامل آنها که wp_id دارند}
                            {--force : نادیده گرفتن wallet_sync_direction تکنسین (push اجباری)}
                            {--type= : فقط نوع خاص (wallet_charge / reward / penalty / commission / adjustment)}
                            {--limit= : حداکثر تعداد رکورد}
                            {--dry-run : فقط شمارش بدون push}';

    protected $description = 'Push مجدد تراکنش‌های کیف‌پول به WP CRM';

    public function handle(WpPushService $push): int
    {
        app()->instance('crm.wp_push.force', true);

        if ($this->option('force')) {
            // فلگ برای WpPushService::pushWalletTransaction تا چک direction
            // را skip کند. این فقط برای این command اعمال می‌شود.
            app()->instance('crm.wp_push.skip_direction_check', true);
        }

        if (! $push->isEnabled()) {
            $this->error('سینک Laravel→WP غیرفعال است.');
            return self::FAILURE;
        }

        $query = WalletTransaction::query();

        if (! $this->option('all')) {
            $query->whereNull('wp_id');
        }
        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = (clone $query)->count();
        $this->info("تعداد تراکنش برای resync: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run فعال — بدون push خروج.');
            return self::SUCCESS;
        }

        if (! $this->confirm("ادامه می‌دهی؟ این {$total} تراکنش را به WP ارسال می‌کند.", false)) {
            $this->info('لغو شد.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok = 0; $fail = 0; $skipped = 0;
        $query->orderBy('id')->chunk(100, function ($txs) use ($push, $bar, &$ok, &$fail, &$skipped) {
            foreach ($txs as $tx) {
                try {
                    $before = $tx->wp_id;
                    $push->pushWalletTransaction($tx);
                    $tx->refresh();
                    if ($tx->wp_id || $before) {
                        $ok++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $fail++;
                    \Illuminate\Support\Facades\Log::error('crm.resync_wallet.failed', [
                        'tx_id' => $tx->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("موفق: {$ok}");
        if ($skipped > 0) $this->warn("رد شده (بدون wp_id جدید): {$skipped}");
        if ($fail > 0) $this->error("ناموفق: {$fail}");

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
