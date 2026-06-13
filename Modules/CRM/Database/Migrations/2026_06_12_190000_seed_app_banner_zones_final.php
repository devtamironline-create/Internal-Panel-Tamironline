<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Zoneهای بنر اپلیکیشن مشتریان مطابق درخواست تیم اپ (2026-06-12).
 *
 * نسبت‌های دقیق ابعاد:
 *   - login           16/9   (اسلایدر صفحه‌ی ورود)
 *   - home_top        16/9   (اسلایدر بالای صفحه‌ی اصلی)
 *   - home_promotions 1/1    (کارت‌های مربع تبلیغ)
 *   - orders_list     16/5   (باند افقی صفحه‌ی سفارش‌ها)
 *   - profile         16/5   (باند افقی صفحه‌ی پروفایل)
 *
 * Slugها بدون پیشوند `app_` چون drop-in همان قراردادی است که فرانت ست کرده.
 * Conflict با zoneهای وب ندارد — نام‌های وب: home_hero / home_secondary /
 * home_promo / blog_* / forum_* / services_promo / global_top / about_promo /
 * brand_promo / device_promo / brand_device_promo.
 *
 * Idempotent: قبل از insert با slug چک می‌شود. ۴ zone قبلی (app_home_top،
 * app_home_promo، app_orders_promo، app_profile_promo) که در مهاجرت
 * 2026_06_11_140000 ست شده بودند جایگزین می‌شوند — چون هنوز هیچ بنری به
 * آنها متصل نشده، حذف می‌شوند تا panel/API گیج نشوند.
 */
return new class extends Migration
{
    private const NEW_ZONES = [
        [
            'slug' => 'login',
            'name' => 'اپ — اسلایدر صفحه ورود',
            'description' => 'بنرهای اسلایدر صفحه ورود/ثبت‌نام. نسبت ۱۶/۹.',
            'recommended_width' => 1280,
            'recommended_height' => 720,
            'max_count' => 5,
            'sort_order' => 200,
        ],
        [
            'slug' => 'home_top',
            'name' => 'اپ — اسلایدر بالای صفحه اصلی',
            'description' => 'بنر هیرو بالای صفحه اصلی اپ. نسبت ۱۶/۹، اسلایدر.',
            'recommended_width' => 1280,
            'recommended_height' => 720,
            'max_count' => 5,
            'sort_order' => 201,
        ],
        [
            'slug' => 'home_promotions',
            'name' => 'اپ — کارت‌های تبلیغ صفحه اصلی',
            'description' => 'کارت‌های مربع تبلیغ پایین‌تر در صفحه اصلی. نسبت ۱/۱.',
            'recommended_width' => 1080,
            'recommended_height' => 1080,
            'max_count' => 6,
            'sort_order' => 202,
        ],
        [
            'slug' => 'orders_list',
            'name' => 'اپ — باند افقی صفحه سفارش‌ها',
            'description' => 'بنر باند افقی در صفحه سفارش‌های من. نسبت ۱۶/۵.',
            'recommended_width' => 1600,
            'recommended_height' => 500,
            'max_count' => 3,
            'sort_order' => 203,
        ],
        [
            'slug' => 'profile',
            'name' => 'اپ — باند افقی صفحه پروفایل',
            'description' => 'بنر باند افقی در صفحه پروفایل. نسبت ۱۶/۵.',
            'recommended_width' => 1600,
            'recommended_height' => 500,
            'max_count' => 3,
            'sort_order' => 204,
        ],
    ];

    private const LEGACY_ZONES_TO_REMOVE = [
        'app_home_top',
        'app_home_promo',
        'app_orders_promo',
        'app_profile_promo',
    ];

    public function up(): void
    {
        // ۴ zone قبلی app_* را حذف — فقط اگر هیچ بنری متصل نیست (defense in depth)
        $legacyIds = DB::table('site_banner_zones')
            ->whereIn('slug', self::LEGACY_ZONES_TO_REMOVE)
            ->pluck('id', 'slug');

        foreach ($legacyIds as $slug => $id) {
            $bannerCount = DB::table('site_banners')->where('zone_id', $id)->count();
            if ($bannerCount > 0) {
                // اگر بنری چسبیده، نگه می‌داریم و فقط غیرفعال می‌کنیم — داده از دست نرود
                DB::table('site_banner_zones')->where('id', $id)->update([
                    'is_active' => false,
                    'description' => 'منسوخ‌شده — لطفاً به zone جدید بدون پیشوند app_ مهاجرت کنید',
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('site_banner_zones')->where('id', $id)->delete();
            }
        }

        $now = now();
        foreach (self::NEW_ZONES as $z) {
            $exists = DB::table('site_banner_zones')->where('slug', $z['slug'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('site_banner_zones')->insert(array_merge($z, [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        DB::table('site_banner_zones')
            ->whereIn('slug', array_column(self::NEW_ZONES, 'slug'))
            ->delete();
    }
};
