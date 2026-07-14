<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * قالب‌های پیش‌فرضِ Title/Description صفحاتِ خدمات (device) و برند-خدمات
 * (brand_device) طبق دستورکارِ SEMrush — در سطحِ تنظیماتِ پنل ست می‌شوند تا
 * مقدارِ قدیمیِ ذخیره‌شده در پنل روی پیش‌فرضِ جدیدِ config سایه نیندازد.
 *
 * اولویتِ resolver دست‌نخورده می‌ماند: متایِ دستیِ هر صفحه (seo_meta) همچنان
 * بر این قالب‌ها مقدم است.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_settings')) {
            return;
        }

        $templates = [
            'tpl_device_title' => 'تعمیر %title% | اعزام 3 ساعته | 180 روز ضمانت',
            'tpl_device_description' => 'تعمیر %title% در محل با 180 روز ضمانت | اعزام 3 ساعته تعمیرکار %title% | تعمیرات %title% حتی روزهای تعطیل | جدول هزینه ها',
            'tpl_brand_device_title' => 'تعمیر %device% %brand% | اعزام 3 ساعته | 180 روز ضمانت',
            'tpl_brand_device_description' => 'تعمیر %device% %brand% در محل با 180 روز ضمانت | اعزام 3 ساعته تعمیرکار %device% %brand% | تعمیرات %device% %brand% حتی روزهای تعطیل | جدول هزینه ها',
            'tpl_device_brand_page_title' => 'تعمیر %device% %brand% | اعزام 3 ساعته | 180 روز ضمانت',
            'tpl_device_brand_page_description' => 'تعمیر %device% %brand% در محل با 180 روز ضمانت | اعزام 3 ساعته تعمیرکار %device% %brand% | تعمیرات %device% %brand% حتی روزهای تعطیل | جدول هزینه ها',
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
