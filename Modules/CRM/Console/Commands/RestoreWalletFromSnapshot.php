<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Enums\WalletTxType;

/**
 * بازیابی wallet_balance از snapshot ذخیره‌شده در storage/app/crm/snapshots/.
 *
 * این snapshot قبلاً موقع قفل کردن داده تکنسین گرفته شده. شامل
 * wallet_balance کش‌شده هر تکنسین در آن لحظه است. اگر اعداد آن
 * زمان درست بودند، می‌توان به آن وضعیت برگشت.
 *
 * منطق reset:
 *   - همه wallet_txs تکنسین حذف می‌شوند
 *   - یک opening balance با مقدار snapshot درج می‌شود
 *   - wallet_balance روی تکنسین آپدیت می‌شود
 *
 * نمونه:
 *   php artisan crm:restore-wallet-from-snapshot                       # نمایش snapshotهای موجود
 *   php artisan crm:restore-wallet-from-snapshot --file=...            # dry-run
 *   php artisan crm:restore-wallet-from-snapshot --file=... --apply --recompute
 */
class RestoreWalletFromSnapshot extends Command
{
    protected $signature = 'crm:restore-wallet-from-snapshot
                            {--file= : نام فایل snapshot (نسبی به storage/app)}
                            {--apply : اعمال (پیش‌فرض dry-run)}
                            {--recompute : بعد، wallet:recompute-balances اجرا شود}';

    protected $description = 'بازیابی wallet_balance تکنسین‌ها از snapshot ذخیره‌شده';

    public function handle(): int
    {
        $disk = Storage::disk('local');

        if (! $this->option('file')) {
            // لیست snapshotهای موجود
            $files = $disk->files('crm/snapshots');
            if (empty($files)) {
                $this->warn('هیچ snapshot ای در storage/app/crm/snapshots/ پیدا نشد.');
                return self::SUCCESS;
            }
            $this->info('Snapshotهای موجود:');
            foreach ($files as $f) {
                $size = $disk->size($f);
                $this->line("  {$f}  ({$size} bytes)");
            }
            $this->newLine();
            $this->info('برای استفاده:');
            $this->line('  php artisan crm:restore-wallet-from-snapshot --file=' . end($files));
            return self::SUCCESS;
        }

        $file = $this->option('file');
        if (! $disk->exists($file)) {
            $this->error("فایل snapshot پیدا نشد: storage/app/{$file}");
            return self::FAILURE;
        }

        $json = $disk->get($file);
        $data = json_decode($json, true);
        if (! is_array($data) || ! isset($data['technicians'])) {
            $this->error('فرمت snapshot نامعتبر.');
            return self::FAILURE;
        }

        $techs = $data['technicians'];
        $snapshotAt = $data['snapshot_at'] ?? '—';

        $apply = (bool) $this->option('apply');
        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — snapshot از: {$snapshotAt}");
        $this->info('تعداد تکنسین در snapshot: ' . count($techs));
        $this->newLine();

        $rows = [];
        $changedCount = 0;
        $totalDiff = 0;

        foreach ($techs as $snapTech) {
            $techId = (int) ($snapTech['id'] ?? 0);
            if (! $techId) continue;

            $snapWallet = (int) ($snapTech['wallet_balance'] ?? 0);

            // current
            $current = DB::table('crm_technicians')
                ->where('id', $techId)
                ->first(['id', 'wallet_balance', 'firstname_tech', 'first_name', 'last_name']);
            if (! $current) continue;

            $currentWallet = (int) $current->wallet_balance;
            $invoiceDebt = (int) DB::table('crm_invoices')
                ->where('technician_id', $techId)
                ->whereNull('superseded_at')
                ->sum('company_share');
            $currentTrueBalance = $currentWallet - $invoiceDebt;

            $name = mb_substr($current->firstname_tech ?: trim($current->first_name . ' ' . $current->last_name), 0, 22);

            $rows[] = [
                $techId,
                $name,
                number_format($currentWallet),
                number_format($snapWallet),
                number_format($snapWallet - $currentWallet),
            ];

            if ($apply && $snapWallet !== $currentWallet) {
                // ۱) حذف wallet_txs تکنسین
                DB::table('crm_tech_wallet_transactions')
                    ->where('technician_id', $techId)
                    ->delete();

                // ۲) درج opening balance با مقدار snapshot
                $type = $snapWallet >= 0 ? WalletTxType::WalletCharge->value : WalletTxType::Adjustment->value;
                DB::table('crm_tech_wallet_transactions')->insert([
                    'technician_id' => $techId,
                    'order_id' => null,
                    'invoice_id' => null,
                    'wp_id' => null,
                    'type' => $type,
                    'amount' => $snapWallet,
                    'balance_after' => $snapWallet,
                    'note' => 'بازیابی از snapshot ' . basename($file),
                    'created_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // ۳) wallet_balance روی تکنسین
                DB::table('crm_technicians')
                    ->where('id', $techId)
                    ->update(['wallet_balance' => $snapWallet, 'updated_at' => now()]);

                $changedCount++;
                $totalDiff += ($snapWallet - $currentWallet);
            }
        }

        // نمایش ۳۰ تای اول
        $this->table(['tech_id', 'نام', 'wallet فعلی', 'wallet snapshot', 'diff'], array_slice($rows, 0, 30));
        if (count($rows) > 30) {
            $this->line('... و ' . (count($rows) - 30) . ' تکنسین دیگر');
        }

        $this->newLine();
        if ($apply) {
            $this->info("✓ {$changedCount} تکنسین بازیابی شد.");
            $this->line('جمع diff: ' . number_format($totalDiff));
            if ($this->option('recompute')) {
                $this->info('در حال recompute...');
                Artisan::call('crm:wallet:recompute-balances');
                $this->info('✓ تمام.');
            }
        } else {
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:restore-wallet-from-snapshot --file=' . $file . ' --apply --recompute');
        }

        return self::SUCCESS;
    }
}
