<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * برگرداندن فاکتورهای superseded به حالت active (superseded_at = NULL).
 *
 * این برای موقعی است که قبلاً همه فاکتورها بی‌دلیل superseded شده‌اند
 * (مثلاً crm:reset-wallet-from-wp اجرا شده) و حالا باید برگردانده شوند
 * تا در محاسبه invoice_debt شرکت کنند.
 *
 * با --recent-only فقط superseded شده‌های اخیر (پیش‌فرض ۲۴ ساعت گذشته)
 * را undo می‌کند.
 *
 * نمونه:
 *   php artisan crm:unsupersede-invoices                          # dry-run
 *   php artisan crm:unsupersede-invoices --recent-only=24         # ۲۴ ساعت اخیر
 *   php artisan crm:unsupersede-invoices --apply
 *   php artisan crm:unsupersede-invoices --apply --recent-only=48
 */
class UnsupersedeInvoices extends Command
{
    protected $signature = 'crm:unsupersede-invoices
                            {--recent-only= : فقط superseded شده‌های N ساعت گذشته}
                            {--tech= : فقط یک تکنسین (id Panel)}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'برگرداندن فاکتورهای superseded به active (superseded_at = NULL)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $query = DB::table('crm_invoices')->whereNotNull('superseded_at');

        if ($hours = $this->option('recent-only')) {
            $query->where('superseded_at', '>=', now()->subHours((int) $hours));
        }

        if ($techId = $this->option('tech')) {
            $query->where('technician_id', (int) $techId);
        }

        $count = (clone $query)->count();

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — فاکتورهای superseded: {$count}");

        if ($count === 0) {
            $this->warn('فاکتوری برای بازگردانی پیدا نشد.');
            return self::SUCCESS;
        }

        // جمع company_share این فاکتورها
        $totalCompanyShare = (int) (clone $query)->sum('company_share');
        $this->line('جمع company_share این فاکتورها: ' . number_format($totalCompanyShare));
        $this->newLine();

        // نمونه ۱۰ تا
        $samples = (clone $query)->limit(10)
            ->select(['id', 'wp_id', 'technician_id', 'total_amount', 'company_share', 'tech_share', 'superseded_at'])
            ->get();

        $rows = [];
        foreach ($samples as $s) {
            $rows[] = [
                $s->id,
                $s->wp_id ?? '—',
                $s->technician_id,
                number_format((int) $s->total_amount),
                number_format((int) $s->tech_share),
                number_format((int) $s->company_share),
                $s->superseded_at,
            ];
        }
        $this->table(['id', 'wp_id', 'tech_id', 'total', 'tech_share', 'company_share', 'superseded_at'], $rows);

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:unsupersede-invoices --apply');
            return self::SUCCESS;
        }

        $updated = $query->update(['superseded_at' => null, 'updated_at' => now()]);

        $this->info("✓ {$updated} فاکتور به حالت active برگشت.");
        $this->newLine();
        $this->warn('گام بعدی پیشنهادی:');
        $this->line('  ۱) php artisan crm:fix-invoices-from-wp   # بازمحاسبه company_share با درصد WP');
        $this->line('  ۲) php artisan crm:wallet:recompute-balances');

        return self::SUCCESS;
    }
}
