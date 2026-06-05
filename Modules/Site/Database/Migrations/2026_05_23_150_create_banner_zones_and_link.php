<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banner Zones:
 *   - جدول site_banner_zones با لیست ثابت زون‌ها (home_hero, blog_sidebar, ...)
 *   - افزودن zone_id, media_id, media_id_mobile به site_banners
 *   - افزودن impressions/clicks برای آمار سبک
 *   - seed اولیه‌ی zone‌های پایه
 *   - migration position string → zone_id
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_banner_zones', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('recommended_width')->nullable();
            $table->unsignedSmallInteger('recommended_height')->nullable();
            $table->unsignedTinyInteger('max_count')->default(0);   // 0 = unlimited
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('site_banners', function (Blueprint $table) {
            if (! Schema::hasColumn('site_banners', 'zone_id')) {
                $table->unsignedBigInteger('zone_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('site_banners', 'media_id')) {
                $table->unsignedBigInteger('media_id')->nullable()->after('image_url');
            }
            if (! Schema::hasColumn('site_banners', 'media_id_mobile')) {
                $table->unsignedBigInteger('media_id_mobile')->nullable()->after('media_id');
            }
            if (! Schema::hasColumn('site_banners', 'impressions')) {
                $table->unsignedInteger('impressions')->default(0);
            }
            if (! Schema::hasColumn('site_banners', 'clicks')) {
                $table->unsignedInteger('clicks')->default(0);
            }
            $table->index('zone_id');
        });

        // FKها — جدا چون باید قبلاً ستون‌ها موجود باشند
        Schema::table('site_banners', function (Blueprint $table) {
            try {
                $table->foreign('zone_id')->references('id')->on('site_banner_zones')->nullOnDelete();
                $table->foreign('media_id')->references('id')->on('site_media')->nullOnDelete();
                $table->foreign('media_id_mobile')->references('id')->on('site_media')->nullOnDelete();
            } catch (\Throwable $e) {
                // FKها قبلاً موجودند
            }
        });

        // Seed zone‌های پایه — قابل ویرایش توسط ادمین
        $now = now();
        $zones = [
            ['slug' => 'home_hero', 'name' => 'صفحه‌ی اصلی — Hero', 'recommended_width' => 1920, 'recommended_height' => 700, 'sort_order' => 1],
            ['slug' => 'home_secondary', 'name' => 'صفحه‌ی اصلی — بنر دوم', 'recommended_width' => 1200, 'recommended_height' => 300, 'sort_order' => 2],
            ['slug' => 'blog_hero', 'name' => 'بلاگ — Hero', 'recommended_width' => 1200, 'recommended_height' => 300, 'sort_order' => 3],
            ['slug' => 'blog_sidebar', 'name' => 'بلاگ — Sidebar', 'recommended_width' => 300, 'recommended_height' => 250, 'sort_order' => 4],
            ['slug' => 'forum_sidebar', 'name' => 'انجمن — Sidebar', 'recommended_width' => 300, 'recommended_height' => 250, 'sort_order' => 5],
            ['slug' => 'forum_top', 'name' => 'انجمن — بالای صفحه', 'recommended_width' => 1200, 'recommended_height' => 200, 'sort_order' => 6],
            ['slug' => 'services_promo', 'name' => 'خدمات — بنر تبلیغی', 'recommended_width' => 1200, 'recommended_height' => 300, 'sort_order' => 7],
            ['slug' => 'global_top', 'name' => 'سراسری — بالای همه صفحات', 'recommended_width' => 1200, 'recommended_height' => 60, 'sort_order' => 8],
        ];
        foreach ($zones as $z) {
            DB::table('site_banner_zones')->insert(array_merge($z, [
                'description' => null, 'max_count' => 0, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        // Backfill: position string → zone_id (مطابقت با slug)
        if (Schema::hasColumn('site_banners', 'position')) {
            DB::statement('
                UPDATE site_banners b
                JOIN site_banner_zones z ON z.slug = b.position
                SET b.zone_id = z.id
                WHERE b.zone_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('site_banners', function (Blueprint $table) {
            try {
                $table->dropForeign(['zone_id']);
                $table->dropForeign(['media_id']);
                $table->dropForeign(['media_id_mobile']);
            } catch (\Throwable $e) {
            }
            $cols = array_filter(['zone_id', 'media_id', 'media_id_mobile', 'impressions', 'clicks'],
                fn ($c) => Schema::hasColumn('site_banners', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
        Schema::dropIfExists('site_banner_zones');
    }
};
