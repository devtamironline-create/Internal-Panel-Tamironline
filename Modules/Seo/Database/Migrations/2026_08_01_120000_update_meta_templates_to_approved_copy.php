<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * قالب‌های تأییدشدهٔ Title/Description (مرداد ۱۴۰۵) — جایگزینِ نسخهٔ تیر.
 *
 * چرا مهاجرتِ تازه و نه ویرایشِ `2026_07_13_170000`: آن مهاجرت روی پروداکشن
 * اجرا شده و دوباره اجرا نمی‌شود. مقدارهایش در `seo_settings` نشسته‌اند و طبق
 * `MetaResolver::template()` تنظیمِ پنل بر config **مقدم** است — یعنی تغییرِ
 * `Config/config.php` به‌تنهایی هیچ اثری روی سایتِ زنده ندارد. این ردیف‌ها
 * باید صریحاً به‌روزرسانی شوند.
 *
 * `tpl_brand_*` تازه اضافه می‌شود؛ نسخهٔ قبلی آن را ست نکرده بود و صفحاتِ برند
 * از config می‌خواندند.
 *
 * اولویتِ resolver دست‌نخورده می‌ماند: متایِ دستیِ هر صفحه (`seo_meta`) همچنان
 * بر این قالب‌ها مقدم است.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_settings')) {
            return;
        }

        $comboTitle = 'تعمیر %device% %brand% | تعمیرات ۳ ساعته و ۶ ماه ضمانت';
        $comboDescription = 'تعمیر تخصصی %device% %brand% در محل با تعمیرکار ۵ ستاره | اعزام تا ۳ ساعت | ۱۸۰ روز ضمانت و خدمات ۷ روز هفته حتی تعطیلات';

        $templates = [
            // صفحهٔ دستگاه — %title% نامِ دستگاه است (title_attr = name).
            'tpl_device_title' => 'تعمیر %title% | تعمیرکار ۵ ستاره، اعزام تا ۳ ساعت',
            'tpl_device_description' => 'تعمیر %title% در محل با تعمیرکاران ۵ ستاره | اعزام تا ۳ ساعت | ۱۸۰ روز ضمانت و خدمات ۷ روز هفته حتی تعطیلات',

            // صفحهٔ برند — %title% نامِ برند است. %brand% اینجا مقدار ندارد.
            'tpl_brand_title' => 'تعمیرات %title% در محل | اعزام ۳ ساعته، ضمانت ۱۸۰ روزه',
            'tpl_brand_description' => 'تعمیر تخصصی لوازم خانگی %title% در محل؛ اعزام تا ۳ ساعت، ضمانت ۱۸۰ روزه و خدمات حتی تعطیلات. تفاوت خدمات ما با نمایندگی %title% را ببینید.',

            // صفحهٔ ترکیبی — هر دو متغیر لازم‌اند، وگرنه همهٔ صفحاتِ یک برند
            // عنوانِ یکسان می‌گیرند (همان چیزی که SEMrush گزارش کرد).
            'tpl_brand_device_title' => $comboTitle,
            'tpl_brand_device_description' => $comboDescription,
            'tpl_device_brand_page_title' => $comboTitle,
            'tpl_device_brand_page_description' => $comboDescription,
        ];

        try {
            foreach ($templates as $key => $value) {
                \Modules\Seo\Models\SeoSetting::set($key, $value, 'templates');
            }
        } catch (\Throwable $e) {
            // نباید دیپلوی را بشکند؛ قالب‌ها از config هم fallback دارند.
        }
    }

    public function down(): void
    {
        // برگشت لازم نیست — قالب‌ها از پنل قابلِ ویرایش‌اند.
    }
};
