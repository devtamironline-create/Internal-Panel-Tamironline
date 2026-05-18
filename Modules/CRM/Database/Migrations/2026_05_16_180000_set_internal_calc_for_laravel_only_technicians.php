<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * برای تکنسین‌های ساخته‌شده در پنل لاراول که هنوز روی WP نیستند
 * (wp_id IS NULL)، حالت محاسبهٔ WP را Internal ست می‌کند تا وقتی به
 * WP push می‌شوند، WP CRM درصد را به‌عنوان «سهم شرکت از جمع کل»
 * تفسیر کند. اگر ادمین مقدار متفاوتی دستی ست کرده، دست نمی‌خورد.
 *
 * تکنسین‌هایی که از WP آمده‌اند (wp_id NOT NULL) دست‌نخورده باقی
 * می‌مانند تا منطق اصلی WP خودشان (External یا Internal با مقدار
 * متفاوت) حفظ شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_technicians')
            ->whereNull('wp_id')
            ->where(function ($q) {
                $q->whereNull('type_of_calc_tech')->orWhere('type_of_calc_tech', '');
            })
            ->update(['type_of_calc_tech' => '1']);

        // tech_per_of_all = percent برای آنهایی که هنوز ست نشده — تا با
        // Internal mode سازگار باشد (Internal از tech_per_of_all
        // به‌عنوان «درصد سهم شرکت» می‌خواند).
        DB::statement("
            UPDATE crm_technicians
            SET tech_per_of_all = percent
            WHERE wp_id IS NULL
              AND (tech_per_of_all IS NULL OR tech_per_of_all = 0)
              AND percent > 0
        ");
    }

    public function down(): void
    {
        // بازگردانی نمی‌کنیم — تشخیص مقدار قبلی برای رکوردهای مختلف
        // غیرممکن است.
    }
};
