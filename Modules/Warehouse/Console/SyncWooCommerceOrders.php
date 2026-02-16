<?php

namespace Modules\Warehouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Services\WooCommerceService;

class SyncWooCommerceOrders extends Command
{
    protected $signature = 'warehouse:sync-orders
                            {--status=processing,bslm-preparation,completed : WC order statuses (comma-separated)}';

    protected $description = 'سینک خودکار سفارشات از ووکامرس (پردازش، باسلام، حضوری)';

    public function handle(): int
    {
        $statuses = $this->option('status');

        $this->info("شروع سینک سفارشات با وضعیت: {$statuses}");

        try {
            $service = new WooCommerceService();

            if ($statuses === 'any') {
                $statuses = null;
            }

            $result = $service->syncOrders($statuses);

            if ($result['success']) {
                $msg = $result['message'] ?? 'سینک انجام شد';
                $this->info($msg);
                Log::info('Auto-sync WC orders completed', $result);
            } else {
                $this->error($result['message'] ?? 'خطا در سینک');
                Log::warning('Auto-sync WC orders failed', $result);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('خطا: ' . $e->getMessage());
            Log::error('Auto-sync WC orders exception', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
