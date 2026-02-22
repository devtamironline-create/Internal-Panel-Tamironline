<?php

namespace Modules\Warehouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseOrderItem;

class CleanupOldSyncedOrders extends Command
{
    protected $signature = 'warehouse:cleanup-old-synced
                            {--days=7 : حذف سفارشاتی که تاریخ WC آنها قدیمی‌تر از این تعداد روز است}
                            {--dry-run : فقط نمایش تعداد بدون حذف}
                            {--force : حذف بدون تأیید}';

    protected $description = 'حذف سفارشات قدیمی ووکامرس که اشتباهی سینک شده‌اند (فقط pending)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $cutoffDate = Carbon::now()->subDays($days)->toIso8601String();

        // سفارشاتی که:
        // 1. از WooCommerce سینک شدن (wc_order_id دارن)
        // 2. هنوز pending هستن (کسی روشون کار نکرده)
        // 3. تاریخ ساخت WC آنها قدیمی‌تر از cutoff هست
        $query = WarehouseOrder::whereNotNull('wc_order_id')
            ->where('status', WarehouseOrder::STATUS_PENDING)
            ->whereNotNull('wc_order_data')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(wc_order_data, '$.date_created')) < ?", [$cutoffDate]);

        $count = $query->count();

        if ($count === 0) {
            $this->info('هیچ سفارش قدیمی‌ای برای حذف پیدا نشد.');
            return self::SUCCESS;
        }

        $this->warn("تعداد {$count} سفارش قدیمی (pending + WC date > {$days} روز) پیدا شد.");

        if ($dryRun) {
            $this->info('حالت dry-run: هیچ چیزی حذف نمی‌شود.');
            // نمایش چند نمونه
            $samples = (clone $query)->limit(10)->get(['id', 'order_number', 'wc_order_id', 'customer_name', 'created_at']);
            $this->table(['ID', 'شماره سفارش', 'WC ID', 'مشتری', 'تاریخ ایجاد'], $samples->map(fn($o) => [
                $o->id, $o->order_number, $o->wc_order_id, $o->customer_name, $o->created_at,
            ])->toArray());
            return self::SUCCESS;
        }

        if (!$force && !$this->confirm("آیا از حذف {$count} سفارش مطمئنید؟")) {
            $this->info('لغو شد.');
            return self::SUCCESS;
        }

        // حذف آیتم‌های سفارشات
        $orderIds = $query->pluck('id')->toArray();
        $itemsDeleted = WarehouseOrderItem::whereIn('warehouse_order_id', $orderIds)->delete();

        // soft delete سفارشات
        $deleted = $query->delete();

        $this->info("تعداد {$deleted} سفارش و {$itemsDeleted} آیتم حذف (soft-delete) شدند.");

        return self::SUCCESS;
    }
}
