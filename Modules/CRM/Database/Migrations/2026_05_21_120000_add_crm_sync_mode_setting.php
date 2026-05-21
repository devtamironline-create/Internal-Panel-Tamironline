<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * setting `crm_sync_mode` کنترل می‌کند کدام inbound endpoints WP→Panel
 * فعال هستند. مقادیر مجاز: full, orders_only, disabled.
 *
 * پیش‌فرض روی orders_only تنظیم می‌شود تا فقط سفارش جدید sync شود
 * و بقیه (technician/customer/financial) قطع باشند. در آینده می‌توان
 * با UI آن را تغییر داد.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now()->toDateTimeString();
        DB::table('crm_settings')->updateOrInsert(
            ['key' => 'crm_sync_mode'],
            ['value' => 'orders_only', 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('crm_settings')->where('key', 'crm_sync_mode')->delete();
    }
};
