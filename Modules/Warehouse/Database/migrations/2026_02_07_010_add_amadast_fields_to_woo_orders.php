<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('amadast_order_id')->nullable()->after('shipping_carrier');
            $table->string('amadast_tracking_code')->nullable()->after('amadast_order_id');
            $table->string('courier_tracking_code')->nullable()->after('amadast_tracking_code');
            $table->string('courier_title')->nullable()->after('courier_tracking_code');
            $table->string('amadast_status')->nullable()->after('courier_title');
            $table->timestamp('sent_to_amadast_at')->nullable()->after('amadast_status');

            $table->index('amadast_order_id');
            $table->index('amadast_tracking_code');
        });
    }

    public function down(): void
    {
        Schema::table('woo_orders', function (Blueprint $table) {
            $table->dropIndex(['amadast_order_id']);
            $table->dropIndex(['amadast_tracking_code']);
            $table->dropColumn([
                'amadast_order_id',
                'amadast_tracking_code',
                'courier_tracking_code',
                'courier_title',
                'amadast_status',
                'sent_to_amadast_at',
            ]);
        });
    }
};
