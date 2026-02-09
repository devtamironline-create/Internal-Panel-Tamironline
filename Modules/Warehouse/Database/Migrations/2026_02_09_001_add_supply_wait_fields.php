<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->timestamp('supply_deadline')->nullable()->after('timer_deadline');
        });

        Schema::table('warehouse_order_items', function (Blueprint $table) {
            $table->boolean('is_unavailable')->default(false)->after('scanned_at');
            $table->timestamp('available_at')->nullable()->after('is_unavailable');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->dropColumn('supply_deadline');
        });

        Schema::table('warehouse_order_items', function (Blueprint $table) {
            $table->dropColumn(['is_unavailable', 'available_at']);
        });
    }
};
