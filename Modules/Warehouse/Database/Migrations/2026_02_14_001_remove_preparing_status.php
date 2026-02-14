<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Move all 'preparing' orders to 'pending' (در حال پردازش)
        DB::table('warehouse_orders')->where('status', 'preparing')->update(['status' => 'pending']);
    }

    public function down(): void
    {
        // Cannot safely reverse
    }
};
