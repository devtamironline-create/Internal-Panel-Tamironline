<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * یکبار-مصرف: درصد همه تکنسین‌های ساخته‌شده در پنل لاراول (بدون wp_id)
 * را به ۳۰٪ تنظیم می‌کند. تکنسین‌های سینک‌شده از WP دست‌نخورده می‌مانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_technicians')
            ->whereNull('wp_id')
            ->update(['percent' => 30]);
    }

    public function down(): void
    {
        // عمداً rollback نمی‌کنیم — این یک data backfill است و مقدار قبلی
        // برای رکوردهای مختلف می‌توانست متفاوت باشد.
    }
};
