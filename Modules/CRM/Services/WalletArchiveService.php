<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Log;

/**
 * سرویس فقط-خوان برای نمایش تاریخچهٔ تراکنش‌های کیف‌پولی که در
 * crm:reset-wallet-from-wp --apply حذف شدند و در فایل‌های JSONL
 * بک‌آپ شدند (storage/app/crm/wallet-reset-*.jsonl).
 *
 * ⚠️ این داده‌ها هرگز نباید در محاسبه‌ی هیچ مقدار مالی استفاده شوند:
 *   - wallet_balance ✗
 *   - true_balance ✗
 *   - invoice_debt ✗
 *   - جمع‌های گزارش مالی ✗
 *   - SyncFinancial inbound ✗
 *
 * فقط برای نمایش read-only در UI کیف‌پول هر تکنسین به‌عنوان «تاریخچهٔ
 * قبل از reset». این سرویس تنها از فایل می‌خواند و هیچ DB write یا
 * تأثیری بر هیچ aggregate ندارد.
 */
class WalletArchiveService
{
    /** پوشه‌ی نگه‌داری فایل‌های backup. */
    protected const BACKUP_DIR = 'crm';
    protected const BACKUP_GLOB = 'wallet-reset-*.jsonl';

    /**
     * تراکنش‌های آرشیو یک تکنسین را از همه‌ی backupها برمی‌گرداند.
     *
     * @return array<int, array{
     *     source_file: string,
     *     reset_at: ?string,
     *     old_panel_balance: ?int,
     *     new_balance_from_wp: ?int,
     *     wallet_txs: array<int, array<string, mixed>>,
     *     invoices: array<int, array<string, mixed>>,
     * }>
     */
    public function getForTechnician(int $technicianId): array
    {
        $base = storage_path('app/' . self::BACKUP_DIR);
        if (! is_dir($base)) {
            return [];
        }

        $files = glob($base . '/' . self::BACKUP_GLOB) ?: [];
        if (empty($files)) {
            return [];
        }

        // قدیمی‌ترین اول تا اگر چند reset متوالی بوده، ترتیب حفظ شود
        sort($files);

        $result = [];

        foreach ($files as $file) {
            $resetAt = null;
            $fp = @fopen($file, 'r');
            if (! $fp) {
                continue;
            }

            try {
                while (($line = fgets($fp)) !== false) {
                    $line = trim($line);
                    if ($line === '') continue;

                    $rec = json_decode($line, true);
                    if (! is_array($rec)) continue;

                    // خط اول JSONL، _meta است
                    if (isset($rec['_meta'])) {
                        $resetAt = $rec['_meta']['timestamp'] ?? null;
                        continue;
                    }

                    if ((int) ($rec['tech_id'] ?? 0) !== $technicianId) {
                        continue;
                    }

                    $result[] = [
                        'source_file' => basename($file),
                        'reset_at' => $resetAt,
                        'old_panel_balance' => isset($rec['old_panel_balance']) ? (int) $rec['old_panel_balance'] : null,
                        'new_balance_from_wp' => isset($rec['new_balance_from_wp']) ? (int) $rec['new_balance_from_wp'] : null,
                        'wallet_txs' => is_array($rec['wallet_txs'] ?? null) ? $rec['wallet_txs'] : [],
                        'invoices' => is_array($rec['invoices'] ?? null) ? $rec['invoices'] : [],
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('wallet_archive.read_failed', [
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                fclose($fp);
            }
        }

        return $result;
    }

    /**
     * فقط تراکنش‌های wallet (flat) آرشیوی این تکنسین — برای نمایش در
     * یک جدول واحد. ترتیب بر اساس created_at قدیمی‌ترین → جدیدترین.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFlatTransactions(int $technicianId): array
    {
        $archives = $this->getForTechnician($technicianId);
        $rows = [];

        foreach ($archives as $arch) {
            foreach ($arch['wallet_txs'] as $tx) {
                $rows[] = $tx + [
                    '_source_file' => $arch['source_file'],
                    '_reset_at' => $arch['reset_at'],
                ];
            }
        }

        // مرتب‌سازی بر اساس id اصلی (که با ترتیب زمانی یکی است)
        usort($rows, fn ($a, $b) => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));

        return $rows;
    }

    /** آیا هیچ آرشیوی برای این تکنسین وجود دارد؟ */
    public function hasArchive(int $technicianId): bool
    {
        return ! empty($this->getForTechnician($technicianId));
    }
}
