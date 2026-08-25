<?php

namespace Modules\CRM\Support;

use Modules\CRM\Services\IranCoverageMap;

/**
 * مراکزِ استان‌های ایران — برای اینکه در لیست‌های شهر، «مرکزِ استان اول»
 * بیاید (خواستهٔ ۱۴۰۵/۰۶/۰۳). تطبیقِ نام با همان نرمال‌سازیِ نقشهٔ پوشش
 * (فاصله/نیم‌فاصله/ي↔ی) انجام می‌شود تا املای متفاوت مشکل نسازد.
 */
class IranCapitals
{
    private const CAPITALS = [
        'تهران', 'مشهد', 'اصفهان', 'کرج', 'شیراز', 'تبریز', 'قم', 'اهواز',
        'کرمانشاه', 'ارومیه', 'رشت', 'زاهدان', 'همدان', 'کرمان', 'یزد',
        'اردبیل', 'بندرعباس', 'اراک', 'زنجان', 'سنندج', 'قزوین', 'خرم‌آباد',
        'گرگان', 'ساری', 'بجنورد', 'بیرجند', 'ایلام', 'بوشهر', 'شهرکرد',
        'یاسوج', 'سمنان',
    ];

    /** @var array<string, true>|null */
    private static ?array $normalized = null;

    public static function isCapital(?string $cityName): bool
    {
        if ($cityName === null || $cityName === '') {
            return false;
        }

        if (self::$normalized === null) {
            self::$normalized = [];
            foreach (self::CAPITALS as $name) {
                self::$normalized[IranCoverageMap::normalizeName($name)] = true;
            }
        }

        return isset(self::$normalized[IranCoverageMap::normalizeName($cityName)]);
    }

    /**
     * مرکزِ استان اول، بقیه با همان ترتیبِ قبلی (پایدار).
     *
     * @template T of \Illuminate\Support\Collection
     *
     * @param  T  $cities  آیتم‌ها باید name داشته باشند (model یا object)
     * @return T
     */
    public static function capitalsFirst($cities)
    {
        return $cities->sortBy(fn ($c, $i) => [self::isCapital($c->name ?? null) ? 0 : 1, $i])->values();
    }
}
