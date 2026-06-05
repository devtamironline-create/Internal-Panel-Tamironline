<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;

/**
 * Backup خواندنی از وضعیت فعلی crm_tech_wallet_transactions + crm_invoices
 * بدون هیچ تغییر در DB.
 *
 * هدف: قبل از هر عملیات مالی پرریسک (recompute / find-duplicates --apply /
 * backfill / ...) یک snapshot دستی بگیریم تا اگر چیزی خراب شد، بشود
 * با مقایسهٔ فایل JSONL به وضعیت قبلی برگشت.
 *
 * فرمت فایل JSONL — هم‌سو با ResetWalletFromWp ولی با prefix متفاوت:
 *   storage/app/crm/wallet-backup-{YYYY-MM-DD_HHMMSS}.jsonl
 *
 * چرا prefix متفاوت؟ فایل‌های wallet-reset-*.jsonl تراکنش‌های پاک‌شده
 * هستند که در تب «تاریخچهٔ قدیمی» نمایش داده می‌شوند. backupهای دستی
 * snapshot داده‌ی فعالند و نباید با آن قاطی شوند — لذا prefix جدا.
 *
 * نمونه:
 *   php artisan crm:wallet:backup                    # backup همه تکنسین‌ها
 *   php artisan crm:wallet:backup --tech=42          # فقط یک تکنسین
 *   php artisan crm:wallet:backup --skip-invoices    # فقط wallet_txs
 */
class BackupWalletData extends Command
{
    protected $signature = 'crm:wallet:backup
                            {--tech= : فقط برای یک تکنسین (id)}
                            {--skip-invoices : از فاکتورها backup نگیر (فقط wallet_txs)}';

    protected $description = 'Snapshot read-only از تراکنش‌های کیف‌پول و فاکتورها در فایل JSONL — هیچ تغییری در DB نمی‌دهد';

    public function handle(): int
    {
        $techId = $this->option('tech') ? (int) $this->option('tech') : null;
        $skipInvoices = (bool) $this->option('skip-invoices');

        $dir = storage_path('app/crm');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $path = $dir . '/wallet-backup-' . $timestamp . '.jsonl';

        $fp = @fopen($path, 'w');
        if (! $fp) {
            $this->error("نمی‌توان فایل را ساخت: {$path}");
            return self::FAILURE;
        }

        // ─── کوئری تکنسین‌ها ───────────────────────────────────────
        $techQuery = Technician::query()
            ->orderBy('id')
            ->select(['id', 'wp_id', 'first_name', 'last_name', 'firstname_tech', 'wallet_balance']);
        if ($techId) {
            $techQuery->where('id', $techId);
        }
        $techs = $techQuery->get();

        if ($techs->isEmpty()) {
            fclose($fp);
            @unlink($path);
            $this->warn('تکنسینی پیدا نشد.');
            return self::SUCCESS;
        }

        $this->info("Backup در: {$path}");
        $this->line("تعداد تکنسین‌ها: " . $techs->count() . ($skipInvoices ? ' (بدون فاکتورها)' : ''));

        // ─── خط _meta ────────────────────────────────────────────
        fwrite($fp, json_encode([
            '_meta' => [
                'timestamp' => now()->toIso8601String(),
                'type' => 'manual_backup',
                'tech_count' => $techs->count(),
                'skip_invoices' => $skipInvoices,
                'note' => 'snapshot read-only — هیچ DB write انجام نشده',
            ],
        ], JSON_UNESCAPED_UNICODE) . "\n");

        $bar = $this->output->createProgressBar($techs->count());
        $bar->start();

        $totalTxs = 0;
        $totalInvoices = 0;

        foreach ($techs as $tech) {
            $name = trim($tech->firstname_tech ?: ($tech->first_name . ' ' . ($tech->last_name ?? '')));

            $walletTxs = DB::table('crm_tech_wallet_transactions')
                ->where('technician_id', $tech->id)
                ->orderBy('id')
                ->get()
                ->toArray();

            $invoices = $skipInvoices
                ? []
                : DB::table('crm_invoices')
                    ->where('technician_id', $tech->id)
                    ->orderBy('id')
                    ->get()
                    ->toArray();

            $line = [
                'tech_id' => $tech->id,
                'wp_id' => $tech->wp_id,
                'name' => $name,
                'wallet_balance_snapshot' => (int) $tech->wallet_balance,
                'wallet_txs' => $walletTxs,
                'invoices' => $invoices,
            ];

            fwrite($fp, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n");

            $totalTxs += count($walletTxs);
            $totalInvoices += count($invoices);
            unset($line, $walletTxs, $invoices); // free memory

            $bar->advance();
        }

        $bar->finish();
        fclose($fp);

        $size = filesize($path);
        $sizeReadable = $size > 1048576
            ? round($size / 1048576, 2) . ' MB'
            : round($size / 1024, 2) . ' KB';

        $this->newLine(2);
        $this->info("✓ Backup ساخته شد:");
        $this->line("  مسیر:           {$path}");
        $this->line("  حجم:            {$sizeReadable}");
        $this->line("  تکنسین:         " . number_format($techs->count()));
        $this->line("  تراکنش‌ها:       " . number_format($totalTxs));
        if (! $skipInvoices) {
            $this->line("  فاکتورها:        " . number_format($totalInvoices));
        }

        $this->newLine();
        $this->comment('⚠ این backup در محاسبات وارد نمی‌شود — فقط snapshot برای rollback.');
        $this->comment('برای دانلود از سرور:  scp user@host:' . $path . ' .');

        return self::SUCCESS;
    }
}
