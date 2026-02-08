<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert removed statuses to their nearest equivalent in new flow
        // printed -> preparing (was between preparing and packing, now preparing covers this)
        // packing -> preparing (barcode scan will move them to packed)
        DB::table('warehouse_orders')->where('status', 'printed')->update(['status' => 'preparing']);
        DB::table('warehouse_orders')->where('status', 'packing')->update(['status' => 'preparing']);
    }

    public function down(): void
    {
        // Cannot safely reverse since we don't know which were originally printed vs packing
    }
};
