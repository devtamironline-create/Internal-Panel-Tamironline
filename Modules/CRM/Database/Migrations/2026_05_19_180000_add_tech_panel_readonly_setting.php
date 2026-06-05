<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تنظیم freeze پنل تکنسین — وقتی روشن باشد، همه تکنسین‌ها در حالت
 * فقط-خواندنی قرار می‌گیرند تا در زمان sync بزرگ، تغییری ایجاد نکنند
 * که با داده WP در حال sync تداخل کند.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->toDateTimeString();
        DB::table('crm_settings')->updateOrInsert(
            ['key' => 'tech_panel_readonly'],
            ['value' => '0', 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('crm_settings')->where('key', 'tech_panel_readonly')->delete();
    }
};
