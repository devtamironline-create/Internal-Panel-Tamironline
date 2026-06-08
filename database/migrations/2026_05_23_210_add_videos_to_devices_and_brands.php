<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن ستون `videos` (JSON nullable) به `crm_devices` و `crm_brands`.
 *
 * این ستون per-entity override برای سکشن ویدیوی صفحه‌ی دستگاه/برند است:
 *   - اگر null یا [] باشد → فرانت از template (page-sections device/brand)
 *     استفاده می‌کند (پیش‌فرض مشترک همه‌ی صفحات)
 *   - اگر آرایه‌ی غیرخالی باشد → جایگزین template می‌شود (فقط در آن صفحه)
 *
 * شکل JSON:
 *   [
 *     {title, aparat_id, youtube_id, video_url, description, poster_url},
 *     ...
 *   ]
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['crm_devices', 'crm_brands'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'videos')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->json('videos')->nullable()->after('faq');
            });
        }
    }

    public function down(): void
    {
        foreach (['crm_devices', 'crm_brands'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'videos')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('videos');
                });
            }
        }
    }
};
