<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;

/**
 * تشخیص و فیکس سفارش‌های Completed با وضعیت مالی ناقص.
 *
 * سه حالت پوشش داده می‌شود (مکمل crm:invoices:backfill که فقط حالت ۱
 * را فیکس می‌کند):
 *
 *   ۱) سفارش Completed بدون هیچ فاکتور
 *      → crm:invoices:backfill این را فیکس می‌کند (InvoiceService::generateForOrder)
 *
 *   ۲) سفارش Completed با فاکتور active ولی in_wallet=false و
 *      technician_id IS NOT NULL و company_share > 0
 *      → wallet tx کمیسیون ساخته نشده — این کامند آن را می‌سازد
 *
 *   ۳) فاکتور active، technician_id ست، company_share > 0، in_wallet=true،
 *      ولی هیچ WalletTransaction با invoice_id برابر در DB نیست
 *      (داده‌ی نامتقارن — فلگ true ولی tx فیزیکی نیست)
 *      → wallet tx را می‌سازد و recompute پیشنهاد می‌دهد
 *
 * نمونه‌ها:
 *   php artisan crm:invoices:diagnose-financial               # dry-run کامل
 *   php artisan crm:invoices:diagnose-financial --tech=42
 *   php artisan crm:invoices:diagnose-financial --apply
 *   php artisan crm:invoices:diagnose-financial --apply --recompute
 */
class DiagnoseOrderFinancialHealth extends Command
{
    protected $signature = 'crm:invoices:diagnose-financial
                            {--tech= : فقط برای یک تکنسین (id)}
                            {--since= : فقط سفارش‌های Completed بعد از این تاریخ (YYYY-MM-DD یا 7d, 1w, 1m)}
                            {--apply : فیکس واقعی موارد ۲ و ۳ (پیش‌فرض dry-run)}
                            {--recompute : بعد از فیکس، crm:wallet:recompute-balances هم بزن}';

    protected $description = 'تشخیص (و فیکس) سفارش‌های Completed با مالی ناقص — فاکتور نداشتن یا wallet tx نداشتن';

    public function handle(): int
    {
        $techId = $this->option('tech') ? (int) $this->option('tech') : null;
        $apply = (bool) $this->option('apply');
        $since = $this->parseSince($this->option('since'));

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . ' — تحلیل مالی سفارش‌های Completed');
        if ($techId) {
            $this->line("محدود به تکنسین: {$techId}");
        }
        if ($since) {
            $this->line("فیلتر زمانی: از {$since->toDateTimeString()}");
        }
        $this->newLine();

        // ─── حالت ۱: سفارش Completed بدون هیچ فاکتور ──────────────
        $this->line('═══ حالت ۱: سفارش Completed بدون فاکتور ═══');
        $case1Q = Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->where('is_legacy_closed', false) // retro-closed عمداً بدون فاکتورند
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')->from('crm_invoices')
                    ->whereColumn('crm_invoices.order_id', 'crm_orders.id');
            });
        if ($techId) $case1Q->where('technician_id', $techId);
        if ($since) {
            $case1Q->where(function ($q) use ($since) {
                $q->where('completed_at', '>=', $since)
                  ->orWhere(function ($qq) use ($since) {
                      $qq->whereNull('completed_at')->where('created_at', '>=', $since);
                  });
            });
        }

        $case1Count = (clone $case1Q)->count();
        $this->line("  تعداد: {$case1Count}");
        if ($case1Count > 0) {
            $this->warn("  → برای فیکس این حالت:  php artisan crm:invoices:backfill" . ($techId ? " --technician={$techId}" : ''));
        }
        $this->newLine();

        // ─── حالت ۲: فاکتور هست ولی in_wallet=false و باید tx ساخته شود ───
        $this->line('═══ حالت ۲: فاکتور active دارد ولی wallet tx ندارد (in_wallet=false) ═══');
        $case2Q = Invoice::query()
            ->where('in_wallet', false)
            ->whereNotNull('technician_id')
            ->where('company_share', '>', 0);
        if ($techId) $case2Q->where('technician_id', $techId);
        if ($since) $case2Q->where('issued_at', '>=', $since);

        $case2 = $case2Q->orderBy('id')->get(['id', 'invoice_code', 'order_id', 'technician_id', 'company_share', 'tech_share', 'total_amount', 'issued_at']);

        $this->line("  تعداد: " . $case2->count());
        if ($case2->isNotEmpty()) {
            $sampleRows = $case2->take(15)->map(fn ($i) => [
                $i->id, $i->invoice_code, $i->technician_id, number_format((int) $i->company_share), $i->issued_at?->format('Y-m-d'),
            ])->all();
            $this->table(['inv id', 'code', 'tech_id', 'company_share', 'issued'], $sampleRows);
            if ($case2->count() > 15) {
                $this->line('  ... و ' . ($case2->count() - 15) . ' مورد دیگر.');
            }
        }
        $this->newLine();

        // ─── حالت ۳: in_wallet=true ولی WalletTransaction واقعی موجود نیست ───
        $this->line('═══ حالت ۳: فاکتور in_wallet=true ولی WalletTransaction واقعی موجود نیست ═══');
        $case3Q = Invoice::query()
            ->where('in_wallet', true)
            ->whereNotNull('technician_id')
            ->where('company_share', '>', 0)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')->from('crm_tech_wallet_transactions')
                    ->whereColumn('crm_tech_wallet_transactions.invoice_id', 'crm_invoices.id');
            });
        if ($techId) $case3Q->where('technician_id', $techId);
        if ($since) $case3Q->where('issued_at', '>=', $since);

        $case3 = $case3Q->orderBy('id')->get(['id', 'invoice_code', 'order_id', 'technician_id', 'company_share', 'issued_at']);
        $this->line("  تعداد: " . $case3->count());
        if ($case3->isNotEmpty()) {
            $sampleRows = $case3->take(15)->map(fn ($i) => [
                $i->id, $i->invoice_code, $i->technician_id, number_format((int) $i->company_share), $i->issued_at?->format('Y-m-d'),
            ])->all();
            $this->table(['inv id', 'code', 'tech_id', 'company_share', 'issued'], $sampleRows);
            if ($case3->count() > 15) {
                $this->line('  ... و ' . ($case3->count() - 15) . ' مورد دیگر.');
            }
        }
        $this->newLine();

        // ─── جمع‌بندی ───────────────────────────────────────────
        $totalNeedFix = $case2->count() + $case3->count();
        $this->info('─────────────────────────────');
        $this->info("  حالت ۱ (بدون فاکتور): {$case1Count}  → backfill جدا");
        $this->info("  حالت ۲ (in_wallet=false): " . $case2->count());
        $this->info("  حالت ۳ (in_wallet=true ولی tx گمشده): " . $case3->count());

        if (! $apply) {
            $this->newLine();
            if ($totalNeedFix === 0 && $case1Count === 0) {
                $this->info('✓ همه چیز سالم است.');
            } else {
                $this->warn('این dry-run بود.');
                if ($case1Count > 0) {
                    $this->line("  ۱) php artisan crm:invoices:backfill" . ($techId ? " --technician={$techId}" : ''));
                }
                if ($totalNeedFix > 0) {
                    $this->line("  ۲) php artisan crm:invoices:diagnose-financial --apply --recompute" . ($techId ? " --tech={$techId}" : ''));
                }
            }
            return self::SUCCESS;
        }

        // ─── APPLY ───────────────────────────────────────────────
        $created = 0;
        $errors = 0;
        $bar = $this->output->createProgressBar($totalNeedFix);
        $bar->start();

        // حالت ۲ و ۳ هر دو همان فیکس را دارند: ساخت wallet tx برای فاکتور
        foreach ($case2->concat($case3) as $invoice) {
            try {
                DB::transaction(function () use ($invoice) {
                    // قفل تکنسین برای race-safety
                    $tech = Technician::whereKey($invoice->technician_id)->lockForUpdate()->first();
                    if (! $tech) {
                        return;
                    }

                    $last = (int) (WalletTransaction::where('technician_id', $tech->id)
                        ->orderByDesc('id')->value('balance_after') ?? 0);
                    $amount = -1 * (int) $invoice->company_share;

                    WalletTransaction::create([
                        'technician_id' => $tech->id,
                        'order_id' => $invoice->order_id,
                        'invoice_id' => $invoice->id,
                        'wp_id' => null,
                        'type' => WalletTxType::Commission->value,
                        'amount' => $amount,
                        'balance_after' => $last + $amount,
                        'note' => 'سهم شرکت از فاکتور ' . $invoice->invoice_code . ' (backfill)',
                        'created_by' => null,
                    ]);

                    $tech->wallet_balance = $last + $amount;
                    $tech->save();

                    if (! $invoice->in_wallet) {
                        $invoice->update(['in_wallet' => true]);
                    }
                });
                $created++;
            } catch (\Throwable $e) {
                $errors++;
                \Illuminate\Support\Facades\Log::warning('diagnose_financial.fix_failed', [
                    'invoice_id' => $invoice->id, 'error' => $e->getMessage(),
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ wallet tx ساخته شد: {$created}");
        if ($errors > 0) {
            $this->warn("✗ خطا: {$errors} (در laravel.log)");
        }

        if ($this->option('recompute')) {
            $this->info('در حال recompute balances...');
            Artisan::call('crm:wallet:recompute-balances', [], $this->getOutput());
        } else {
            $this->newLine();
            $this->warn('گام بعد: php artisan crm:wallet:recompute-balances');
        }

        return self::SUCCESS;
    }

    /**
     * تبدیل --since به Carbon. پشتیبانی از:
     *   YYYY-MM-DD یا YYYY-MM-DD HH:MM:SS
     *   نسبی: 7d (7 روز پیش)، 1w (1 هفته)، 1m (1 ماه)
     */
    private function parseSince(?string $value): ?\Carbon\Carbon
    {
        if (! $value) return null;
        $value = trim($value);
        if ($value === '') return null;

        if (preg_match('/^(\d+)([dwmy])$/i', $value, $m)) {
            $n = (int) $m[1];
            return match (strtolower($m[2])) {
                'd' => now()->subDays($n)->startOfDay(),
                'w' => now()->subWeeks($n)->startOfDay(),
                'm' => now()->subMonths($n)->startOfDay(),
                'y' => now()->subYears($n)->startOfDay(),
            };
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable $e) {
            $this->warn("--since فرمت نامعتبر: {$value} — نادیده گرفته شد.");
            return null;
        }
    }
}
