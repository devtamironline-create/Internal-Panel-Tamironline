<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * نرمالایز کردن مقادیر status در crm_technicians.
 *
 * در WP CRM، status می‌تواند یکی از این مقادیر باشد:
 *   '1', 'active', 'enable', 'enabled', 'on', 'yes', 'فعال'  → فعال
 *   '0', 'inactive', 'disable', 'disabled', 'off', 'no', 'غیرفعال' → غیرفعال
 *
 * ولی در پنل لاراول، فقط 'active' یا 'inactive' را معتبر می‌دانیم
 * (view و filter ها روی این دو مقدار strict چک می‌کنند). این مهاجرت
 * مقادیر موجود را به یکی از این دو نگاشت می‌کند.
 *
 * NULL و مقادیر ناشناس → 'active' (فرض پیش‌فرض چون رکوردهای موجود
 * در DB یعنی تکنسین قبلاً فعال بوده).
 */
return new class extends Migration
{
    public function up(): void
    {
        // فعال
        DB::table('crm_technicians')
            ->whereIn(DB::raw('LOWER(TRIM(status))'), [
                '1', 'active', 'enable', 'enabled', 'on', 'yes', 'true',
            ])
            ->update(['status' => 'active']);

        // غیرفعال
        DB::table('crm_technicians')
            ->whereIn(DB::raw('LOWER(TRIM(status))'), [
                '0', 'inactive', 'disable', 'disabled', 'off', 'no', 'false',
            ])
            ->update(['status' => 'inactive']);

        // فارسی
        DB::table('crm_technicians')->where('status', 'فعال')->update(['status' => 'active']);
        DB::table('crm_technicians')->where('status', 'غیرفعال')->update(['status' => 'inactive']);

        // null یا empty → پیش‌فرض active
        DB::table('crm_technicians')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '');
            })
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        // غیرقابل بازگشت دقیق (مقدار اصلی WP از دست رفته). بدون عمل.
    }
};
