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
            $table->boolean('is_priority')->default(false)->after('requires_dispatch')
                ->comment('آیا این نوع ارسال در بخش "ارسال‌های فوری" نمایش داده شود؟');
        });

        // تنظیم پیش‌فرض: emergency و urgent به عنوان اولویت‌دار
        DB::table('warehouse_shipping_types')
            ->whereIn('slug', ['emergency', 'urgent'])
            ->update(['is_priority' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_shipping_types', function (Blueprint $table) {
            $table->dropColumn('is_priority');
        });
    }
};
