<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * افزودن فلگ is_active به crm_cities.
 *
 * هدف اصلی: بعد از «تبدیل شهر به منطقه»، شهر قدیمی (مثلاً «منطقه 1»
 * که اشتباهاً به‌عنوان شهر ثبت شده بود) از فهرست dropdown ها مخفی
 * شود ولی ردیف در DB باقی بماند تا history سفارش‌ها نشکند. سفارش‌های
 * قدیمی همان لحظهٔ تبدیل به شهر+منطقهٔ جدید منتقل می‌شوند، ولی شهر
 * قدیمی صرفاً غیرفعال (نه حذف) می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_cities', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_cities', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
                $table->index('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_cities', function (Blueprint $table) {
            if (Schema::hasColumn('crm_cities', 'is_active')) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            }
        });
    }
};
