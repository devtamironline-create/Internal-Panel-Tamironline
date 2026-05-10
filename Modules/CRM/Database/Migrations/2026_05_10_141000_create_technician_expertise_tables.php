<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * زیرساخت تخصص تکنسین برای سیستم پیشنهاد هوشمند تخصیص (فاز ۱):
 *
 * - technician_cities  : لیست شهرهای فعال هر تکنسین
 * - technician_brands  : برندهای تخصصی
 * - technician_devices : دستگاه‌های قابل انجام
 * - satisfaction_score : امتیاز رضایت ۰..۵ که ادمین دستی ست می‌کند
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_technician_cities', function (Blueprint $t) {
            $t->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
            $t->foreignId('city_id')->constrained('crm_cities')->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->primary(['technician_id', 'city_id']);
            $t->index('city_id');
        });

        Schema::create('crm_technician_brands', function (Blueprint $t) {
            $t->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
            $t->foreignId('brand_id')->constrained('crm_brands')->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->primary(['technician_id', 'brand_id']);
            $t->index('brand_id');
        });

        Schema::create('crm_technician_devices', function (Blueprint $t) {
            $t->foreignId('technician_id')->constrained('crm_technicians')->cascadeOnDelete();
            $t->foreignId('device_id')->constrained('crm_devices')->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->primary(['technician_id', 'device_id']);
            $t->index('device_id');
        });

        Schema::table('crm_technicians', function (Blueprint $t) {
            // امتیاز رضایت ۰ تا ۵، با یک رقم اعشار. ادمین در صفحهٔ
            // ویرایش تکنسین تنظیم می‌کند.
            $t->decimal('satisfaction_score', 3, 1)->nullable()->after('percent');
        });
    }

    public function down(): void
    {
        Schema::table('crm_technicians', function (Blueprint $t) {
            $t->dropColumn('satisfaction_score');
        });
        Schema::dropIfExists('crm_technician_devices');
        Schema::dropIfExists('crm_technician_brands');
        Schema::dropIfExists('crm_technician_cities');
    }
};
