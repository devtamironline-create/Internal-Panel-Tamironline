<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->string('order_source', 20)->default('website')->after('shipping_type');
        });

        // سفارشات موجود رو بر اساس notes تشخیص بده
        \DB::table('warehouse_orders')
            ->where('notes', 'like', '%سفارش باسلام%')
            ->update(['order_source' => 'basalam']);

        \DB::table('warehouse_orders')
            ->where('notes', 'like', '%سفارش حضوری%')
            ->update(['order_source' => 'in_store']);

        // سفارشات بدون wc_order_id = دستی
        \DB::table('warehouse_orders')
            ->whereNull('wc_order_id')
            ->update(['order_source' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->dropColumn('order_source');
        });
    }
};
