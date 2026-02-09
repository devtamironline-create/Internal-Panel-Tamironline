<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wc_product_id')->unique();
            $table->string('name');
            $table->string('sku')->nullable()->index();
            $table->decimal('weight', 10, 2)->default(0);
            $table->decimal('price', 12, 0)->default(0);
            $table->string('type')->default('simple'); // simple, variable, variation
            $table->unsignedBigInteger('parent_id')->nullable(); // for variations
            $table->string('status')->default('publish');
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_products');
    }
};
