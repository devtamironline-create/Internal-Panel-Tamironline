<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * پاک‌سازیِ فایل‌های گزارشِ فعالیتِ قدیمی‌تر از بازهٔ نگهداری (پیش‌فرض ۱۵ روز).
 *
 * چرخشِ Monolog هم فایل‌های قدیمی را حذف می‌کند، اما فقط «هنگامِ نوشتن».
 * این دستور تضمین می‌کند حتی در روزهای بدونِ فعالیت هم سقفِ نگهداری رعایت شود.
 */
class PurgeActivityLogs extends Command
{
    protected $signature = 'activity-log:purge
                            {--days= : بازهٔ نگهداری (پیش‌فرض از config)}
                            {--dry-run : فقط نمایش فایل‌هایی که حذف می‌شوند}';

    protected $description = 'حذف فایل‌های گزارش فعالیتِ قدیمی‌تر از بازهٔ نگهداری';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('activity-log.retention_days', 15));
        $days = max(1, $days);
        $dryRun = (bool) $this->option('dry-run');

        $cutoff = CarbonImmutable::today()->subDays($days)->format('Y-m-d');
        $directory = storage_path('logs/activity');

        if (! is_dir($directory)) {
            $this->info('پوشهٔ گزارش فعالیت هنوز ساخته نشده — کاری برای انجام نیست.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $freed = 0;

        foreach (glob($directory.'/activity-*.log') ?: [] as $file) {
            if (! preg_match('/activity-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m)) {
                continue;
            }
            if ($m[1] >= $cutoff) {
                continue;
            }

            $size = (int) @filesize($file);
            if ($dryRun) {
                $this->line('حذف می‌شود: '.basename($file).' ('.number_format($size / 1024, 1).' KB)');
                $deleted++;
                $freed += $size;

                continue;
            }

            if (@unlink($file)) {
                $deleted++;
                $freed += $size;
            }
        }

        $this->info(sprintf(
            '%s %d فایل قدیمی‌تر از %s (%s مگابایت).',
            $dryRun ? 'قابلِ حذف:' : 'حذف شد:',
            $deleted,
            $cutoff,
            number_format($freed / 1048576, 2)
        ));

        return self::SUCCESS;
    }
}
