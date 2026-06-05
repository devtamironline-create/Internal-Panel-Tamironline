<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Supersede همه فاکتورهای active — تا در محاسبه invoice_debt شرکت
 * نکنند. این مفید است وقتی می‌خواهیم true_balance فقط برابر
 * wallet_balance باشد (بدون کسر سهم شرکت از فاکتورها).
 *
 * نمونه:
 *   php artisan crm:supersede-all-invoices              # dry-run
 *   php artisan crm:supersede-all-invoices --apply
 *   php artisan crm:supersede-all-invoices --tech=42 --apply
 */
class SupersedeAllInvoices extends Command
{
    protected $signature = 'crm:supersede-all-invoices
                            {--tech= : فقط یک تکنسین}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'علامت‌گذاری همه فاکتورهای active به superseded تا در محاسبه invoice_debt نیایند';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $query = DB::table('crm_invoices')->whereNull('superseded_at');
        if ($techId = $this->option('tech')) {
            $query->where('technician_id', (int) $techId);
        }

        $count = (clone $query)->count();
        $totalCompanyShare = (int) (clone $query)->sum('company_share');

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — فاکتورهای active: {$count}");
        $this->line('جمع company_share آنها: ' . number_format($totalCompanyShare));

        if ($count === 0) {
            $this->warn('فاکتور active ای نیست.');
            return self::SUCCESS;
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:supersede-all-invoices --apply');
            return self::SUCCESS;
        }

        $updated = $query->update(['superseded_at' => now(), 'updated_at' => now()]);
        $this->info("✓ {$updated} فاکتور به superseded تبدیل شد.");
        $this->newLine();
        $this->info('سهم شرکت همه تکنسین‌ها الان ۰ است → true_balance = wallet_balance');

        return self::SUCCESS;
    }
}
