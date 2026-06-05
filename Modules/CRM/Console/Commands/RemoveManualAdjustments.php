<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\WalletTransaction;

/**
 * حذف تراکنش‌های «تعدیل دستی» (Adjustment) که ادمین در گذشته برای
 * بالانس کردن کیف‌پول تکنسین‌ها ثبت کرده. وقتی داده اصلی sync اصلاح
 * می‌شود، این تعدیل‌ها روی هم سوار می‌شوند و balance غلط می‌شود.
 *
 * نمونه:
 *   php artisan crm:remove-manual-adjustments               # گزارش
 *   php artisan crm:remove-manual-adjustments --tech=42     # یک تکنسین
 *   php artisan crm:remove-manual-adjustments --apply       # حذف واقعی
 *   php artisan crm:remove-manual-adjustments --apply --recompute
 */
class RemoveManualAdjustments extends Command
{
    protected $signature = 'crm:remove-manual-adjustments
                            {--tech= : فقط یک تکنسین (id Panel)}
                            {--apply : حذف واقعی (پیش‌فرض dry-run)}
                            {--recompute : بعد از حذف، crm:wallet:recompute-balances اجرا شود}';

    protected $description = 'حذف تراکنش‌های تعدیل دستی (Adjustment) که ادمین برای fix دستی ثبت کرده';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // پیدا کردن همه adjustmentها
        $query = WalletTransaction::where('type', WalletTxType::Adjustment->value);
        if ($techId = $this->option('tech')) {
            $query->where('technician_id', (int) $techId);
        }

        $txs = $query->orderBy('technician_id')->orderBy('id')
            ->get(['id', 'technician_id', 'amount', 'note', 'created_at', 'wp_id']);

        if ($txs->isEmpty()) {
            $this->info('هیچ تراکنش تعدیل دستی پیدا نشد.');
            return self::SUCCESS;
        }

        // group by tech
        $byTech = $txs->groupBy('technician_id');
        $totalAmount = (int) $txs->sum('amount');

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — تراکنش‌های تعدیل: {$txs->count()} روی {$byTech->count()} تکنسین");
        $this->line("جمع کل amount: " . number_format($totalAmount));
        $this->newLine();

        $rows = [];
        foreach ($txs as $tx) {
            $rows[] = [
                $tx->id,
                $tx->technician_id,
                number_format((int) $tx->amount),
                $tx->created_at?->format('Y-m-d H:i'),
                $tx->wp_id ?? '—',
                mb_substr((string) ($tx->note ?? ''), 0, 50),
            ];
        }

        $this->table(['id', 'tech_id', 'amount', 'created_at', 'wp_id', 'note'], $rows);

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای حذف واقعی --apply اضافه کنید:');
            $this->line('php artisan crm:remove-manual-adjustments --apply --recompute');
            return self::SUCCESS;
        }

        // حذف
        $deleted = DB::table('crm_tech_wallet_transactions')
            ->whereIn('id', $txs->pluck('id')->all())
            ->delete();

        $this->newLine();
        $this->info("✓ {$deleted} تراکنش تعدیل حذف شد.");

        if ($this->option('recompute')) {
            $this->info('در حال recompute balances...');
            if ($this->option('tech')) {
                Artisan::call('crm:wallet:recompute-balances', ['--technician' => (int) $this->option('tech')]);
            } else {
                Artisan::call('crm:wallet:recompute-balances');
            }
            $this->info('✓ موجودی‌ها بازخوانی شدند.');
        } else {
            $this->warn('گام بعدی: php artisan crm:wallet:recompute-balances');
        }

        return self::SUCCESS;
    }
}
