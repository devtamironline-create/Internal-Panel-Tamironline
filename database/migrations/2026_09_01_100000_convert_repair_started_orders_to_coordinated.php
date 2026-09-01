<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * وضعیت «شروع تعمیر» (repair_started) حذف شد — همهٔ سفارش‌های موجود که در
 * این وضعیت مانده‌اند به «هماهنگ شده» (coordinated) منتقل می‌شوند.
 *
 * status_changed_at را دست نمی‌زنیم تا مهلتِ SLA همان مبنای قبلی را داشته
 * باشد؛ یک ردِ لاگ هم در crm_order_status_logs ثبت می‌شود اگر جدول باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_orders')) {
            return;
        }

        $ids = DB::table('crm_orders')
            ->where('status', 'repair_started')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('crm_orders')
            ->whereIn('id', $ids)
            ->update(['status' => 'coordinated']);

        if (Schema::hasTable('crm_order_status_logs')) {
            $now = now();
            $rows = $ids->map(fn ($id) => [
                'order_id' => $id,
                'from_status' => 'repair_started',
                'to_status' => 'coordinated',
                'note' => 'انتقالِ خودکار: وضعیت «شروع تعمیر» حذف شد.',
                'created_at' => $now,
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('crm_order_status_logs')->insert($chunk);
            }
        }
    }

    public function down(): void
    {
        // بازگشت‌پذیر نیست — «شروع تعمیر» دیگر وضعیتِ معتبری نیست.
    }
};
