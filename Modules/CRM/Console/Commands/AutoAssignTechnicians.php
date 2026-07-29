<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Services\AutoAssignService;
use Modules\CRM\Support\AssignmentSettings;

/**
 * پخشِ خودکارِ سفارش‌های بدون تکنسین.
 *
 * زمان‌بند این کامند را *هر دقیقه* صدا می‌زند و خودِ کامند تصمیم می‌گیرد
 * نوبتش رسیده یا نه. چرا این‌طور: فاصلهٔ اجرا از پنل قابل تنظیم است و
 * اگر در فایلِ زمان‌بندی بنویسیمش (everyFiveMinutes)، تغییرِ آن عدد در
 * پنل هیچ اثری ندارد — دقیقاً همان چیزی که دیده شد: مهلت روی ۳ دقیقه
 * تنظیم بود ولی تخصیص‌ها همچنان سرِ هر ۵ دقیقه می‌افتاد.
 *
 * اگر حالتِ تنظیمات روی «فقط پیشنهاد» باشد بلافاصله خارج می‌شود، پس
 * زمان‌بندیِ همیشگی بی‌خطر است.
 */
class AutoAssignTechnicians extends Command
{
    protected $signature = 'crm:orders:auto-assign
                            {--dry-run : فقط گزارش بده، چیزی را تخصیص نده}
                            {--force : فاصلهٔ تنظیم‌شده را نادیده بگیر و همین حالا اجرا کن}';

    protected $description = 'تخصیص خودکار تکنسین به سفارش‌های بدون تکنسین (گروه‌بندی‌شده بر اساس مشتری/آدرس/روز)';

    public function handle(AutoAssignService $service): int
    {
        $dry = (bool) $this->option('dry-run');

        if (! AssignmentSettings::isAuto() && ! $dry) {
            $this->line('حالتِ تخصیص روی «فقط پیشنهاد» است — کاری انجام نشد.');

            return self::SUCCESS;
        }

        // اجرای دستی و پیش‌نمایشِ خشک نباید تابعِ نوبت باشند.
        if (! $dry && ! $this->option('force') && ! AssignmentSettings::isDueToRun()) {
            $last = AssignmentSettings::lastRunAt();
            $this->line(sprintf(
                'نوبتِ اجرا نرسیده (فاصلهٔ تنظیم‌شده: %d دقیقه، آخرین اجرا: %s).',
                AssignmentSettings::runEveryMinutes(),
                $last?->format('H:i:s') ?? '—'
            ));

            return self::SUCCESS;
        }

        if (! $dry) {
            // پیش از اجرا مهر می‌زنیم، نه بعدش: اگر اجرا طول بکشد یا خطا
            // بدهد، نباید دقیقهٔ بعد دوباره شروع شود.
            AssignmentSettings::markRun();
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
