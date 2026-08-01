<?php

namespace Modules\Seo\Support;

use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\VariableRenderer;

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
        return MetaLength::title(self::render('title', $device, $brand));
    }

    public static function description(?string $device, ?string $brand): string
    {
        return MetaLength::description(self::render('description', $device, $brand));
    }

    private static function render(string $field, ?string $device, ?string $brand): string
    {
        $device = trim((string) $device);
        $brand = trim((string) $brand);

        // بدونِ نامِ دستگاه، عنوان همان چیزی می‌شود که می‌خواهیم از آن فرار کنیم.
        if ($device === '' || $brand === '') {
            return '';
        }

        // خواندنِ تنظیمات نباید صفحهٔ عمومی را بشکند (جدولِ نبوده، دیتابیسِ خاموش).
        $stored = rescue(fn () => SeoSetting::get('tpl_brand_device_'.$field), null, false);

        $template = $stored ?: (string) config('seo.templates.brand_device.'.$field, '');

        return (new VariableRenderer)->render($template, [
            'device' => $device,
            'brand' => $brand,
        ]);
    }
}
