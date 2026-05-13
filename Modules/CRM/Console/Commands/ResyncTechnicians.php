<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\WpPushService;

/**
 * Push مجدد همه (یا بخشی از) تکنسین‌ها به WP. برای جا انداختن داده‌هایی
 * که قبلاً با نسخه‌ی قبلی پلاگین/سرویس ناقص رفته‌اند (مثلاً قبل از
 * ترجمه‌ی نام فیلدها به املای WP CRM).
 *
 * Idempotent است — هر اجرا فقط update_user_meta روی WP می‌زند که هیچ
 * ضرری ندارد. تراکنش‌ها و سفارش‌ها دست‌نخورده می‌مانند.
 *
 * نمونه‌ها:
 *   php artisan crm:resync-technicians                 # همه
 *   php artisan crm:resync-technicians --laravel-only  # فقط بدون wp_id
 *   php artisan crm:resync-technicians --id=5          # یک نفر
 *   php artisan crm:resync-technicians --limit=50      # batch
 *   php artisan crm:resync-technicians --dry-run       # فقط شمارش
 */
class ResyncTechnicians extends Command
{
    protected $signature = 'crm:resync-technicians
                            {--laravel-only : فقط تکنسین‌هایی که wp_id ندارند}
                            {--id= : فقط یک تکنسین خاص با id لاراولی}
                            {--limit= : حداکثر تعداد رکورد}
                            {--dry-run : فقط شمارش بدون push}';

    protected $description = 'Push مجدد تکنسین‌ها به WP CRM برای جا انداختن داده‌های ناقص';

    public function handle(WpPushService $push): int
    {
        // اطمینان از فعال بودن push (موقع اجرا از console flag را bind می‌کنیم
        // تا booted() listenerهای مدل هم بتوانند push بزنند)
        app()->instance('crm.wp_push.force', true);

        if (! $push->isEnabled()) {
            $this->error('سینک Laravel→WP در تنظیمات غیرفعال است (wp_push_enabled). ابتدا در /admin/crm/sync فعالش کن.');
            return self::FAILURE;
        }

        $query = Technician::query();

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }
        if ($this->option('laravel-only')) {
            $query->whereNull('wp_id');
        }
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = (clone $query)->count();
        $this->info("تعداد تکنسین برای resync: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run فعال است — بدون push خروج می‌گیریم.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok = 0; $fail = 0;
        $query->orderBy('id')->chunk(50, function ($techs) use ($push, $bar, &$ok, &$fail) {
            foreach ($techs as $tech) {
                try {
                    $push->pushTechnician($tech);
                    $ok++;
                } catch (\Throwable $e) {
                    $fail++;
                    \Illuminate\Support\Facades\Log::error('crm.resync_technicians.failed', [
                        'tech_id' => $tech->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("موفق: {$ok}");
        if ($fail > 0) {
            $this->error("ناموفق: {$fail} — جزئیات در /admin/crm/sync-logs و storage/logs/laravel.log");
        }

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
