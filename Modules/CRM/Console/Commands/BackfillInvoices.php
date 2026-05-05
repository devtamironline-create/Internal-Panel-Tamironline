<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\InvoiceService;
use Throwable;

/**
 * تولید فاکتور برای سفارش‌های Completed که فاکتور ندارند.
 *
 * علت: نسخه‌های قبلی پنل PWA تکنسین موقع پایان سفارش InvoiceService
 * را صدا نمی‌زدند، پس سفارش‌های تکمیل‌شده بدون ردیف crm_invoices و
 * بدون تراکنش commission باقی ماندند. این کامند idempotent است؛
 * سفارش‌هایی که قبلاً فاکتور دارند، رد می‌شوند.
 */
class BackfillInvoices extends Command
{
    protected $signature = 'crm:invoices:backfill
                             {--technician= : محدود به یک تکنسین (id)}
                             {--dry-run : فقط شمارش، بدون ساخت فاکتور}';

    protected $description = 'تولید فاکتور و کمیسیون برای سفارش‌های Completed که هنوز فاکتور ندارند.';

    public function handle(InvoiceService $invoiceService): int
    {
        $techId = $this->option('technician');
        $dryRun = (bool) $this->option('dry-run');

        // Invoice global scope «active» را برای این چک نباید اعمال کنیم —
        // سفارش‌هایی که فاکتور superseded دارند هم نباید دوباره فاکتور
        // بگیرند. پس مستقیم روی ستون order_id (unique) چک می‌کنیم.
        $query = Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('crm_invoices')
                    ->whereColumn('crm_invoices.order_id', 'crm_orders.id');
            })
            ->orderBy('id');

        if ($techId) {
            $query->where('technician_id', (int) $techId);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('هیچ سفارش Completed بدون فاکتور یافت نشد.');

            return self::SUCCESS;
        }

        $this->info("تعداد سفارش‌های واجد شرایط: {$total}" . ($dryRun ? '  (dry-run — هیچ تغییری اعمال نمی‌شود)' : ''));

        if ($dryRun) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $created = 0;
        $skipped = 0;
        $errors = 0;

        $query->chunkById(100, function ($orders) use ($invoiceService, $bar, &$created, &$skipped, &$errors) {
            foreach ($orders as $order) {
                try {
                    $existing = Invoice::where('order_id', $order->id)->first();
                    $invoice = $invoiceService->generateForOrder($order);
                    if ($existing) {
                        $skipped++;
                    } elseif ($invoice) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                } catch (Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("سفارش #{$order->id} ({$order->order_code}): " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("ساخته‌شده: {$created}");
        $this->line("از قبل موجود/رد شده: {$skipped}");
        if ($errors > 0) {
            $this->error("خطا: {$errors}");
        }

        $this->newLine();
        $this->comment('برای بازنویسی balance_after تراکنش‌های کیف‌پول، crm:wallet:recompute-balances را اجرا کنید.');

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
