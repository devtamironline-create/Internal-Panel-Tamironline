<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change status from enum to varchar to support more statuses
        DB::statement("ALTER TABLE warehouse_orders MODIFY COLUMN status VARCHAR(50) DEFAULT 'pending'");

        // Migrate old status values to new ones
        DB::table('warehouse_orders')->where('status', 'processing')->update(['status' => 'pending']);
        DB::table('warehouse_orders')->where('status', 'ready_to_ship')->update(['status' => 'packed']);

        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->string('shipping_type', 50)->nullable()->after('status');
            $table->unsignedBigInteger('wc_order_id')->nullable()->after('order_number');
            $table->json('wc_order_data')->nullable()->after('wc_order_id');
            $table->string('barcode')->nullable()->unique()->after('tracking_code');
            $table->decimal('total_weight', 10, 2)->default(0)->after('barcode');
            $table->decimal('actual_weight', 10, 2)->nullable()->after('total_weight');
            $table->boolean('weight_verified')->default(false)->after('actual_weight');
            $table->timestamp('timer_deadline')->nullable()->after('weight_verified');
            $table->timestamp('printed_at')->nullable()->after('timer_deadline');
            $table->timestamp('packed_at')->nullable()->after('printed_at');
            $table->string('driver_name')->nullable()->after('packed_at');
            $table->string('driver_phone', 20)->nullable()->after('driver_name');

            $table->index('shipping_type');
            $table->index('wc_order_id');
            $table->index('barcode');
            $table->index('timer_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->dropIndex(['shipping_type']);
            $table->dropIndex(['wc_order_id']);
            $table->dropIndex(['barcode']);
            $table->dropIndex(['timer_deadline']);

            $table->dropColumn([
                'shipping_type', 'wc_order_id', 'wc_order_data', 'barcode',
                'total_weight', 'actual_weight', 'weight_verified',
                'timer_deadline', 'printed_at', 'packed_at',
                'driver_name', 'driver_phone',
            ]);
        });

        DB::statement("ALTER TABLE warehouse_orders MODIFY COLUMN status ENUM('processing','preparing','ready_to_ship','shipped','delivered') DEFAULT 'processing'");
    }
};
