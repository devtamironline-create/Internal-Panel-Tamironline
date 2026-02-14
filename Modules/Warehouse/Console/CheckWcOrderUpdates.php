<?php

namespace Modules\Warehouse\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseSetting;
use Modules\Warehouse\Notifications\WcOrderChangedNotification;

class CheckWcOrderUpdates extends Command
{
    protected $signature = 'warehouse:check-wc-updates';
    protected $description = 'بررسی تغییرات وضعیت سفارشات فعال در ووکامرس';

    public function handle(): int
    {
        $siteUrl = rtrim(WarehouseSetting::get('wc_site_url', ''), '/');
        $key = WarehouseSetting::get('wc_consumer_key');
        $secret = WarehouseSetting::get('wc_consumer_secret');

        if (empty($siteUrl) || empty($key) || empty($secret)) {
            return self::SUCCESS;
        }

        // سفارشات فعال که هنوز تحویل/مرجوع نشدن
        $activeOrders = WarehouseOrder::whereNotNull('wc_order_id')
            ->whereNotIn('status', [
                WarehouseOrder::STATUS_DELIVERED,
                WarehouseOrder::STATUS_RETURNED,
            ])
            ->get();

        if ($activeOrders->isEmpty()) {
            return self::SUCCESS;
        }

        // بررسی هر سفارش در ووکامرس (دسته‌ای ۲۰ تایی)
        $updated = 0;
        $notified = 0;

        foreach ($activeOrders->chunk(20) as $chunk) {
            $wcIds = $chunk->pluck('wc_order_id')->implode(',');

            try {
                $response = Http::timeout(30)
                    ->withBasicAuth($key, $secret)
                    ->get($siteUrl . '/wp-json/wc/v3/orders', [
                        'include' => $wcIds,
                        'per_page' => 20,
                    ]);

                if (!$response->successful()) continue;

                $wcOrders = collect($response->json())->keyBy('id');

                foreach ($chunk as $order) {
                    $wcOrder = $wcOrders->get($order->wc_order_id);
                    if (!$wcOrder) continue;

                    $wcNewStatus = $wcOrder['status'] ?? '';
                    $wcOldStatus = $order->wc_order_data['status'] ?? '';

                    // وضعیت تغییر نکرده
                    if ($wcNewStatus === $wcOldStatus) continue;

                    // آپدیت wc_order_data ذخیره شده
                    $order->update(['wc_order_data' => $wcOrder]);

                    // اگه هنوز "در حال پردازش" هست → آپدیت ساکت
                    if ($order->status === WarehouseOrder::STATUS_PENDING) {
                        // اگه تو سایت کنسل/ریفاند شد → حذف سفارش
                        if (in_array($wcNewStatus, ['cancelled', 'refunded', 'failed'])) {
                            $order->update(['status' => WarehouseOrder::STATUS_RETURNED]);
                            $order->delete(); // soft delete
                            Log::info("WC order {$order->order_number} auto-cancelled (WC status: {$wcNewStatus})");
                        }
                        $updated++;
                    } else {
                        // سفارش در مرحله دیگه‌ای هست → نوتیفیکیشن بده
                        $this->notifyWarehouseUsers($order, $wcOldStatus, $wcNewStatus);
                        $notified++;
                    }
                }
            } catch (\Exception $e) {
                Log::error('WC order update check failed', ['error' => $e->getMessage()]);
            }
        }

        if ($updated > 0 || $notified > 0) {
            Log::info("WC order updates: {$updated} auto-updated, {$notified} notifications sent");
            $this->info("آپدیت: {$updated} | نوتیفیکیشن: {$notified}");
        }

        return self::SUCCESS;
    }

    protected function notifyWarehouseUsers(WarehouseOrder $order, string $wcOldStatus, string $wcNewStatus): void
    {
        $users = User::permission('manage-warehouse')->get();

        foreach ($users as $user) {
            $user->notify(new WcOrderChangedNotification(
                $order,
                $wcOldStatus,
                $wcNewStatus,
                $order->status,
            ));
        }
    }
}
