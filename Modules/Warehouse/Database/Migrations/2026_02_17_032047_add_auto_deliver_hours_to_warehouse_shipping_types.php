<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouse_shipping_types', function (Blueprint $table) {
            $table->decimal('auto_deliver_hours', 5, 2)->nullable()->after('is_priority')
                ->comment('تعداد ساعت برای تحویل خودکار بعد از ارسال (null = غیرفعال)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_shipping_types', function (Blueprint $table) {
            $table->dropColumn('auto_deliver_hours');
        });
    }
};
