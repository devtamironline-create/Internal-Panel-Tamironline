<?php

namespace Modules\CRM\Support;

use Modules\CRM\Models\CrmSetting;

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
    ];

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
