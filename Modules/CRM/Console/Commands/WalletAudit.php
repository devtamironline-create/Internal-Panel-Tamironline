<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;

/**
 * بازرسی فقط-خواندنی کیف‌پول یک تکنسین. هیچ‌چیز را تغییر نمی‌دهد.
 *
 * فرمول مانده نهایی:
 *   true_balance = wallet_balance - invoice_debt
 *   wallet_balance = running sum امضادار همه تراکنش‌های wallet
 *   invoice_debt   = SUM(company_share) فاکتورهای active
 *
 * استفاده:
 *   php artisan crm:wallet-audit 42                  # by id
 *   php artisan crm:wallet-audit --mobile=09120000000
 *   php artisan crm:wallet-audit 42 --show-all       # لیست همه تراکنش‌ها
 */
class WalletAudit extends Command
{
    protected $signature = 'crm:wallet-audit
                            {tech? : id تکنسین (Laravel)}
                            {--mobile= : موبایل تکنسین (به‌جای id)}
                            {--show-all : چاپ همه تراکنش‌ها (پیش‌فرض: فقط ۲۰ تای بزرگ + ۲۰ تای آخر)}';

    protected $description = 'بازرسی فقط-خواندنی کیف‌پول تکنسین — تشخیص ناهنجاری بدون تغییر';

    public function handle(): int
    {
        // ── یافتن تکنسین ───────────────────────────────────────────
        $tech = $this->resolveTechnician();
        if (! $tech) {
            $this->error('تکنسین پیدا نشد.');
            return self::FAILURE;
        }

        $this->printTechnicianHeader($tech);

        // ── خواندن همه تراکنش‌ها + فاکتورها ────────────────────────
        $txs = WalletTransaction::where('technician_id', $tech->id)
            ->orderBy('id')
            ->get();

        $invoices = Invoice::withoutGlobalScope('active')
            ->where('technician_id', $tech->id)
            ->orderBy('id')
            ->get();

        $activeInvoices = $invoices->whereNull('superseded_at');

        // ── محاسبه running sum واقعی ───────────────────────────────
        $running = 0;
        $maxStoredBalanceAfterMismatch = null;
        foreach ($txs as $tx) {
            $running += (int) $tx->amount;
            if ((int) $tx->balance_after !== $running && $maxStoredBalanceAfterMismatch === null) {
                $maxStoredBalanceAfterMismatch = [
                    'id' => $tx->id,
                    'stored' => (int) $tx->balance_after,
                    'expected' => $running,
                ];
            }
        }
        $computedWalletBalance = $running;
        $cachedWalletBalance = (int) $tech->wallet_balance;

        $invoiceDebt = (int) $activeInvoices->sum('company_share');
        $supersededInvoiceDebt = (int) $invoices->whereNotNull('superseded_at')->sum('company_share');

        $trueBalance = $computedWalletBalance - $invoiceDebt;

        // ── خلاصه ──────────────────────────────────────────────────
        $this->newLine();
        $this->info('━━━ خلاصه ─────────────────────────────────────────');
        $this->table(
            ['شاخص', 'مقدار', 'توضیح'],
            [
                ['تعداد تراکنش‌ها', $txs->count(), 'wallet transactions'],
                ['running sum محاسبه‌شده', $this->fmt($computedWalletBalance), 'جمع امضادار همه amounts'],
                ['wallet_balance کش‌شده', $this->fmt($cachedWalletBalance), 'فیلد technicians.wallet_balance'],
                ['تفاوت کش/واقعی', $this->fmt($cachedWalletBalance - $computedWalletBalance), $cachedWalletBalance === $computedWalletBalance ? 'OK' : '⚠ Mismatch — recompute نیاز است'],
                ['تعداد فاکتورهای active', $activeInvoices->count(), 'بدون superseded_at'],
                ['تعداد فاکتورهای superseded', $invoices->whereNotNull('superseded_at')->count(), 'آرشیو — در محاسبه نیست'],
                ['invoice_debt (sum company_share)', $this->fmt($invoiceDebt), 'فقط active'],
                ['superseded debt (نمایشی)', $this->fmt($supersededInvoiceDebt), 'صرفاً برای مقایسه'],
                ['true_balance نهایی', $this->fmt($trueBalance), $trueBalance >= 0 ? 'شرکت بدهکار به تکنسین' : 'تکنسین بدهکار به شرکت'],
            ]
        );

        // ── تجمیع تراکنش‌ها بر اساس نوع ───────────────────────────
        $this->info('━━━ تجمیع تراکنش‌ها بر اساس نوع ─────────────────');
        $byType = $txs->groupBy(fn($t) => $t->type instanceof WalletTxType ? $t->type->value : (string) $t->type);
        $rows = [];
        foreach ($byType as $typeKey => $group) {
            $enum = WalletTxType::tryFrom($typeKey);
            $sum = (int) $group->sum('amount');
            $count = $group->count();
            $expectedSign = $enum?->sign() ?? 0;
            $actualSign = $sum > 0 ? +1 : ($sum < 0 ? -1 : 0);
            $signOk = ($expectedSign === 0 || $expectedSign === $actualSign || $sum === 0) ? '✓' : '⚠ علامت غلط';
            $rows[] = [$enum?->label() ?? $typeKey, $count, $this->fmt($sum), $signOk];
        }
        $this->table(['نوع', 'تعداد', 'جمع', 'بررسی علامت'], $rows);

        // ── ناهنجاری‌ها ────────────────────────────────────────────
        $this->info('━━━ بررسی ناهنجاری‌ها ─────────────────────────────');
        $issues = [];

        if ($cachedWalletBalance !== $computedWalletBalance) {
            $issues[] = sprintf(
                '⚠ Mismatch بین wallet_balance کش‌شده (%s) و running sum واقعی (%s). راه‌حل: php artisan crm:wallet:recompute-balances --technician=%d',
                $this->fmt($cachedWalletBalance), $this->fmt($computedWalletBalance), $tech->id
            );
        }

        if ($maxStoredBalanceAfterMismatch !== null) {
            $issues[] = sprintf(
                '⚠ balance_after در تراکنش id=%d اشتباه است (ذخیره‌شده=%s، انتظار=%s). recompute لازم است.',
                $maxStoredBalanceAfterMismatch['id'],
                $this->fmt($maxStoredBalanceAfterMismatch['stored']),
                $this->fmt($maxStoredBalanceAfterMismatch['expected'])
            );
        }

        // تراکنش با علامت غلط نسبت به نوع
        foreach ($txs as $tx) {
            $enum = $tx->type instanceof WalletTxType ? $tx->type : WalletTxType::tryFrom((string) $tx->type);
            if (! $enum || $enum === WalletTxType::Adjustment) continue;
            $expected = $enum->sign();
            $amount = (int) $tx->amount;
            if ($amount === 0) continue;
            $actual = $amount > 0 ? +1 : -1;
            if ($expected !== $actual) {
                $issues[] = sprintf(
                    '⚠ تراکنش id=%d (%s) امضای غلط دارد: amount=%s — انتظار: %s',
                    $tx->id, $enum->label(), $this->fmt($amount), $expected > 0 ? '+' : '−'
                );
            }
        }

        // wp_id تکراری
        $dupWp = $txs->whereNotNull('wp_id')->groupBy('wp_id')->filter(fn($g) => $g->count() > 1);
        foreach ($dupWp as $wpId => $group) {
            $ids = $group->pluck('id')->implode(', ');
            $issues[] = sprintf('⚠ wp_id=%s در تراکنش‌های id=%s تکرار شده (احتمال double-import)', $wpId, $ids);
        }

        // فاکتور active با company_share=0
        $zeroShare = $activeInvoices->where('company_share', 0);
        if ($zeroShare->count() > 0) {
            $issues[] = sprintf(
                '⚠ %d فاکتور active با company_share=0 — ids: %s',
                $zeroShare->count(),
                $zeroShare->pluck('id')->implode(', ')
            );
        }

        // فاکتورها با invoice_code تکراری (شاید duplicate import)
        $dupInvoiceCode = $activeInvoices->whereNotNull('invoice_code')
            ->groupBy('invoice_code')
            ->filter(fn($g) => $g->count() > 1);
        foreach ($dupInvoiceCode as $code => $group) {
            $issues[] = sprintf('⚠ invoice_code=%s در فاکتورهای id=%s تکرار شده', $code, $group->pluck('id')->implode(', '));
        }

        // فاکتورها با wp_id تکراری
        $dupInvoiceWp = $invoices->whereNotNull('wp_id')->groupBy('wp_id')->filter(fn($g) => $g->count() > 1);
        foreach ($dupInvoiceWp as $wpId => $group) {
            $issues[] = sprintf('⚠ فاکتور با wp_id=%s در ids=%s تکرار شده', $wpId, $group->pluck('id')->implode(', '));
        }

        if (empty($issues)) {
            $this->line('  ✓ ناهنجاری ساختاری پیدا نشد.');
        } else {
            foreach ($issues as $issue) {
                $this->line('  ' . $issue);
            }
        }

        // ── لیست تراکنش‌ها ─────────────────────────────────────────
        $this->newLine();
        $this->info('━━━ تراکنش‌ها ─────────────────────────────────────');
        if ($this->option('show-all')) {
            $this->printTxTable($txs);
        } else {
            $this->line('بزرگ‌ترین ۲۰ تراکنش (بر اساس |amount|):');
            $topByAbs = $txs->sortByDesc(fn($t) => abs((int) $t->amount))->take(20);
            $this->printTxTable($topByAbs);
            $this->newLine();
            $this->line('۲۰ تراکنش آخر (بر اساس id):');
            $this->printTxTable($txs->sortByDesc('id')->take(20));
        }

        // ── لیست فاکتورها ─────────────────────────────────────────
        $this->newLine();
        $this->info('━━━ فاکتورها (active) ────────────────────────────');
        $this->printInvoiceTable($activeInvoices);

        if ($invoices->whereNotNull('superseded_at')->count() > 0) {
            $this->newLine();
            $this->line('فاکتورهای superseded (آرشیو — در محاسبه نیست):');
            $this->printInvoiceTable($invoices->whereNotNull('superseded_at'));
        }

        return self::SUCCESS;
    }

    private function resolveTechnician(): ?Technician
    {
        if ($mobile = $this->option('mobile')) {
            return Technician::where('mobile', $mobile)->first();
        }
        $id = $this->argument('tech');
        if (! $id) return null;
        return Technician::find((int) $id);
    }

    private function printTechnicianHeader(Technician $t): void
    {
        $this->info('━━━ تکنسین ───────────────────────────────────────');
        $this->table(['فیلد', 'مقدار'], [
            ['id', $t->id],
            ['wp_id', $t->wp_id ?? '—'],
            ['نام', $t->full_name],
            ['mobile', $t->mobile],
            ['status', $t->status],
            ['percent (External)', $t->percent ?? '—'],
            ['type_of_calc_tech', $t->type_of_calc_tech === null ? '—' : ($t->type_of_calc_tech === '1' ? '1 (Internal)' : $t->type_of_calc_tech)],
            ['tech_per_of_all (Internal)', $t->tech_per_of_all ?? '—'],
            ['wallet_sync_direction', $t->wallet_sync_direction ?? '—'],
            ['order_sync_direction', $t->order_sync_direction ?? '—'],
        ]);
    }

    private function printTxTable($txs): void
    {
        $rows = [];
        foreach ($txs as $tx) {
            $enum = $tx->type instanceof WalletTxType ? $tx->type : WalletTxType::tryFrom((string) $tx->type);
            $rows[] = [
                $tx->id,
                $tx->wp_id ?? '—',
                $enum?->label() ?? (string) $tx->type,
                $this->fmt((int) $tx->amount),
                $this->fmt((int) $tx->balance_after),
                $tx->order_id ?? '—',
                $tx->invoice_id ?? '—',
                $tx->created_at?->format('Y-m-d H:i') ?? '—',
                mb_substr((string) ($tx->note ?? ''), 0, 40),
            ];
        }
        $this->table(['id', 'wp_id', 'نوع', 'amount', 'balance_after', 'order', 'invoice', 'تاریخ', 'note'], $rows);
    }

    private function printInvoiceTable($invoices): void
    {
        $rows = [];
        foreach ($invoices as $inv) {
            $rows[] = [
                $inv->id,
                $inv->wp_id ?? '—',
                $inv->invoice_code ?? '—',
                $inv->order_id ?? '—',
                $this->fmt((int) $inv->total_amount),
                $this->fmt((int) $inv->tech_share),
                $this->fmt((int) $inv->company_share),
                $inv->calc_type ?? '—',
                $inv->commission_percent ?? '—',
                $inv->status ?? '—',
                $inv->issued_at?->format('Y-m-d') ?? '—',
            ];
        }
        if (empty($rows)) {
            $this->line('  (هیچ فاکتوری یافت نشد)');
            return;
        }
        $this->table(
            ['id', 'wp_id', 'code', 'order', 'total', 'tech_share', 'company_share', 'calc', '%', 'status', 'issued'],
            $rows
        );
    }

    private function fmt(int $n): string
    {
        return number_format($n);
    }
}
