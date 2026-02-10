<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * تبدیل وزن‌ها از کیلوگرم به گرم
     * دیتای قبلی: 2.45 (kg) → باید بشه: 2450 (g)
     */
    public function up(): void
    {
        // وزن محصولات (warehouse_products)
        // فقط مقادیری که کمتر از 100 هستن (یعنی هنوز kg هستن)
        DB::table('warehouse_products')
            ->where('weight', '>', 0)
            ->where('weight', '<', 100)
            ->update(['weight' => DB::raw('ROUND(weight * 1000)')]);

        // وزن آیتم‌های سفارش (warehouse_order_items)
        DB::table('warehouse_order_items')
            ->where('weight', '>', 0)
            ->where('weight', '<', 100)
            ->update(['weight' => DB::raw('ROUND(weight * 1000)')]);

        // وزن کل سفارشات (warehouse_orders.total_weight)
        DB::table('warehouse_orders')
            ->where('total_weight', '>', 0)
            ->where('total_weight', '<', 100)
            ->update(['total_weight' => DB::raw('ROUND(total_weight * 1000)')]);

        // وزن واقعی سفارشات (warehouse_orders.actual_weight)
        DB::table('warehouse_orders')
            ->whereNotNull('actual_weight')
            ->where('actual_weight', '>', 0)
            ->where('actual_weight', '<', 100)
            ->update(['actual_weight' => DB::raw('ROUND(actual_weight * 1000)')]);
    }

    public function down(): void
    {
        // بازگشت: تبدیل گرم به کیلوگرم
        DB::table('warehouse_products')
            ->where('weight', '>=', 100)
            ->update(['weight' => DB::raw('ROUND(weight / 1000, 3)')]);

        DB::table('warehouse_order_items')
            ->where('weight', '>=', 100)
            ->update(['weight' => DB::raw('ROUND(weight / 1000, 3)')]);

        DB::table('warehouse_orders')
            ->where('total_weight', '>=', 100)
            ->update(['total_weight' => DB::raw('ROUND(total_weight / 1000, 3)')]);

        DB::table('warehouse_orders')
            ->whereNotNull('actual_weight')
            ->where('actual_weight', '>=', 100)
            ->update(['actual_weight' => DB::raw('ROUND(actual_weight / 1000, 3)')]);
    }
};
