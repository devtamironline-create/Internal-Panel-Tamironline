<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert old enum status values to new journey statuses
        DB::table('warehouse_orders')->where('status', 'processing')->update(['status' => 'pending']);
        DB::table('warehouse_orders')->where('status', 'ready_to_ship')->update(['status' => 'packed']);
    }

    public function down(): void
    {
        DB::table('warehouse_orders')->where('status', 'pending')->update(['status' => 'processing']);
        DB::table('warehouse_orders')->where('status', 'packed')->update(['status' => 'ready_to_ship']);
    }
};
