<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woo_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('woo_order_id')->constrained('woo_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('woo_item_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->integer('quantity');
            $table->decimal('price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);
            $table->decimal('total_tax', 15, 2)->nullable();
            $table->json('meta_data')->nullable();
            $table->string('image_url')->nullable();

            // Internal warehouse fields
            $table->boolean('is_picked')->default(false);
            $table->string('bin_location')->nullable();
            $table->integer('picked_quantity')->default(0);
            $table->text('pick_note')->nullable();

            $table->timestamps();

            $table->index('product_id');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woo_order_items');
    }
};
