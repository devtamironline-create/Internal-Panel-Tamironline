<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن زون‌های بنر برای صفحات الگو (template pages):
 *   - brand_promo            → مشترک همه‌ی صفحات /brands/[brand]
 *   - device_promo           → مشترک همه‌ی صفحات /devices/[device]
 *   - brand_device_promo     → مشترک هر دو روت /brands/[b]/[d] و /devices/[d]/[b]
 *
 * این زون‌ها از فیلد banner_zone در سکشن `promo` صفحات الگو reference می‌شوند
 * (ر.ک: Modules/Site/Config/page-sections.php). تنها یک تنظیم برای کل
 * مجموعه‌ی صفحات هر الگو لازم است.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_banner_zones')) {
            return;
        }

        $now = now();
        $zones = [
            [
                'slug' => 'brand_promo',
                'name' => 'صفحات برند — بنر تبلیغاتی مشترک',
                'description' => 'بنری که در همه‌ی صفحات /brands/[brand] نمایش داده می‌شود. تک‌بار تنظیم، در همه‌ی صفحات برند ظاهر می‌شود.',
                'recommended_width' => 1200,
                'recommended_height' => 300,
                'sort_order' => 11,
            ],
            [
                'slug' => 'device_promo',
                'name' => 'صفحات دستگاه — بنر تبلیغاتی مشترک',
                'description' => 'بنری که در همه‌ی صفحات /devices/[device] نمایش داده می‌شود. تک‌بار تنظیم، در همه‌ی صفحات دستگاه ظاهر می‌شود.',
                'recommended_width' => 1200,
                'recommended_height' => 300,
                'sort_order' => 12,
            ],
            [
                'slug' => 'brand_device_promo',
                'name' => 'صفحات ترکیبی دستگاه × برند — بنر تبلیغاتی مشترک',
                'description' => 'بنر مشترک صفحات /brands/[brand]/[device] و /devices/[device]/[brand] (هر دو از یک کامپوننت رندر می‌شوند).',
                'recommended_width' => 1200,
                'recommended_height' => 300,
                'sort_order' => 13,
            ],
        ];

        foreach ($zones as $z) {
            if (DB::table('site_banner_zones')->where('slug', $z['slug'])->exists()) {
                continue;
            }
            DB::table('site_banner_zones')->insert(array_merge($z, [
                'max_count' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_banner_zones')) {
            return;
        }
        DB::table('site_banner_zones')
            ->whereIn('slug', ['brand_promo', 'device_promo', 'brand_device_promo'])
            ->delete();
    }
};
