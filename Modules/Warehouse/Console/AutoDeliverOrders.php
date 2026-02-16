<?php

namespace Modules\Warehouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseShippingType;
use Carbon\Carbon;

class AutoDeliverOrders extends Command
{
    protected $signature = 'warehouse:auto-deliver';

    protected $description = 'تحویل خودکار سفارشات ارسال شده بر اساس تنظیمات نوع حمل و نقل';

    public function handle(): int
    {
        $this->info('شروع بررسی سفارشات برای تحویل خودکار...');

        try {
            // دریافت نوع‌های حمل و نقل که تحویل خودکار دارند
            $shippingTypes = WarehouseShippingType::whereNotNull('auto_deliver_hours')
                ->where('auto_deliver_hours', '>', 0)
                ->get();

            if ($shippingTypes->isEmpty()) {
                $this->info('هیچ نوع حمل و نقلی با تحویل خودکار فعال یافت نشد.');
                return self::SUCCESS;
            }

            $deliveredCount = 0;

            foreach ($shippingTypes as $type) {
                $hoursAgo = Carbon::now()->subHours($type->auto_deliver_hours);

                // پیدا کردن سفارشات ارسال شده که زمانشان گذشته
                $orders = WarehouseOrder::where('status', 'dispatched')
                    ->where('shipping_type', $type->slug)
                    ->where('dispatched_at', '<=', $hoursAgo)
                    ->get();

                foreach ($orders as $order) {
                    $order->status = 'delivered';
                    $order->delivered_at = Carbon::now();
                    $order->save();

                    $deliveredCount++;

                    Log::info("Auto-delivered order {$order->order_number}", [
                        'order_id' => $order->id,
                        'shipping_type' => $type->slug,
                        'auto_deliver_hours' => $type->auto_deliver_hours,
                        'dispatched_at' => $order->dispatched_at,
                    ]);
                }

                if ($orders->count() > 0) {
                    $this->info("✓ {$orders->count()} سفارش {$type->name} به تحویل شده منتقل شد");
                }
            }

            if ($deliveredCount > 0) {
                $this->info("✅ مجموع {$deliveredCount} سفارش به طور خودکار تحویل شد.");
                Log::info('Auto-delivery completed', ['delivered_count' => $deliveredCount]);
            } else {
                $this->info('هیچ سفارشی برای تحویل خودکار یافت نشد.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('خطا: ' . $e->getMessage());
            Log::error('Auto-delivery exception', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
