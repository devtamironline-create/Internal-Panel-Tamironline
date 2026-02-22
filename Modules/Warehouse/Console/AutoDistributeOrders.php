<?php

namespace Modules\Warehouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Warehouse\Models\StaffReadiness;
use Modules\Warehouse\Services\OrderDistributionService;

class AutoDistributeOrders extends Command
{
    protected $signature = 'warehouse:auto-distribute';

    protected $description = 'تخصیص خودکار سفارشات جدید به اپراتورهای آماده';

    public function handle(): int
    {
        // فقط اگه حداقل یه نفر آماده باشه اجرا بشه
        $readyUserIds = StaffReadiness::todayReadyUserIds();
        if (empty($readyUserIds)) {
            return self::SUCCESS;
        }

        try {
            $service = new OrderDistributionService();
            $result = $service->distribute(null);

            if ($result['assigned'] ?? 0 > 0) {
                $this->info($result['message']);
                Log::info('Auto-distribute completed', [
                    'assigned' => $result['assigned'],
                    'message' => $result['message'],
                ]);
            }

            // پاکسازی تخصیصات سفارشاتی که از pending رد شدن
            $cleaned = OrderDistributionService::cleanupCompletedAssignments();
            if ($cleaned > 0) {
                Log::info("Auto-distribute cleanup: {$cleaned} completed assignments removed");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('خطا: ' . $e->getMessage());
            Log::error('Auto-distribute exception', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
