<?php

namespace Modules\Seo\Support;

use Modules\Seo\Models\SeoSetting;
use Modules\Seo\Services\VariableRenderer;

/**
 * کفِ زنجیرهٔ متا برای endpointهای کاتالوگ.
 *
 * چرا لازم است: کانالِ کاتالوگ قالب‌هایش را از `site_page_sections` می‌خواند و
 * آن ردیف‌ها در عمل خالی‌اند. تا وقتی ستونِ `meta_title` موجودیت پر بود این
 * دیده نمی‌شد؛ به‌محضِ خالی‌شدنِ آن ستون‌ها (پاک‌سازیِ متایِ واردشده از وردپرس)
 * صفحه بدونِ هیچ عنوانی سرو می‌شود.
 *
 * این کلاس همان قالبِ تأییدشدهٔ ماژول سئو را در اختیارِ کانالِ کاتالوگ می‌گذارد،
 * پس متن یک‌جا تعریف می‌شود و هر دو کانال یک چیز می‌گویند.
 *
 * عمداً به `tpl_global_*` برنمی‌گردد: قالبِ سراسری متغیرهای دامنه‌ای ندارد و
 * برگشت به آن همان عنوان‌های بی‌خاصیت را برمی‌گرداند.
 */
final class TemplateMeta
{
    public static function title(string $type, array $vars): string
    {
        return MetaLength::title(self::render($type, 'title', $vars));
    }

    public static function description(string $type, array $vars): string
    {
        return MetaLength::description(self::render($type, 'description', $vars));
    }

    /** @param  array<string, string|null>  $vars */
    private static function render(string $type, string $field, array $vars): string
    {
        foreach ($vars as $value) {
            // بدونِ نامِ موجودیت، قالب چیزی جز متنِ ثابت تولید نمی‌کند — و متنِ
            // ثابت روی همهٔ صفحات یعنی همان عنوانِ تکراری که از آن فرار می‌کنیم.
            if (trim((string) $value) === '') {
                return '';
            }
        }

        // خواندنِ تنظیمات نباید صفحهٔ عمومی را بشکند (جدولِ نبوده، دیتابیسِ خاموش).
        $stored = rescue(fn () => SeoSetting::get("tpl_{$type}_{$field}"), null, false);

        $template = $stored ?: (string) config("seo.templates.{$type}.{$field}", '');

        return (new VariableRenderer)->render($template, $vars);
    }
}
