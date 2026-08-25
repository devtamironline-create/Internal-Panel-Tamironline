<?php

namespace Modules\CRM\Support;

use Modules\CRM\Models\Brand;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Services\ServiceCoverage;

/**
 * «الگوی عنوانِ مناطق تحت پوشش» به ازای هر خدمت (طرحِ ۱۴۰۵/۰۶/۰۳):
 *
 * ادمین برای هر خدمت چند ردیفِ عنوان می‌سازد (پیشوند + برندِ اختیاری +
 * حرفِ اضافه) و سایت برای هر استان/شهر/منطقهٔ تحتِ پوشش عنوان می‌سازد:
 *
 *   {پیشوند} {دستگاه} {برند؟} {حرف اضافه} {مکان}
 *   «تعمیر لباسشویی بوش در تهران»
 *
 * ذخیره در crm_settings (کلیدِ per-device) — بدونِ migration.
 */
class CoverageTitles
{
    public const PREPOSITIONS = ['در', 'برای'];

    public static function key(int $deviceId): string
    {
        return 'coverage.titles.d'.$deviceId;
    }

    /**
     * ردیف‌های عنوانِ یک خدمت — اگر ادمین چیزی نساخته، یک ردیفِ پیش‌فرض.
     *
     * @return array<int, array{prefix: string, brand_id: int|null, preposition: string}>
     */
    public static function get(int $deviceId): array
    {
        $rows = CrmSetting::getJson(self::key($deviceId), null);
        if (! is_array($rows) || $rows === []) {
            return [['prefix' => 'تعمیر', 'brand_id' => null, 'preposition' => 'در']];
        }

        return array_values(array_map(fn ($r) => [
            'prefix' => (string) ($r['prefix'] ?? 'تعمیر'),
            'brand_id' => isset($r['brand_id']) && (int) $r['brand_id'] > 0 ? (int) $r['brand_id'] : null,
            'preposition' => in_array($r['preposition'] ?? null, self::PREPOSITIONS, true) ? $r['preposition'] : 'در',
        ], $rows));
    }

    /**
     * ذخیرهٔ ردیف‌ها (ترتیبِ آرایه = ترتیبِ نمایش در سایت).
     * ردیف‌های بدونِ پیشوند حذف می‌شوند؛ برندِ نامعتبر null می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function save(int $deviceId, array $rows): void
    {
        $validBrandIds = Brand::query()->pluck('id')->flip();

        $clean = [];
        foreach ($rows as $r) {
            $prefix = trim((string) ($r['prefix'] ?? ''));
            if ($prefix === '') {
                continue;
            }
            $brandId = (int) ($r['brand_id'] ?? 0);
            $clean[] = [
                'prefix' => mb_substr($prefix, 0, 60),
                'brand_id' => $brandId > 0 && isset($validBrandIds[$brandId]) ? $brandId : null,
                'preposition' => in_array($r['preposition'] ?? null, self::PREPOSITIONS, true) ? $r['preposition'] : 'در',
            ];
        }

        CrmSetting::setJson(self::key($deviceId), $clean);
        // خروجیِ سایت (services[].titles) از این تنظیم ساخته می‌شود.
        ServiceCoverage::forget();
        \Modules\CustomerApp\Support\AppCacheVersion::bump();
    }

    /**
     * نسخهٔ API (برای سایت): برند با slug/نام تا مصرف‌کننده lookup نخواهد.
     *
     * @param  \Illuminate\Support\Collection<int, Brand>  $brandsById  keyBy('id')
     * @return array<int, array{prefix: string, brand: string|null, brand_name: string|null, preposition: string}>
     */
    public static function forApi(int $deviceId, $brandsById): array
    {
        return array_map(function (array $r) use ($brandsById) {
            $brand = $r['brand_id'] !== null ? $brandsById->get($r['brand_id']) : null;

            return [
                'prefix' => $r['prefix'],
                'brand' => $brand?->slug,
                'brand_name' => $brand?->name,
                'preposition' => $r['preposition'],
            ];
        }, self::get($deviceId));
    }
}
