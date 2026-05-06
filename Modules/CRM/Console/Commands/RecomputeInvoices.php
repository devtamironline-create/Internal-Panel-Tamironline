<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\WalletTransaction;
use Modules\CRM\Services\CommissionCalculator;

/**
 * بازمحاسبه فاکتورهای موجود با فرمول صحیح + پاک‌سازی Commission txهای
 * تاریخی که نباید وجود داشته باشند.
 *
 * مدل صحیح (هم‌تراز با WP):
 *   - tech_share / company_share از CommissionCalculator (بازنویسی شده)
 *   - هیچ Commission تراکنشی در کیف‌پول ثبت نمی‌شود؛ مانده فقط از طریق
 *     شارژ/پاداش/جریمه/پرداخت تغییر می‌کند و invoice_debt سهم شرکت را
 *     کسر می‌کند.
 *
 * این کامند:
 *   1. هر فاکتور را load می‌کند، با technician محاسبه می‌کند، تغییرات
 *      tech_share/company_share/commission_percent/calc_type را write
 *      می‌کند (فقط اگر مقدار جدید با مقدار فعلی فرق داشته باشد).
 *   2. تراکنش‌های wallet از نوع Commission مرتبط با هر فاکتور را حذف
 *      می‌کند.
 *
 * idempotent: اجرای دوباره کاری ندارد و خروجی صفر می‌دهد.
 *
 * بعد از این کامند، crm:wallet:recompute-balances را اجرا کنید تا
 * balance_after هر تراکنش بازنویسی شود.
 */
class RecomputeInvoices extends Command
{
    protected $signature = 'crm:invoices:recompute
                             {--technician= : محدود به تکنسین خاص (id)}
                             {--dry-run : نمایش تغییرات بدون اعمال}';

    protected $description = 'بازمحاسبه tech_share/company_share فاکتورها با فرمول صحیح + حذف Commission txهای تاریخی.';

    public function handle(CommissionCalculator $calc): int
    {
        $techId = $this->option('technician');
        $dryRun = (bool) $this->option('dry-run');

        $query = Invoice::query()->with(['order', 'technician']);
        if ($techId) {
            $query->where('technician_id', (int) $techId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('هیچ فاکتوری یافت نشد.');

            return self::SUCCESS;
        }

        $this->info("تعداد فاکتورها: {$total}" . ($dryRun ? '  (dry-run)' : ''));

        $bar = $this->output->createProgressBar($total);
        $changedInvoices = 0;
        $deletedCommissions = 0;

        $query->chunkById(200, function ($invoices) use ($calc, $dryRun, $bar, &$changedInvoices, &$deletedCommissions) {
            foreach ($invoices as $invoice) {
                if (! $invoice->technician || ! $invoice->order) {
                    $bar->advance();
                    continue;
                }

                $totals = $calc->calculate($invoice->order, $invoice->technician, (int) $invoice->total_amount);

                $hasChange =
                    (int) $invoice->tech_share !== (int) $totals['tech_share']
                    || (int) $invoice->company_share !== (int) $totals['company_share']
                    || (int) $invoice->commission_percent !== (int) $totals['percent']
                    || (string) $invoice->calc_type !== (string) ($totals['calc_type'] ?? '');

                $commissionCount = WalletTransaction::where('invoice_id', $invoice->id)
                    ->where('type', WalletTxType::Commission->value)
                    ->count();

                if (! $dryRun) {
                    DB::transaction(function () use ($invoice, $totals, $hasChange, $commissionCount, &$changedInvoices, &$deletedCommissions) {
                        if ($hasChange) {
                            $invoice->update([
                                'tech_share' => (int) $totals['tech_share'],
                                'company_share' => (int) $totals['company_share'],
                                'commission_percent' => (int) $totals['percent'],
                                'calc_type' => $totals['calc_type'] ?? null,
                            ]);
                            $changedInvoices++;
                        }

                        if ($commissionCount > 0) {
                            WalletTransaction::where('invoice_id', $invoice->id)
                                ->where('type', WalletTxType::Commission->value)
                                ->delete();
                            $deletedCommissions += $commissionCount;
                        }
                    });
                } else {
                    if ($hasChange) {
                        $changedInvoices++;
                    }
                    $deletedCommissions += $commissionCount;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("فاکتورهای تغییر کرده: {$changedInvoices}");
        $this->info("تراکنش‌های Commission حذف‌شده: {$deletedCommissions}");

        if ($dryRun) {
            $this->comment('(dry-run — هیچ تغییری اعمال نشد)');
        } elseif ($deletedCommissions > 0 || $changedInvoices > 0) {
            $this->newLine();
            $this->comment('گام بعد: php artisan crm:wallet:recompute-balances');
        }

        return self::SUCCESS;
    }
}
