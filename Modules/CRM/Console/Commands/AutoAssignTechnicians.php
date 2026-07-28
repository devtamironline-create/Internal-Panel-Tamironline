<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Services\AutoAssignService;
use Modules\CRM\Support\AssignmentSettings;

/**
 * پخشِ خودکارِ سفارش‌های بدون تکنسین — هر ۵ دقیقه از زمان‌بند صدا زده
 * می‌شود. اگر حالتِ تنظیمات روی «فقط پیشنهاد» باشد بلافاصله خارج می‌شود،
 * پس زمان‌بندیِ همیشگی بی‌خطر است.
 */
class AutoAssignTechnicians extends Command
{
    protected $signature = 'crm:orders:auto-assign
                            {--dry-run : فقط گزارش بده، چیزی را تخصیص نده}';

    protected $description = 'تخصیص خودکار تکنسین به سفارش‌های بدون تکنسین (گروه‌بندی‌شده بر اساس مشتری/آدرس/روز)';

    public function handle(AutoAssignService $service): int
    {
        $dry = (bool) $this->option('dry-run');

        if (! AssignmentSettings::isAuto() && ! $dry) {
            $this->line('حالتِ تخصیص روی «فقط پیشنهاد» است — کاری انجام نشد.');

            return self::SUCCESS;
        }

        $stats = $service->run($dry);

        $this->info(sprintf(
            '%d گروه بررسی شد · %d سفارش تخصیص %s · %d به‌دلیل امتیاز پایین رها شد · %d بدون تکنسین ماند · %d بدون نوع خدمت رد شد',
            $stats['groups'],
            $stats['assigned'],
            $dry ? 'می‌شد' : 'داده شد',
            $stats['skipped_low_score'],
            $stats['unassignable'],
            $stats['skipped_no_type'],
        ));

        if ($stats['skipped_no_type'] > 0) {
            $this->warn('سفارش‌های بدون «نوع خدمت» خودکار پخش نمی‌شوند — نوعشان را در پنل تعیین کنید.');
        }

        return self::SUCCESS;
    }
}
