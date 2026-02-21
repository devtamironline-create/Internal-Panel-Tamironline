<?php

namespace Modules\Warehouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Services\WooCommerceService;

class SyncStatusToWooCommerce extends Command
{
    protected $signature = 'warehouse:sync-status-to-wc';
    protected $description = 'سینک وضعیت سفارشات پنل به ووکامرس';

    public function handle(): int
    {
        if (!WooCommerceService::isStatusSyncEnabled()) {
            return self::SUCCESS;
        }

        $service = new WooCommerceService();

        if (!$service->isConfigured()) {
            return self::SUCCESS;
        }

        $map = WooCommerceService::WC_STATUS_MAP;

        // وضعیت‌هایی که قابل لغو توسط WC هستند (نباید override بشن)
        $problemWcStatuses = ['cancelled', 'refunded', 'failed'];

        // سفارشات ووکامرسی که وضعیتشون در مپینگ هست
        $orders = WarehouseOrder::whereNotNull('wc_order_id')
            ->whereIn('status', array_keys($map))
            ->whereNotNull('wc_order_data')
            ->get();

        if ($orders->isEmpty()) {
            return self::SUCCESS;
        }

        $synced = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $expectedWcStatus = $map[$order->status] ?? null;
            if (!$expectedWcStatus) {
                continue;
            }

            $currentWcStatus = $order->wc_order_data['status'] ?? null;

            // اگه وضعیت WC همونه که انتظار داریم → نیازی به سینک نیست
            if ($currentWcStatus === $expectedWcStatus) {
                continue;
            }

            // اگه WC وضعیت مشکل‌دار داره (لغو/مسترد) و پنل returned نیست → نباید override کنیم
            if (in_array($currentWcStatus, $problemWcStatuses) && $order->status !== WarehouseOrder::STATUS_RETURNED) {
                $skipped++;
                continue;
            }

            $result = $service->syncPanelStatusToWc($order, $order->status);

            if ($result['success']) {
                // آپدیت wc_order_data تا دفعه بعد دوباره سینک نکنه
                $wcData = $order->wc_order_data;
                $wcData['status'] = $expectedWcStatus;
                $order->update(['wc_order_data' => $wcData]);
                $synced++;
            } else {
                $failed++;
                Log::warning('Cron: WC status sync failed', [
                    'order' => $order->order_number,
                    'wc_order_id' => $order->wc_order_id,
                    'panel_status' => $order->status,
                    'error' => $result['message'] ?? 'unknown',
                ]);
            }
        }

        if ($synced > 0 || $failed > 0) {
            Log::info("WC status sync cron: {$synced} synced, {$failed} failed, {$skipped} skipped");
            $this->info("سینک وضعیت: {$synced} موفق | {$failed} ناموفق | {$skipped} رد شده");
        }

        return self::SUCCESS;
    }
}
