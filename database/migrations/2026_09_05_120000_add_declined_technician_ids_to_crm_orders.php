<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ستونِ «تکنسین‌هایی که این سفارش را رد کرده‌اند».
 *
 * وقتی تکنسین سفارش را با علتی که «بازگشت به تخصیص خودکار» دارد رد می‌کند،
 * سفارش از او گرفته و برای تخصیصِ مجدد باز می‌شود؛ شناسهٔ او این‌جا نگه
 * داشته می‌شود تا پخشِ خودکار دوباره همان تکنسین را پیشنهاد ندهد (حلقه
 * ایجاد نشود). افزایشی و بی‌خطر.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_orders') && ! Schema::hasColumn('crm_orders', 'declined_technician_ids')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->json('declined_technician_ids')->nullable()->after('cancel_reason_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_orders') && Schema::hasColumn('crm_orders', 'declined_technician_ids')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->dropColumn('declined_technician_ids');
            });
        }
    }
};
