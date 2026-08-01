<?php

namespace Modules\Seo\Support;

/**
 * قالبِ متایِ صفحهٔ ترکیبی (دستگاه × برند) برای مصرفِ endpointِ کاتالوگ.
 *
 * چرا لازم است: متایِ صفحهٔ ترکیبی از دو کانالِ موازی سرو می‌شود —
 *   الف) `/v1/seo/meta?type=brand_device` که قالب‌ها را از `seo_settings`/config می‌خواند،
 *   ب) `/v1/catalog/devices/{device}/{brand}` که `$template['seo']` را از
 *      `site_page_sections` (ردیفِ محتوایِ پنل) می‌خواند.
 *
 * آن ردیفِ محتوایی هیچ seederی ندارد و در عمل خالی است. با حذفِ
 * `brand->meta_*`/`device->meta_*` از زنجیره، کانالِ (ب) بدونِ این fallback
 * متایِ خالی برمی‌گرداند. این کلاس همان یک قالبِ تأییدشده را در اختیارِ هر دو
 * کانال می‌گذارد تا متن در یک‌جا تعریف شود، نه دو جا.
 *
 * عمداً به `tpl_global_*` برنمی‌گردد: قالبِ سراسری `%device%`/`%brand%` ندارد و
 * برگشت به آن دقیقاً همان عنوان‌های تکراری را برمی‌گرداند که این تغییر برای
 * رفعشان نوشته شده.
 */
final class ComboMeta
{
    public static function title(?string $device, ?string $brand): string
    {
        return TemplateMeta::title('brand_device', self::vars($device, $brand));
    }

    public static function description(?string $device, ?string $brand): string
    {
        return TemplateMeta::description('brand_device', self::vars($device, $brand));
    }

    /** @return array<string, string> */
    private static function vars(?string $device, ?string $brand): array
    {
        return ['device' => trim((string) $device), 'brand' => trim((string) $brand)];
    }
}
