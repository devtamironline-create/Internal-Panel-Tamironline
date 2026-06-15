<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * بازنشستگیِ کاملِ سیستم قدیمیِ «منطقه» (crm_regions) — فاز نهایی.
 *
 * مهاجرتِ 2026_06_14_000002 داده‌ها را به crm_cities (district) منتقل کرد
 * و crm_regions / crm_orders.region_id / crm_technician_regions را عمداً
 * به‌عنوان fallback نگه داشت. حالا که نمایش/نوشتن/پوشش تکنسین همگی روی
 * district (crm_cities) یکپارچه شده‌اند، این بقایای قدیمی حذف می‌شوند تا
 * در کل سیستم تنها یک منبعِ منطقه (crm_cities با parent_city_id) بماند.
 *
 * ترتیب حذف به‌خاطر کلیدهای خارجی:
 *   ۱) backfill ایمنِ نهایی district_id از روی region_id (تطبیق نام)
 *   ۲) drop جدول crm_technician_regions (FK به crm_regions دارد)
 *   ۳) drop ستون crm_orders.region_id (FK به crm_regions دارد)
 *   ۴) drop جدول crm_regions
 */
return new class extends Migration
{
    public function up(): void
    {
        // ۱) backfill ایمن: اگر سفارشی هنوز district_id ندارد ولی region_id
        //    دارد، از منطقهٔ معادل در crm_cities پر کن. مهاجرتِ یکپارچه‌سازی
        //    منطقه‌ها را با همان نام در crm_cities ساخته، پس تطبیق نام دقیق
        //    کافی است. (فقط MySQL — محیط اجرای پروژه.)
        if (DB::getDriverName() === 'mysql'
            && Schema::hasTable('crm_regions')
            && Schema::hasColumn('crm_orders', 'region_id')
            && Schema::hasColumn('crm_orders', 'district_id')) {
            DB::statement('
                UPDATE crm_orders o
                JOIN crm_regions r ON r.id = o.region_id
                JOIN crm_cities d ON d.parent_city_id = r.city_id AND d.name = r.name
                SET o.district_id = d.id
                WHERE o.district_id IS NULL AND o.region_id IS NOT NULL
            ');
        }

        // ۲) pivot قدیمیِ پوشش تکنسین (داده‌اش قبلاً به crm_technician_districts رفته)
        Schema::dropIfExists('crm_technician_regions');

        // ۳) ستون region_id روی سفارش‌ها
        if (Schema::hasTable('crm_orders') && Schema::hasColumn('crm_orders', 'region_id')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('region_id');
            });
        }

        // ۴) خودِ جدول قدیمی
        Schema::dropIfExists('crm_regions');
    }

    /**
     * بازگردانیِ ساختاری (بدون داده) برای معکوس‌پذیریِ schema. داده‌های
     * قدیمیِ crm_regions قابل بازگردانی نیستند — district روی crm_cities
     * منبعِ حقیقت است.
     */
    public function down(): void
    {
        if (! Schema::hasTable('crm_regions')) {
            Schema::create('crm_regions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('city_id')->constrained('crm_cities')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('slug', 120);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['city_id', 'slug']);
                $table->index(['city_id', 'sort_order'], 'regions_city_sort_idx');
                $table->index('is_active');
            });
        }

        if (Schema::hasTable('crm_orders') && ! Schema::hasColumn('crm_orders', 'region_id')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->foreignId('region_id')->nullable()->after('city_id')
                    ->constrained('crm_regions')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('crm_technician_regions')) {
            Schema::create('crm_technician_regions', function (Blueprint $t) {
                $t->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
                $t->foreignId('region_id')->constrained('crm_regions')->cascadeOnDelete();
                $t->timestamp('created_at')->nullable();
                $t->primary(['technician_id', 'region_id']);
                $t->index('region_id');
            });
        }
    }
};
