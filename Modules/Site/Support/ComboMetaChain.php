<?php

namespace Modules\Site\Support;

use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\Seo\Support\ComboMeta;

/**
 * زنجیرهٔ متایِ صفحهٔ ترکیبی (دستگاه × برند) — منبعِ واحد.
 *
 * تا پیش از این، این منطق داخلِ `CatalogDeviceBrandController::show()` بود.
 * بیرون کشیده شد چون ابزارِ بازبینیِ سئو باید *همان* مقداری را گزارش کند که
 * کاربر می‌بیند؛ اگر ابزار منطق را کپی می‌کرد، با اولین تغییرِ کنترلر بی‌سروصدا
 * دروغ می‌گفت — و ابزارِ بازبینیِ دروغ‌گو از نبودنش بدتر است.
 *
 * ترتیب (بالاترین اولویت اول):
 *   ۱) override دستیِ همین جفت — `crm_device_brand_pages.meta_*`
 *   ۲) الگوی اختصاصیِ این دستگاه — `site_page_sections` با slugِ `device_brand:{slug}`
 *   ۳) الگوی محتواییِ ترکیبی — `site_page_sections` با slugِ `device_brand`
 *   ۴) قالبِ تأییدشدهٔ ماژول سئو — `ComboMeta` (کفِ زنجیره؛ هیچ‌وقت خالی نیست)
 *
 * عمداً `device->meta_*` و `brand->meta_*` در زنجیره نیستند: هرکدام مقدارِ *یک*
 * موجودیت‌اند و در صفحهٔ ترکیبی باعث می‌شدند همهٔ صفحاتِ یک برند (یا یک دستگاه)
 * عنوانِ یکسان بگیرند.
 */
final class ComboMetaChain
{
    /**
     * @param  array<string, mixed>  $template  خروجیِ loadForPublic('device_brand')
     * @param  array<string, mixed>  $deviceCombo  خروجیِ loadForPublic('device_brand:{slug}')
     * @return array{meta_title: ?string, meta_description: ?string}
     */
    public static function for(
        ?DeviceBrandPage $page,
        Device $device,
        Brand $brand,
        array $template = [],
        array $deviceCombo = [],
    ): array {
        // نوشتهٔ دستی عیناً می‌ماند؛ سقفِ طول فقط روی خروجیِ قالب اعمال می‌شود
        // (`ComboMeta` خودش سقف را می‌زند). کوتاه‌کردنِ بی‌خبرِ متنی که ادمین
        // تایپ کرده یعنی «چیزی که نوشتم عوض شد».
        $title = self::pick(
            // پنلِ سئو — کانالِ سئو از اول این را می‌خواند و کاتالوگ نمی‌خواند؛
            // نتیجه‌اش دو عنوانِ متفاوت برای یک صفحه بود.
            $page?->seoMeta?->title,
            $page?->meta_title,
            $deviceCombo['seo']['meta_title'] ?? null,
            $template['seo']['meta_title'] ?? null,
        ) ?? ComboMeta::title($device->name, $brand->name);

        $description = self::pick(
            $page?->seoMeta?->description,
            $page?->meta_description,
            $deviceCombo['seo']['meta_description'] ?? null,
            $template['seo']['meta_description'] ?? null,
        ) ?? ComboMeta::description($device->name, $brand->name);

        return [
            'meta_title' => $title ?: null,
            'meta_description' => $description ?: null,
        ];
    }

    /**
     * اولین مقدارِ غیرخالی. رشتهٔ خالی هم مثلِ null رد می‌شود — همان قاعدهٔ
     * `CatalogMerger::pick` که بقیهٔ endpointهای کاتالوگ با آن کار می‌کنند.
     */
    private static function pick(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $picked = CatalogMerger::pick($value, null);
            if ($picked !== null) {
                return (string) $picked;
            }
        }

        return null;
    }
}
