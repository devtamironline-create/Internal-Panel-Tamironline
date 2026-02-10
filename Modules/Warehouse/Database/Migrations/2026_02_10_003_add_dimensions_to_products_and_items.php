<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ابعاد محصولات
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->decimal('length', 8, 1)->default(0)->after('weight');  // cm
            $table->decimal('width', 8, 1)->default(0)->after('length');   // cm
            $table->decimal('height', 8, 1)->default(0)->after('width');   // cm
        });

        // ابعاد آیتم‌های سفارش
        Schema::table('warehouse_order_items', function (Blueprint $table) {
            $table->decimal('length', 8, 1)->default(0)->after('weight');  // cm
            $table->decimal('width', 8, 1)->default(0)->after('length');   // cm
            $table->decimal('height', 8, 1)->default(0)->after('width');   // cm
        });

        // فیلد کارتن انتخابی برای سفارش
        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('box_size_id')->nullable()->after('weight_verified');
            $table->foreign('box_size_id')->references('id')->on('warehouse_box_sizes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->dropForeign(['box_size_id']);
            $table->dropColumn('box_size_id');
        });

        Schema::table('warehouse_order_items', function (Blueprint $table) {
            $table->dropColumn(['length', 'width', 'height']);
        });

        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->dropColumn(['length', 'width', 'height']);
        });
    }
};
