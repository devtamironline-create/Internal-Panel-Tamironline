<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\WalletArchiveTx;

/**
 * سرویس فقط-خوان برای نمایش تاریخچهٔ تراکنش‌های کیف‌پولی که قبل از
 * reset مالی وجود داشتند. دو منبع داده پشتیبانی می‌شود:
 *
 *   ۱) فایل‌های JSONL در storage/app/crm/wallet-reset-*.jsonl
 *      (ساخته‌شده توسط ResetWalletFromWp وقتی --apply اجرا شده باشد)
 *
 *   ۲) جدول crm_wallet_archive_txs که توسط کامند
 *      crm:wallet:import-archive-from-wp از WP CRM پر می‌شود
 *      (post_type=financial)
 *
 * ⚠️ این داده‌ها هرگز نباید در محاسبه‌ی هیچ مقدار مالی استفاده شوند:
 *   - wallet_balance ✗
 *   - true_balance ✗
 *   - invoice_debt ✗
 *   - جمع‌های گزارش مالی ✗
 *   - SyncFinancial inbound ✗
 *
 * فقط برای نمایش read-only در UI کیف‌پول هر تکنسین به‌عنوان «تاریخچهٔ
 * قبل از reset». این سرویس تنها read انجام می‌دهد — هیچ DB write یا
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
     * یک جدول واحد. شامل دو منبع: JSONL + crm_wallet_archive_txs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFlatTransactions(int $technicianId): array
    {
        $rows = [];

        // منبع ۱: فایل‌های JSONL (wallet-reset-*.jsonl)
        foreach ($this->getForTechnician($technicianId) as $arch) {
            foreach ($arch['wallet_txs'] as $tx) {
                $rows[] = $tx + [
                    '_source' => 'JSONL: ' . $arch['source_file'],
                    '_reset_at' => $arch['reset_at'],
                ];
            }
        }

        // منبع ۲: جدول crm_wallet_archive_txs (ایمپورت‌شده از WP)
        $dbRows = WalletArchiveTx::query()
            ->where('technician_id', $technicianId)
            ->orderBy('wp_created_at')
            ->orderBy('id')
            ->get();

        foreach ($dbRows as $r) {
            $rows[] = [
                'id' => $r->wp_post_id, // برای ترتیب
                'type' => $r->type,
                'amount' => (int) $r->amount,
                'balance_after' => null, // در منبع WP running balance نداریم
                'note' => $r->note,
                'created_at' => $r->wp_created_at?->toDateTimeString(),
                'wp_id' => $r->wp_post_id,
                '_source' => 'WP archive (post_id=' . $r->wp_post_id . ')',
                '_reset_at' => $r->imported_at?->toDateTimeString(),
                '_refid' => $r->refid,
                '_payment_status' => $r->payment_status,
            ];
        }

        // مرتب‌سازی بر اساس created_at (قدیمی‌ترین → جدیدترین).
        // اگر created_at نبود، fallback به id.
        usort($rows, function ($a, $b) {
            $ta = $a['created_at'] ?? null;
            $tb = $b['created_at'] ?? null;
            if ($ta && $tb) {
                return strcmp((string) $ta, (string) $tb);
            }
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $rows;
    }

    /** آیا هیچ آرشیوی (JSONL یا DB) برای این تکنسین وجود دارد؟ */
    public function hasArchive(int $technicianId): bool
    {
        if (! empty($this->getForTechnician($technicianId))) {
            return true;
        }
        return WalletArchiveTx::where('technician_id', $technicianId)->exists();
    }
}
