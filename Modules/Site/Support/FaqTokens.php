<?php

namespace Modules\Site\Support;

use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Services\ServiceCoverage;

/**
 * توکن‌های داینامیکِ متنِ FAQ (سؤال و پاسخ) — هم‌سبکِ بقیهٔ placeholderهای FAQ
 * که تک‌آکولادی‌اند ({device} و …). خواستهٔ ۱۴۰۵/۰۶: افزودنِ «شهرهای پوشش».
 *
 *   {cities}      شهرهای واقعیِ تحتِ پوشش («تهران، مشهد و کرج» — مرکز استان اول)
 *   {provinces}   استان‌های تحتِ پوشش
 *   {city_count}  تعدادِ شهرها (رقمِ فارسی)
 *   {device} {device_label} {device_slug} {brand} {brand_slug} {page_title}
 *
 * نکته: ردیف‌های FAQ که ادمین می‌سازد تا امروز هیچ توکنی را resolve نمی‌کردند
 * (فقط FAQِ داخلِ الگوی page-content جایگزین می‌شد)؛ این کلاس هر دو را پوشش
 * می‌دهد. در صفحهٔ دستگاه/ترکیبی شهرها از پوششِ همان خدمت (و در ترکیبی محدود
 * به برند) می‌آیند؛ در صفحهٔ برند/عمومی از پوششِ سراسریِ سایت. resolve سمتِ
 * پنل است — اپ/سایت متنِ نهایی می‌گیرد. توکنِ ناشناخته دست‌نخورده می‌ماند.
 */
final class FaqTokens
{
    /**
     * @param  array{items?: array<int, array<string, mixed>>, categories?: array<int, array<string, mixed>>}  $faq
     * @return array<string, mixed>
     */
    public static function apply(array $faq, ?Device $device = null, ?Brand $brand = null): array
    {
        if (! self::sectionHasToken($faq)) {
            return $faq; // هیچ توکنی نیست — هزینهٔ محاسبهٔ پوشش را نمی‌دهیم.
        }

        $map = self::replacements($device, $brand);
        $resolve = fn (string $t): string => str_contains($t, '{') ? strtr($t, $map) : $t;

        if (isset($faq['items']) && is_array($faq['items'])) {
            $faq['items'] = array_map(fn ($i) => self::item($i, $resolve), $faq['items']);
        }
        if (isset($faq['categories']) && is_array($faq['categories'])) {
            $faq['categories'] = array_map(function ($c) use ($resolve) {
                if (isset($c['items']) && is_array($c['items'])) {
                    $c['items'] = array_map(fn ($i) => self::item($i, $resolve), $c['items']);
                }

                return $c;
            }, $faq['categories']);
        }

        return $faq;
    }

    /** @param  array<string, mixed>  $item */
    private static function item(array $item, callable $resolve): array
    {
        foreach (['question', 'answer'] as $field) {
            if (isset($item[$field]) && is_string($item[$field])) {
                $item[$field] = $resolve($item[$field]);
            }
        }

        return $item;
    }

    /** @param  array<string, mixed>  $faq */
    private static function sectionHasToken(array $faq): bool
    {
        $buckets = [$faq['items'] ?? []];
        foreach (($faq['categories'] ?? []) as $c) {
            $buckets[] = $c['items'] ?? [];
        }
        foreach ($buckets as $items) {
            foreach ((array) $items as $i) {
                if (str_contains((string) ($i['question'] ?? ''), '{') || str_contains((string) ($i['answer'] ?? ''), '{')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * نقشهٔ جایگزینیِ تک‌آکولادی.
     *
     * @return array<string, string>
     */
    private static function replacements(?Device $device, ?Brand $brand): array
    {
        [$cities, $provinces] = $device instanceof Device
            ? self::devicePlaces($device, $brand)
            : self::globalPlaces();

        $map = [
            '{cities}' => self::joinFa($cities),
            '{provinces}' => self::joinFa($provinces),
            '{city_count}' => self::faDigits(count($cities)),
            '{brand}' => (string) ($brand?->name ?? ''),
            '{brand_slug}' => (string) ($brand?->slug ?? ''),
        ];

        if ($device instanceof Device) {
            $map['{device}'] = (string) ($device->short_name ?? $device->name);
            $map['{device_label}'] = (string) $device->name;
            $map['{device_slug}'] = (string) $device->slug;
            $map['{page_title}'] = trim((string) (($device->service_name ?? $device->name).' '.($brand?->name ?? '')));
        }

        return $map;
    }

    /**
     * شهرها/استان‌های پوششِ یک خدمت (در ترکیبی محدود به برند) — هم‌منطقِ
     * CaptionTokens برای سازگاریِ «مرکز استان اول» و فیلترِ برند.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private static function devicePlaces(Device $device, ?Brand $brand): array
    {
        $coverage = app(ServiceCoverage::class)->forDevice((int) $device->id);
        if (! $coverage) {
            return [[], []];
        }

        $cities = [];
        $provinces = [];
        foreach (($coverage['provinces'] ?? []) as $province) {
            $names = [];
            foreach (($province['cities'] ?? []) as $city) {
                if (! ($city['site_visible'] ?? true)) {
                    continue;
                }
                if ($brand && ($city['brands'] ?? 'all') !== 'all'
                    && ! in_array($brand->slug, (array) $city['brands'], true)) {
                    continue;
                }
                $names[] = (string) ($city['name'] ?? '');
            }
            if (array_filter($names) !== []) {
                $provinces[] = (string) ($province['name'] ?? '');
                $cities = array_merge($cities, $names);
            }
        }

        return [array_values(array_filter($cities)), array_values(array_filter($provinces))];
    }

    /**
     * اجتماعِ شهرها/استان‌های تحتِ پوششِ سایت (برای متنِ عمومیِ بدونِ دستگاه).
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private static function globalPlaces(): array
    {
        $cities = [];
        $provinces = [];
        try {
            $table = app(ServiceCoverage::class)->siteTable();
            foreach (($table['services'] ?? []) as $service) {
                foreach (($service['provinces'] ?? []) as $province) {
                    $has = false;
                    foreach (($province['cities'] ?? []) as $city) {
                        if (! ($city['site_visible'] ?? true)) {
                            continue;
                        }
                        $cities[] = (string) ($city['name'] ?? '');
                        $has = true;
                    }
                    if ($has) {
                        $provinces[] = (string) ($province['name'] ?? '');
                    }
                }
            }
        } catch (\Throwable $e) {
            return [[], []];
        }

        return [array_values(array_unique(array_filter($cities))), array_values(array_unique(array_filter($provinces)))];
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
        return strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}
