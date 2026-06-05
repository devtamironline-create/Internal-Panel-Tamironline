<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون `hero_image` (JSON nullable) به `crm_devices` و `crm_brands`
 * برای per-entity override تصویر hero روی صفحات الگوی device/brand.
 *
 * شکل JSON همانند فیلد responsive_image در page-sections:
 *   {
 *     "desktop": {"url": "...", "alt": "..."},
 *     "mobile":  {"url": "...", "alt": "..."}
 *   }
 *
 * اگر null یا خالی باشد، فرانت از template.hero.image استفاده می‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_devices', 'crm_brands'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'hero_image')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->json('hero_image')->nullable()->after('videos');
            });
        }
    }

    public function down(): void
    {
        foreach (['crm_devices', 'crm_brands'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'hero_image')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('hero_image');
                });
            }
        }
    }
};
