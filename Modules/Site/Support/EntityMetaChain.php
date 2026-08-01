<?php

namespace Modules\Site\Support;

use Modules\Seo\Support\TemplateMeta;

/**
 * زنجیرهٔ متایِ صفحاتِ مستقلِ دستگاه و برند در کانالِ کاتالوگ — منبعِ واحد.
 *
 * قرینهٔ `ComboMetaChain` است و به همان دلیل بیرون کشیده شد: ابزارِ بازبینی
 * باید همان مقداری را گزارش کند که بازدیدکننده می‌گیرد، و کپی‌کردنِ منطق در
 * ابزار یعنی گزارشی که با اولین تغییرِ کنترلر بی‌سروصدا دروغ می‌گوید.
 *
 * ترتیب — دقیقاً همان ترتیبی که `MetaResolver` در کانالِ سئو دارد:
 *   پنلِ سئو (`seo_meta`) ← فیلدِ خودِ موجودیت ← ردیفِ محتواییِ صفحه ← قالب.
 *
 * سطحِ اول تازه اضافه شده. تا پیش از این کانالِ کاتالوگ `seo_meta` را اصلاً
 * نمی‌خواند، پس عنوانی که ادمین در پنلِ سئو نوشته بود از یک مسیر دیده می‌شد و
 * از مسیرِ دیگر نه — همان «اختلافِ دو کانال»ی که ابزارِ بازبینی گزارش می‌کرد.
 */
final class EntityMetaChain
{
    /**
     * @param  string  $type  `device` یا `brand` — کلیدِ قالب در تنظیماتِ سئو
     * @param  array<string, mixed>  $template  خروجیِ loadForPublic صفحهٔ متناظر
     * @return array{meta_title: ?string, meta_description: ?string}
     */
    public static function for(
        string $type,
        string $name,
        ?string $metaTitle,
        ?string $metaDescription,
        array $template = [],
        ?object $seoMeta = null,
    ): array {
        // نوشتهٔ دستی عیناً می‌ماند؛ سقفِ طول فقط روی خروجیِ قالب اعمال می‌شود.
        // (`TemplateMeta` خودش سقف را می‌زند.)
        $manualTitle = CatalogMerger::pick(
            $seoMeta->title ?? null,
            CatalogMerger::pick($metaTitle, $template['seo']['meta_title'] ?? null)
        );
        $manualDescription = CatalogMerger::pick(
            $seoMeta->description ?? null,
            CatalogMerger::pick($metaDescription, $template['seo']['meta_description'] ?? null)
        );

        $title = $manualTitle !== null && trim((string) $manualTitle) !== ''
            ? trim((string) $manualTitle)
            : TemplateMeta::title($type, ['title' => $name]);

        $description = $manualDescription !== null && trim((string) $manualDescription) !== ''
            ? trim((string) $manualDescription)
            : TemplateMeta::description($type, ['title' => $name]);

        return [
            'meta_title' => $title ?: null,
            'meta_description' => $description ?: null,
        ];
    }
}
