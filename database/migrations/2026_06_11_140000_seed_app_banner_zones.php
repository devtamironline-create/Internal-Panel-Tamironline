<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * زون‌های بنر مخصوص اپلیکیشن مشتریان.
 *
 * Slugها با پیشوند «app_» تا با زون‌های وب قاطی نشوند. این چهار زون نقطه‌ی
 * شروع است؛ ادمین می‌تواند از /admin/site/banner-zones زون‌های بیشتری
 * اضافه کند و تیم فرانت بدون deploy آن‌ها را در API می‌بیند.
 *
 * فرانت از GET /v1/customer/services/banners?placement=<slug> استفاده می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $zones = [
            [
                'slug' => 'app_home_top',
                'name' => 'اپ — بنر بالای صفحه اصلی (اسلایدر)',
                'description' => 'بنر هیرو در بالای صفحه اصلی اپ — اسلایدر، حداکثر ۵ تا',
                'recommended_width' => 1080,
                'recommended_height' => 540,
                'max_count' => 5,
                'sort_order' => 100,
            ],
            [
                'slug' => 'app_home_promo',
                'name' => 'اپ — بنر تبلیغی صفحه اصلی',
                'description' => 'بنر تبلیغی پایین‌تر در صفحه اصلی (مثلاً کد تخفیف، معرفی خدمت جدید)',
                'recommended_width' => 1080,
                'recommended_height' => 400,
                'max_count' => 3,
                'sort_order' => 101,
            ],
            [
                'slug' => 'app_orders_promo',
                'name' => 'اپ — بنر صفحه سفارش‌ها',
                'description' => 'بنر تبلیغی در صفحه سفارش‌ها (مثلاً «خدمت جدید را امتحان کنید»)',
                'recommended_width' => 1080,
                'recommended_height' => 300,
                'max_count' => 2,
                'sort_order' => 102,
            ],
            [
                'slug' => 'app_profile_promo',
                'name' => 'اپ — بنر صفحه پروفایل',
                'description' => 'بنر در صفحه پروفایل کاربر (مثلاً معرفی دوستان، باشگاه مشتریان)',
                'recommended_width' => 1080,
                'recommended_height' => 300,
                'max_count' => 2,
                'sort_order' => 103,
            ],
        ];

        foreach ($zones as $z) {
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
            ->whereIn('slug', ['app_home_top', 'app_home_promo', 'app_orders_promo', 'app_profile_promo'])
            ->delete();
    }
};
