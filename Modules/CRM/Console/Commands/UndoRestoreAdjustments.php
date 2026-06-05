<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Undo برای crm:restore-deleted-adjustments — حذف همان ۱۳۹ تراکنشی
 * که قبلاً restore شدند.
 *
 * منطق تشخیص:
 *   - type = adjustment
 *   - note شامل "تنظیم دستی مانده توسط ادمین"
 *   - note شامل "موجودی افتتاحیه" نباشد (opening balanceها)
 *
 * نمونه:
 *   php artisan crm:undo-restore-adjustments              # dry-run
 *   php artisan crm:undo-restore-adjustments --apply --recompute
 */
class UndoRestoreAdjustments extends Command
{
    protected $signature = 'crm:undo-restore-adjustments
                            {--apply : حذف واقعی (پیش‌فرض dry-run)}
                            {--recompute : بعد از حذف، wallet:recompute-balances اجرا شود}';

    protected $description = 'حذف تراکنش‌های restore شده — برگشت به حالت قبل از crm:restore-deleted-adjustments';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $query = DB::table('crm_tech_wallet_transactions')
            ->where('type', 'adjustment')
            ->where('note', 'LIKE', '%تنظیم دستی مانده توسط ادمین%')
            ->where('note', 'NOT LIKE', '%موجودی افتتاحیه%');

        $count = (clone $query)->count();
        $totalAmount = (int) (clone $query)->sum('amount');

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — تراکنش‌های پیدا شده: {$count}");
        $this->line('جمع کل amount: ' . number_format($totalAmount));
        $this->newLine();

        if ($count === 0) {
            $this->warn('هیچ تراکنش restore شده‌ای پیدا نشد.');
            return self::SUCCESS;
        }

        // نمایش ۲۰ نمونه
        $samples = (clone $query)->limit(20)
            ->select(['id', 'technician_id', 'amount', 'note', 'created_at'])
            ->get();

        $rows = [];
        foreach ($samples as $s) {
            $rows[] = [
                $s->id,
                $s->technician_id,
                number_format((int) $s->amount),
                $s->created_at,
                mb_substr((string) $s->note, 0, 50),
            ];
        }
        $this->table(['id', 'tech_id', 'amount', 'created_at', 'note'], $rows);

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run است — هیچ حذفی انجام نشد. برای اعمال:');
            $this->line('php artisan crm:undo-restore-adjustments --apply --recompute');
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("✓ {$deleted} تراکنش restore شده حذف شد.");

        if ($this->option('recompute')) {
            $this->info('در حال recompute balances...');
            Artisan::call('crm:wallet:recompute-balances');
            $this->info('✓ موجودی‌ها بازخوانی شدند.');
        } else {
            $this->warn('گام بعدی: php artisan crm:wallet:recompute-balances');
        }

        return self::SUCCESS;
    }
}
