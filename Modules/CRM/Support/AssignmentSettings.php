<?php

namespace Modules\CRM\Support;

use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Services\TechnicianSuggestionService;

/**
 * تنظیماتِ پخشِ سفارش بین تکنسین‌ها — روی جدولِ key-value موجود
 * (`crm_settings`) می‌نشیند تا جدول جدیدی لازم نباشد.
 *
 * پیش‌فرضِ عمدی `suggest` است: نصبِ این قابلیت نباید به‌خودیِ‌خود رفتار
 * فعلیِ پنل را عوض کند. مدیر باید آگاهانه حالت خودکار را روشن کند.
 */
final class AssignmentSettings
{
    public const MODE_SUGGEST = 'suggest';

    public const MODE_AUTO = 'auto';

    public const DEFAULTS = [
        'assign_mode' => self::MODE_SUGGEST,
        // چند دقیقه از ثبت سفارش بگذرد تا خودکار پخش شود. لازم است چون
        // گروه «همان روز/همان آدرس» است و اگر سفارشِ اول بلافاصله پخش
        // شود، سفارش دوم و سومِ همان مشتری دیگر نمی‌توانند به او بچسبند.
        'assign_grace_minutes' => 10,
        // زیر این امتیاز خودکار تخصیص نده؛ برای اپراتور بگذار.
        'assign_min_score' => 20,
        // سقفِ سفارشِ پردازش‌شده در هر اجرای زمان‌بندی.
        'assign_max_per_run' => 50,
        // حداکثر قدمتِ سفارش برای پخش خودکار (روز) — سفارش‌های خیلی قدیمی
        // معمولاً دلیلِ انسانی دارند و نباید ناگهان پخش شوند.
        'assign_max_age_days' => 3,
        // قاعدهٔ «تکنسینِ سابقهٔ همان دستگاه» فعال باشد؟
        'assign_history_enabled' => 1,
        // تا چند ماه عقب سابقه معتبر است.
        'assign_history_months' => 6,
        // سقفِ توازن: هر تکنسین در یک «دور» حداکثر چند کارِ فعال بگیرد.
        // وقتی همهٔ واجدین شرایط به این عدد رسیدند، دور بعدی شروع می‌شود.
        'assign_balance_cap' => 3,
        // هر چند دقیقه یک‌بار پخشِ خودکار اجرا شود. زمان‌بند هر دقیقه
        // کامند را صدا می‌زند و خودِ کامند تصمیم می‌گیرد نوبتش رسیده یا
        // نه — وگرنه این عدد در فایلِ زمان‌بندی ثابت می‌ماند و تغییرش از
        // پنل بی‌اثر است.
        'assign_run_every_minutes' => 5,
    ];

    /** کلیدِ آخرین اجرای واقعیِ پخش — مبنای فاصلهٔ اجراها. */
    public const LAST_RUN_KEY = 'assign_last_run_at';

    public static function mode(): string
    {
        $value = CrmSetting::get('assign_mode', self::DEFAULTS['assign_mode']);

        return $value === self::MODE_AUTO ? self::MODE_AUTO : self::MODE_SUGGEST;
    }

    public static function isAuto(): bool
    {
        return self::mode() === self::MODE_AUTO;
    }

    public static function graceMinutes(): int
    {
        return self::intValue('assign_grace_minutes', 0, 1440);
    }

    public static function minScore(): int
    {
        return self::intValue('assign_min_score', 0, 100);
    }

    public static function maxPerRun(): int
    {
        return max(1, self::intValue('assign_max_per_run', 1, 500));
    }

    public static function maxAgeDays(): int
    {
        return max(1, self::intValue('assign_max_age_days', 1, 90));
    }

    public static function historyEnabled(): bool
    {
        return CrmSetting::get('assign_history_enabled', (string) self::DEFAULTS['assign_history_enabled']) === '1';
    }

    public static function historyMonths(): int
    {
        return max(1, self::intValue('assign_history_months', 1, 60));
    }

    public static function balanceCap(): int
    {
        return max(1, self::intValue('assign_balance_cap', 1, 50));
    }

    public static function runEveryMinutes(): int
    {
        return max(1, self::intValue('assign_run_every_minutes', 1, 60));
    }

    /**
     * آیا از آخرین اجرا به‌اندازهٔ فاصلهٔ تنظیم‌شده گذشته است؟
     *
     * یک دقیقه ارفاق داده می‌شود: زمان‌بند دقیقاً سرِ ثانیهٔ صفر اجرا
     * نمی‌شود و بدونِ آن، فاصلهٔ ۵ دقیقه عملاً به ۶ دقیقه کش می‌آمد.
     */
    public static function isDueToRun(?\Carbon\CarbonImmutable $now = null): bool
    {
        $last = CrmSetting::get(self::LAST_RUN_KEY, null);
        if (blank($last)) {
            return true;
        }

        try {
            $lastAt = \Carbon\CarbonImmutable::parse($last);
        } catch (\Throwable) {
            return true;
        }

        $now ??= \Carbon\CarbonImmutable::now();

        return $lastAt->addSeconds(self::runEveryMinutes() * 60 - 30)->lessThanOrEqualTo($now);
    }

    public static function markRun(?\Carbon\CarbonImmutable $at = null): void
    {
        CrmSetting::set(self::LAST_RUN_KEY, ($at ?? \Carbon\CarbonImmutable::now())->toDateTimeString());
    }

    public static function lastRunAt(): ?\Carbon\CarbonImmutable
    {
        $last = CrmSetting::get(self::LAST_RUN_KEY, null);
        if (blank($last)) {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse($last);
        } catch (\Throwable) {
            return null;
        }
    }

    /** برچسبِ فارسیِ هر بُعدِ امتیازدهی — برای صفحهٔ تنظیمات و توضیح در پنل. */
    public const WEIGHT_LABELS = [
        'open_orders' => 'سفارش‌های باز (کمتر = بهتر)',
        'debt' => 'بدهی کیف‌پول (کمتر = بهتر)',
        'satisfaction' => 'رضایت مشتری (بیشتر = بهتر)',
        'cancel_rate' => 'نرخ کنسلی (کمتر = بهتر)',
        'recent_activity' => 'فعالیت اخیر (تازه‌تر = بهتر)',
        'response_speed' => 'سرعت پاسخگویی (سریع‌تر = بهتر)',
    ];

    /**
     * وزن‌هایی که مدیر وارد کرده — خام و بدون نرمال‌سازی. اگر کلیدی ثبت
     * نشده باشد مقدار پیش‌فرضِ سرویس امتیازدهی برمی‌گردد.
     *
     * @return array<string, int>
     */
    public static function rawWeights(): array
    {
        $defaults = TechnicianSuggestionService::WEIGHTS;
        $stored = CrmSetting::getJson('assign_weights', []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $out = [];
        foreach ($defaults as $key => $default) {
            $value = array_key_exists($key, $stored) ? $stored[$key] : $default;
            $out[$key] = max(0, min(100, (int) $value));
        }

        return $out;
    }

    /**
     * وزن‌های مؤثر — همیشه جمعشان دقیقاً ۱۰۰ است.
     *
     * چرا نرمال‌سازی: آستانه‌های سطح‌بندی (۸۰ پیشنهاد ویژه، ۶۰ مناسب، …)
     * روی مقیاسِ ۰..۱۰۰ تعریف شده‌اند. اگر مدیر مجموعِ ۱۵۰ وارد کند و
     * نرمال نکنیم، همهٔ تکنسین‌ها «پیشنهاد ویژه» می‌شوند و سطح‌بندی
     * بی‌معنا می‌شود. با نرمال‌سازی، مدیر می‌تواند نسبت‌ها را آزادانه
     * تغییر دهد و مقیاس سالم بماند.
     *
     * روشِ توزیعِ باقیمانده «بزرگ‌ترین باقی‌مانده» است تا جمع دقیقاً ۱۰۰
     * شود و هیچ وزنی منفی نشود.
     *
     * @return array<string, int>
     */
    public static function weights(): array
    {
        $raw = self::rawWeights();
        $sum = array_sum($raw);

        // همهٔ وزن‌ها صفر = پیکربندیِ بی‌معنا؛ به پیش‌فرض برمی‌گردیم.
        if ($sum <= 0) {
            return TechnicianSuggestionService::WEIGHTS;
        }

        $exact = [];
        $out = [];
        foreach ($raw as $key => $value) {
            $exact[$key] = $value / $sum * 100;
            $out[$key] = (int) floor($exact[$key]);
        }

        $remainder = 100 - array_sum($out);
        if ($remainder > 0) {
            $fractions = [];
            foreach ($exact as $key => $value) {
                $fractions[$key] = $value - floor($value);
            }
            arsort($fractions);
            foreach (array_keys($fractions) as $key) {
                if ($remainder <= 0) {
                    break;
                }
                $out[$key]++;
                $remainder--;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $values */
    public static function saveWeights(array $values): void
    {
        $clean = [];
        foreach (array_keys(TechnicianSuggestionService::WEIGHTS) as $key) {
            $clean[$key] = max(0, min(100, (int) ($values[$key] ?? 0)));
        }

        CrmSetting::setJson('assign_weights', $clean);
    }

    /** @return array<string, string|int> همهٔ مقادیر برای صفحهٔ تنظیمات */
    public static function all(): array
    {
        return [
            'assign_mode' => self::mode(),
            'assign_grace_minutes' => self::graceMinutes(),
            'assign_min_score' => self::minScore(),
            'assign_max_per_run' => self::maxPerRun(),
            'assign_max_age_days' => self::maxAgeDays(),
            'assign_history_enabled' => self::historyEnabled() ? 1 : 0,
            'assign_history_months' => self::historyMonths(),
            'assign_balance_cap' => self::balanceCap(),
            'assign_run_every_minutes' => self::runEveryMinutes(),
            'assign_last_run_at' => self::lastRunAt()?->format('Y-m-d H:i:s'),
            'weights_raw' => self::rawWeights(),
            'weights_effective' => self::weights(),
        ];
    }

    /** @param  array<string, mixed>  $values */
    public static function save(array $values): void
    {
        foreach (self::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            CrmSetting::set($key, (string) $values[$key]);
        }
    }

    private static function intValue(string $key, int $min, int $max): int
    {
        $raw = CrmSetting::get($key, (string) self::DEFAULTS[$key]);

        return max($min, min($max, (int) $raw));
    }
}
