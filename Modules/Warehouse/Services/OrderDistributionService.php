<?php

namespace Modules\Warehouse\Services;

use Modules\Warehouse\Models\OrderAssignment;
use Modules\Warehouse\Models\StaffReadiness;
use Modules\Warehouse\Models\WarehouseOrder;
use Modules\Warehouse\Models\WarehouseSetting;

class OrderDistributionService
{
    const STRATEGY_ROUND_ROBIN = 'round_robin';       // نوبتی
    const STRATEGY_LEAST_ORDERS = 'least_orders';     // کمترین سفارش
    const STRATEGY_SHIPPING_TYPE = 'shipping_type';   // بر اساس نوع ارسال

    public static array $strategies = [
        self::STRATEGY_ROUND_ROBIN => 'نوبتی (چرخشی)',
        self::STRATEGY_LEAST_ORDERS => 'کمترین سفارش فعال',
        self::STRATEGY_SHIPPING_TYPE => 'بر اساس نوع ارسال',
    ];

    /**
     * تقسیم سفارشات تخصیص‌نشده بین مسئولین آماده
     */
    public function distribute(?int $assignedBy = null): array
    {
        $readyUserIds = StaffReadiness::todayReadyUserIds();

        if (empty($readyUserIds)) {
            return ['success' => false, 'message' => 'هیچ مسئول انباری امروز آماده نیست.'];
        }

        // سفارشات pending که هنوز تخصیص نشدن
        $unassignedOrders = WarehouseOrder::byStatus(WarehouseOrder::STATUS_PENDING)
            ->whereDoesntHave('assignment')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($unassignedOrders->isEmpty()) {
            return ['success' => true, 'message' => 'سفارش تخصیص‌نشده‌ای وجود ندارد.', 'assigned' => 0];
        }

        $strategy = WarehouseSetting::get('distribution_strategy', self::STRATEGY_ROUND_ROBIN);

        $assignments = match ($strategy) {
            self::STRATEGY_LEAST_ORDERS => $this->distributeLeastOrders($unassignedOrders, $readyUserIds),
            self::STRATEGY_SHIPPING_TYPE => $this->distributeByShippingType($unassignedOrders, $readyUserIds),
            default => $this->distributeRoundRobin($unassignedOrders, $readyUserIds),
        };

        // ذخیره تخصیصات
        $count = 0;
        foreach ($assignments as $orderId => $userId) {
            OrderAssignment::create([
                'warehouse_order_id' => $orderId,
                'user_id' => $userId,
                'assigned_by' => $assignedBy,
                'strategy' => $strategy,
            ]);

            // آپدیت فیلد assigned_to در سفارش هم
            WarehouseOrder::where('id', $orderId)->update(['assigned_to' => $userId]);

            $count++;
        }

        return [
            'success' => true,
            'message' => "{$count} سفارش بین " . count($readyUserIds) . " مسئول تقسیم شد.",
            'assigned' => $count,
        ];
    }

    /**
     * الگوریتم نوبتی (Round Robin)
     * سفارشات رو به ترتیب بین مسئولین تقسیم می‌کنه
     */
    private function distributeRoundRobin($orders, array $userIds): array
    {
        $assignments = [];
        $userCount = count($userIds);
        $index = $this->getLastRoundRobinIndex($userIds);

        foreach ($orders as $order) {
            $index = ($index + 1) % $userCount;
            $assignments[$order->id] = $userIds[$index];
        }

        // ذخیره آخرین index برای دفعه بعد
        WarehouseSetting::set('distribution_last_index', $index);

        return $assignments;
    }

    /**
     * الگوریتم کمترین سفارش
     * هر سفارش رو به کسی میده که کمترین سفارش فعال رو داره
     */
    private function distributeLeastOrders($orders, array $userIds): array
    {
        $assignments = [];
        $currentCounts = OrderAssignment::pendingCountPerUser();

        // مقداردهی اولیه برای کاربران بدون سفارش
        foreach ($userIds as $uid) {
            if (!isset($currentCounts[$uid])) {
                $currentCounts[$uid] = 0;
            }
        }

        foreach ($orders as $order) {
            // فقط کاربران آماده رو فیلتر کن
            $eligible = array_intersect_key($currentCounts, array_flip($userIds));

            // کسی که کمترین سفارش رو داره
            $minUserId = array_keys($eligible, min($eligible))[0];
            $assignments[$order->id] = $minUserId;
            $currentCounts[$minUserId]++;
        }

        return $assignments;
    }

    /**
     * الگوریتم بر اساس نوع ارسال
     * هر مسئول مسئول نوع‌های ارسال خاصی میشه
     * اگه mapping تعریف نشده، فالبک به round_robin
     */
    private function distributeByShippingType($orders, array $userIds): array
    {
        $mapping = json_decode(WarehouseSetting::get('distribution_shipping_map', '{}'), true) ?: [];

        $assignments = [];
        $fallbackIndex = 0;
        $userCount = count($userIds);

        foreach ($orders as $order) {
            $shippingSlug = $order->shipping_type;

            // اگه mapping برای این نوع ارسال وجود داره
            if (isset($mapping[$shippingSlug])) {
                $mappedUserIds = array_intersect($mapping[$shippingSlug], $userIds);
                if (!empty($mappedUserIds)) {
                    // بین مسئولین مپ‌شده، کمترین سفارش
                    $counts = OrderAssignment::pendingCountPerUser();
                    $minCount = PHP_INT_MAX;
                    $selectedUser = reset($mappedUserIds);
                    foreach ($mappedUserIds as $uid) {
                        $c = $counts[$uid] ?? 0;
                        if ($c < $minCount) {
                            $minCount = $c;
                            $selectedUser = $uid;
                        }
                    }
                    $assignments[$order->id] = $selectedUser;
                    continue;
                }
            }

            // فالبک: round robin
            $fallbackIndex = ($fallbackIndex + 1) % $userCount;
            $assignments[$order->id] = $userIds[$fallbackIndex];
        }

        return $assignments;
    }

    /**
     * آخرین index چرخشی رو برگردون
     */
    private function getLastRoundRobinIndex(array $userIds): int
    {
        $lastIndex = (int) WarehouseSetting::get('distribution_last_index', -1);

        // اگه index از حد کاربران بیشتر شده، ریست کن
        if ($lastIndex >= count($userIds)) {
            $lastIndex = -1;
        }

        return $lastIndex;
    }

    /**
     * حذف تخصیصات سفارشاتی که از pending رد شدن
     */
    public static function cleanupCompletedAssignments(): int
    {
        return OrderAssignment::whereHas('order', function ($q) {
            $q->whereNotIn('status', [WarehouseOrder::STATUS_PENDING, WarehouseOrder::STATUS_PREPARING]);
        })->delete();
    }
}
