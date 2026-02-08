<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_order_id')->constrained('warehouse_orders')->cascadeOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->string('product_barcode')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('weight', 10, 2)->default(0);
            $table->decimal('price', 12, 0)->default(0);
            $table->unsignedBigInteger('wc_product_id')->nullable();
            $table->boolean('scanned')->default(false);
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->index('warehouse_order_id');
            $table->index('product_barcode');
            $table->index('wc_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_order_items');
    }
};
