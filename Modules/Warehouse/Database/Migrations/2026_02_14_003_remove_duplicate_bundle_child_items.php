<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * حذف آیتم‌های تکراری پکیج از سفارشات قبلی
 * ووکامرس هم پکیج رو میفرسته هم زیرمجموعه‌هاش رو جداگانه
 * آیتم‌هایی که meta_data شون _bundled_by داره باید حذف بشن
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('warehouse_orders') || !Schema::hasTable('warehouse_order_items')) {
            return;
        }

        $orders = DB::table('warehouse_orders')
            ->whereNotNull('wc_order_data')
            ->get(['id', 'wc_order_data']);

        $totalDeleted = 0;

        foreach ($orders as $order) {
            $wcData = json_decode($order->wc_order_data, true);
            if (!$wcData || empty($wcData['line_items'])) continue;

            // پیدا کردن product_id های زیرمجموعه پکیج
            $bundleChildProductIds = [];
            foreach ($wcData['line_items'] as $item) {
                foreach ($item['meta_data'] ?? [] as $meta) {
                    if (($meta['key'] ?? '') === '_bundled_by') {
                        $productId = $item['product_id'] ?? null;
                        if ($productId) {
                            $bundleChildProductIds[] = $productId;
                        }
                        break;
                    }
                }
            }

            if (empty($bundleChildProductIds)) continue;

            // حذف آیتم‌های تکراری
            $deleted = DB::table('warehouse_order_items')
                ->where('warehouse_order_id', $order->id)
                ->whereIn('wc_product_id', $bundleChildProductIds)
                ->delete();

            $totalDeleted += $deleted;
        }

        if ($totalDeleted > 0) {
            Log::info("Removed {$totalDeleted} duplicate bundle child items from existing orders.");
        }
    }

    public function down(): void
    {
        // آیتم‌های حذف شده قابل بازگشت نیستن - ری‌سینک سفارشات لازمه
    }
};
