<?php

namespace Modules\Site\Support;

use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Services\ServiceCoverage;

/**
 * توکن‌های داینامیکِ متن‌های CMS (خواستهٔ ۱۴۰۵/۰۶/۰۳ — کپشنِ داینامیک):
 *
 *   {{device}}     نام دستگاه («لباسشویی»)
 *   {{brand}}      نام برند — فقط در صفحاتِ ترکیبی؛ در صفحهٔ خودِ دستگاه حذف می‌شود
 *   {{cities}}     شهرهای واقعیِ تحتِ پوششِ همین خدمت («تهران، مشهد و کرج» — مرکز استان اول)
 *   {{provinces}}  استان‌های تحتِ پوشش
 *   {{city_count}} تعدادِ شهرها (رقمِ فارسی)
 *
 * مکان‌ها از موتورِ پوشش (تگِ تکنسین‌ها + کنترلِ «نمایش در سایت» ادمین)
 * می‌آیند؛ در صفحهٔ ترکیبی، شهرها به شهرهایی که آن برند در آن‌ها مجاز است
 * محدود می‌شوند. resolve سمتِ پنل انجام می‌شود — سایت متنِ نهایی می‌گیرد.
 */
class CaptionTokens
{
    public static function resolve(?string $text, Device $device, ?Brand $brand = null): ?string
    {
        if ($text === null || $text === '' || ! str_contains($text, '{{')) {
            return $text;
        }

        [$cities, $provinces] = self::places($device, $brand);

        $out = strtr($text, [
            '{{device}}' => (string) $device->name,
            '{{brand}}' => (string) ($brand?->name ?? ''),
            '{{cities}}' => self::joinFa($cities),
            '{{provinces}}' => self::joinFa($provinces),
            '{{city_count}}' => self::faDigits(count($cities)),
        ]);

        // فاصله‌های دوتاییِ ناشی از توکن‌های خالی (مثلِ برند در صفحهٔ دستگاه).
        $out = trim((string) preg_replace('/\s{2,}/u', ' ', $out));

        return $out === '' ? null : $out;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private static function places(Device $device, ?Brand $brand): array
    {
        $coverage = app(ServiceCoverage::class)->forDevice((int) $device->id);
        if (! $coverage) {
            return [[], []];
        }

        $cities = [];
        $provinces = [];
        foreach ($coverage['provinces'] as $province) {
            $names = [];
            foreach ($province['cities'] as $city) {
                if (! ($city['site_visible'] ?? true)) {
                    continue;
                }
                if ($brand && $city['brands'] !== 'all'
                    && ! in_array($brand->slug, (array) $city['brands'], true)) {
                    continue; // این شهر برای این برند تکنسین ندارد
                }
                $names[] = $city['name'];
            }
            if ($names !== []) {
                $provinces[] = $province['name'];
                $cities = array_merge($cities, $names);
            }
        }

        return [$cities, $provinces];
    }

    /** «تهران، مشهد و کرج» */
    private static function joinFa(array $items): string
    {
        $items = array_values(array_unique(array_filter($items)));
        if ($items === []) {
            return '';
        }
        if (count($items) === 1) {
            return $items[0];
        }
        $last = array_pop($items);

        return implode('، ', $items).' و '.$last;
    }

    private static function faDigits(int $n): string
    {
        return strtr((string) $n, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }
}
