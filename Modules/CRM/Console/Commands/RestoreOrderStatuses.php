<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Restore status و technician_id سفارش‌ها از snapshot JSON (هر خط یک رکورد).
 * استفاده: php artisan crm:restore-order-statuses --file=crm/snapshots/orders-...json
 */
class RestoreOrderStatuses extends Command
{
    protected $signature = 'crm:restore-order-statuses
                            {--file= : نام فایل (نسبی به storage/app)}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'Restore status/technician_id سفارش‌ها از snapshot';

    public function handle(): int
    {
        $file = $this->option('file');
        if (! $file) {
            $files = Storage::disk('local')->files('crm/snapshots');
            $orderFiles = array_filter($files, fn($f) => str_contains(basename($f), 'orders-'));
            if (empty($orderFiles)) {
                $this->warn('snapshot سفارش پیدا نشد.');
                return self::SUCCESS;
            }
            $this->info('Snapshotهای موجود:');
            foreach ($orderFiles as $f) {
                $this->line('  ' . $f);
            }
            return self::SUCCESS;
        }

        if (! Storage::disk('local')->exists($file)) {
            $this->error("فایل پیدا نشد: storage/app/{$file}");
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $path = storage_path('app/' . $file);
        $fp = fopen($path, 'r');

        $header = json_decode(fgets($fp), true);
        $this->info('snapshot_at: ' . ($header['snapshot_at'] ?? '—') . ' — count: ' . ($header['count'] ?? 0));

        $changed = 0;
        $same = 0;
        $missing = 0;

        while (($line = fgets($fp)) !== false) {
            $row = json_decode($line, true);
            if (! $row || ! isset($row['id'])) continue;

            $current = DB::table('crm_orders')->where('id', $row['id'])->first(['status', 'technician_id']);
            if (! $current) {
                $missing++;
                continue;
            }

            $needsRestore = (string) $current->status !== (string) $row['status']
                || (int) $current->technician_id !== (int) $row['technician_id'];

            if (! $needsRestore) {
                $same++;
                continue;
            }

            if ($apply) {
                DB::table('crm_orders')->where('id', $row['id'])->update([
                    'status' => $row['status'],
                    'technician_id' => $row['technician_id'],
                    'updated_at' => now(),
                ]);
            }
            $changed++;
        }
        fclose($fp);

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . ' — تغییر می‌کند: ' . $changed);
        $this->line('بدون تغییر: ' . $same);
        if ($missing > 0) $this->warn('سفارش گم‌شده: ' . $missing);

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال --apply اضافه کن.');
        } else {
            $this->info('✓ restore انجام شد.');
        }

        return self::SUCCESS;
    }
}
