<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصویرِ Hero اختصاصیِ هر صفحهٔ ترکیبی (device×brand) — همان ساختارِ
 * ۳-اسلاتیِ hero_visual (desktop_left/desktop_right/mobile با alt) که
 * Device.hero_image دارد. nullable → خالی یعنی fallback به زنجیرهٔ فعلی
 * (الگوی دستگاه/برند/قالبِ ترکیبی) و هیچ صفحهٔ موجودی تغییر نمی‌کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_device_brand_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_device_brand_pages', 'hero_image')) {
                $table->json('hero_image')->nullable()->after('caption');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_device_brand_pages', function (Blueprint $table) {
            if (Schema::hasColumn('crm_device_brand_pages', 'hero_image')) {
                $table->dropColumn('hero_image');
            }
        });
    }
};
