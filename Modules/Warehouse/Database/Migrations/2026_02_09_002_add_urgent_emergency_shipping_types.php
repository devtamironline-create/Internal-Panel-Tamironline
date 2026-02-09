<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('warehouse_shipping_types')->insert([
            [
                'name' => 'فوری',
                'slug' => 'urgent',
                'timer_minutes' => 60,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'اضطراری',
                'slug' => 'emergency',
                'timer_minutes' => 420,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('warehouse_shipping_types')->whereIn('slug', ['urgent', 'emergency'])->delete();
    }
};
