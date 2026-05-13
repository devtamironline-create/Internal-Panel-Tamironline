<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تکمیل migration درصد قبلی: تمام تکنسین‌هایی که هنوز percent=0 دارند
 * (شامل آنهایی که قبلاً wp_id گرفته بودند و migration قبلی به دلیل
 * فیلتر wp_id IS NULL آنها را skip کرد) به ۳۰٪ به‌روز می‌شوند.
 *
 * تکنسین‌هایی که با درصد >0 سینک شده‌اند دست‌نخورده باقی می‌مانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_technicians')
            ->where('percent', 0)
            ->update(['percent' => 30]);
    }

    public function down(): void
    {
        // بازگردانی نمی‌کنیم — تشخیص اینکه کدام رکوردها از قبل ۳۰ بودند
        // غیرممکن است.
    }
};
