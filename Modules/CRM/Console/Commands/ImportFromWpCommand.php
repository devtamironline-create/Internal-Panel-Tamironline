<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Console\Importers\AbstractImporter;
use Modules\CRM\Console\Importers\CustomerImporter;

/**
 * انتقال داده از CRM وردپرسی به جداول CRM لاراولی.
 *
 * Usage:
 *   php artisan crm:import --section=customers
 *   php artisan crm:import --section=customers --chunk=1000
 *   php artisan crm:import --list                            # فهرست بخش‌های موجود
 *
 * هر importer idempotent است؛ اجرای مجدد، رکوردهای موجود را به‌روز می‌کند
 * نه رکورد تکراری بسازد.
 *
 * پیش‌نیاز: دیتابیس WP روی همان MySQL قابل دسترس باشد و در .env:
 *   LEGACY_WP_DB_DATABASE=<wp-db-name>
 *   LEGACY_WP_DB_USERNAME=...
 *   LEGACY_WP_DB_PASSWORD=...
 *   LEGACY_WP_DB_PREFIX=wp_     # اگر پیشوند جدول‌ها متفاوت است
 */
class ImportFromWpCommand extends Command
{
    protected $signature = 'crm:import
        {--section= : نام بخش (مثل customers)}
        {--chunk=500 : تعداد رکورد در هر دسته}
        {--list : فهرست بخش‌های قابل ایمپورت}';

    protected $description = 'انتقال داده‌ها از CRM وردپرسی به CRM لاراولی';

    public function handle(): int
    {
        $importers = $this->importers();

        if ($this->option('list')) {
            $this->info('بخش‌های قابل ایمپورت:');
            foreach (array_keys($importers) as $name) {
                $this->line("  - {$name}");
            }
            return self::SUCCESS;
        }

        $section = $this->option('section');
        if (! $section || ! isset($importers[$section])) {
            $this->error('پارامتر --section نامعتبر است. برای دیدن فهرست از --list استفاده کنید.');
            return self::FAILURE;
        }

        // تست اتصال به دیتابیس قدیمی
        try {
            DB::connection('legacy_wp')->getPdo();
        } catch (\Throwable $e) {
            $this->error('خطا در اتصال به دیتابیس قدیمی WP:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('بررسی کنید متغیرهای LEGACY_WP_DB_* در .env تنظیم شده باشد.');
            return self::FAILURE;
        }

        $chunk = max(50, (int) $this->option('chunk'));
        $this->info("شروع ایمپورت بخش «{$section}» (chunk={$chunk}) ...");
        $this->newLine();

        $importer = $importers[$section];
        $start = microtime(true);
        $result = $importer->run($chunk);
        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->info('پایان ایمپورت:');
        $this->table(
            ['کل WP', 'ایجاد شد', 'به‌روزرسانی شد', 'رد شد', 'زمان (ثانیه)'],
            [[
                $result['total'] ?? 0,
                $result['created'] ?? 0,
                $result['updated'] ?? 0,
                $result['skipped'] ?? 0,
                $elapsed,
            ]],
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, AbstractImporter>
     */
    protected function importers(): array
    {
        $output = $this->output;

        return [
            'customers' => new CustomerImporter($output),
            // در فازهای بعدی: orders, technicians, brands, devices, ...
        ];
    }
}
