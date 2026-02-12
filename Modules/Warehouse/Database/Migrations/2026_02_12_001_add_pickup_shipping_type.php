<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // اضافه کردن نوع ارسال حضوری اگه وجود نداره
        if (!DB::table('warehouse_shipping_types')->where('slug', 'pickup')->exists()) {
            DB::table('warehouse_shipping_types')->insert([
                'name' => 'حضوری',
                'slug' => 'pickup',
                'timer_minutes' => 1440,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('warehouse_shipping_types')->where('slug', 'pickup')->delete();
    }
};
