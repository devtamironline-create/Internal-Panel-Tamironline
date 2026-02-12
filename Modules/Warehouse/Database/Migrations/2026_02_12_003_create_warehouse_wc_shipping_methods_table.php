<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_wc_shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('wc_instance_id')->comment('WC shipping method instance ID');
            $table->string('method_id', 100)->comment('e.g. flat_rate, free_shipping, local_pickup');
            $table->string('method_title', 255);
            $table->unsignedInteger('zone_id');
            $table->string('zone_name', 255);
            $table->boolean('enabled')->default(true);
            $table->integer('order_count')->default(0)->comment('How many orders use this method');
            $table->string('mapped_shipping_type', 100)->nullable()->comment('Internal shipping type slug');
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['zone_id', 'wc_instance_id']);
            $table->index('method_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_wc_shipping_methods');
    }
};
