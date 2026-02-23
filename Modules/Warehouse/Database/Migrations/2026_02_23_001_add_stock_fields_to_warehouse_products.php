<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->integer('stock_quantity')->nullable()->after('price');
            $table->string('stock_status', 20)->default('instock')->after('stock_quantity');
            $table->boolean('manage_stock')->default(false)->after('stock_status');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_products', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'stock_status', 'manage_stock']);
        });
    }
};
