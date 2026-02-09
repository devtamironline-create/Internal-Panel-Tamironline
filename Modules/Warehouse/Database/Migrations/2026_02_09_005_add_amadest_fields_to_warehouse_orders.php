<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->string('amadest_barcode')->nullable()->after('tracking_code');
            $table->string('post_tracking_code')->nullable()->after('amadest_barcode');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_orders', function (Blueprint $table) {
            $table->dropColumn(['amadest_barcode', 'post_tracking_code']);
        });
    }
};
