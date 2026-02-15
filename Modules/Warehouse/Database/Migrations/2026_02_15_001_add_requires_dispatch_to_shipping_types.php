<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_shipping_types', function (Blueprint $table) {
            $table->boolean('requires_dispatch')->default(false)->after('is_active');
        });

        // پیک‌ها به صورت پیشفرض نیاز به ایستگاه ارسال دارن
        \DB::table('warehouse_shipping_types')
            ->whereIn('slug', ['courier', 'urgent'])
            ->update(['requires_dispatch' => true]);
    }

    public function down(): void
    {
        Schema::table('warehouse_shipping_types', function (Blueprint $table) {
            $table->dropColumn('requires_dispatch');
        });
    }
};
