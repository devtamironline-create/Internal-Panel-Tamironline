<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_product_id')->index(); // wc_product_id of the bundle
            $table->unsignedBigInteger('child_product_id')->index();  // wc_product_id of the component
            $table->integer('default_quantity')->default(1);
            $table->boolean('optional')->default(false);
            $table->decimal('discount', 5, 2)->default(0); // discount percentage
            $table->boolean('priced_individually')->default(false);
            $table->timestamps();

            $table->unique(['bundle_product_id', 'child_product_id'], 'bundle_child_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_product_bundle_items');
    }
};
