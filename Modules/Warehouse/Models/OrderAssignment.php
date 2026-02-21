<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAssignment extends Model
{
    protected $table = 'warehouse_order_assignments';

    protected $fillable = [
        'warehouse_order_id',
        'user_id',
        'assigned_by',
        'strategy',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WarehouseOrder::class, 'warehouse_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * تعداد سفارشات تخصیص‌یافته به هر کاربر (فقط pending)
     */
    public static function pendingCountPerUser(): array
    {
        return self::join('warehouse_orders', 'warehouse_orders.id', '=', 'warehouse_order_assignments.warehouse_order_id')
            ->whereIn('warehouse_orders.status', [WarehouseOrder::STATUS_PENDING, WarehouseOrder::STATUS_PREPARING])
            ->whereNull('warehouse_orders.deleted_at')
            ->selectRaw('warehouse_order_assignments.user_id, COUNT(*) as count')
            ->groupBy('warehouse_order_assignments.user_id')
            ->pluck('count', 'user_id')
            ->toArray();
    }

    /**
     * سفارش‌های تخصیص‌یافته به یک کاربر (فقط pending)
     */
    public static function pendingOrderIdsForUser(int $userId): array
    {
        return self::join('warehouse_orders', 'warehouse_orders.id', '=', 'warehouse_order_assignments.warehouse_order_id')
            ->whereIn('warehouse_orders.status', [WarehouseOrder::STATUS_PENDING, WarehouseOrder::STATUS_PREPARING])
            ->whereNull('warehouse_orders.deleted_at')
            ->where('warehouse_order_assignments.user_id', $userId)
            ->pluck('warehouse_order_assignments.warehouse_order_id')
            ->toArray();
    }
}
