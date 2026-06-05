<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن زون بنر `home_promo` برای سکشن بنر تبلیغاتی صفحه‌ی خانه.
 *
 * این زون از سکشن `promo` صفحه‌ی home از طریق فیلد `banner_zone` ارجاع می‌شود
 * تا فرانت با یک API call (/v1/pages/home) کل بنر را inline دریافت کند.
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
                'slug' => 'home_promo',
                'name' => 'صفحه‌ی اصلی — بنر تبلیغاتی',
                'description' => 'بنری که در صفحه‌ی خانه بین سکشن برندها و سوالات متداول نمایش داده می‌شود.',
                'recommended_width' => 1200,
                'recommended_height' => 300,
                'sort_order' => 9,
            ],
            [
                'slug' => 'about_promo',
                'name' => 'درباره ما — بنر تبلیغاتی',
                'description' => 'بنر تبلیغاتی صفحه‌ی درباره ما.',
                'recommended_width' => 1200,
                'recommended_height' => 300,
                'sort_order' => 10,
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
            ->whereIn('slug', ['home_promo', 'about_promo'])
            ->delete();
    }
};
