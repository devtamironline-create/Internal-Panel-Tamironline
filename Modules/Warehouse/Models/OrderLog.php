<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLog extends Model
{
    public $timestamps = false;

    protected $table = 'warehouse_order_logs';

    protected $fillable = [
        'warehouse_order_id', 'user_id', 'action', 'message', 'meta', 'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    // Action types
    const ACTION_CREATED = 'created';
    const ACTION_STATUS_CHANGED = 'status_changed';
    const ACTION_PRINTED = 'printed';
    const ACTION_SHIPPING_REGISTERED = 'shipping_registered';
    const ACTION_SHIPPING_FAILED = 'shipping_failed';
    const ACTION_WEIGHT_VERIFIED = 'weight_verified';
    const ACTION_WEIGHT_REJECTED = 'weight_rejected';
    const ACTION_WEIGHT_FORCED = 'weight_forced';
    const ACTION_COURIER_ASSIGNED = 'courier_assigned';
    const ACTION_SCANNED_SHIPPED = 'scanned_shipped';
    const ACTION_EDITED = 'edited';
    const ACTION_SUPPLY_WAIT = 'supply_wait';
    const ACTION_TAPIN_LOCATION = 'tapin_location';
    const ACTION_RETURNED = 'returned';
    const ACTION_EXIT_SCANNED = 'exit_scanned';

    public static function actionLabels(): array
    {
        return [
            self::ACTION_CREATED => 'ایجاد سفارش',
            self::ACTION_STATUS_CHANGED => 'تغییر وضعیت',
            self::ACTION_PRINTED => 'چاپ فاکتور',
            self::ACTION_SHIPPING_REGISTERED => 'ثبت در سرویس ارسال',
            self::ACTION_SHIPPING_FAILED => 'خطای ثبت ارسال',
            self::ACTION_WEIGHT_VERIFIED => 'تایید وزن',
            self::ACTION_WEIGHT_REJECTED => 'رد وزن',
            self::ACTION_WEIGHT_FORCED => 'تایید دستی وزن',
            self::ACTION_COURIER_ASSIGNED => 'تخصیص پیک',
            self::ACTION_SCANNED_SHIPPED => 'ارسال با اسکن',
            self::ACTION_EDITED => 'ویرایش سفارش',
            self::ACTION_SUPPLY_WAIT => 'انتظار تامین',
            self::ACTION_TAPIN_LOCATION => 'تنظیم استان/شهر تاپین',
            self::ACTION_RETURNED => 'مرجوعی',
            self::ACTION_EXIT_SCANNED => 'اسکن خروج',
        ];
    }

    public static function actionIcons(): array
    {
        return [
            self::ACTION_CREATED => 'text-blue-500',
            self::ACTION_STATUS_CHANGED => 'text-indigo-500',
            self::ACTION_PRINTED => 'text-purple-500',
            self::ACTION_SHIPPING_REGISTERED => 'text-green-500',
            self::ACTION_SHIPPING_FAILED => 'text-red-500',
            self::ACTION_WEIGHT_VERIFIED => 'text-green-500',
            self::ACTION_WEIGHT_REJECTED => 'text-red-500',
            self::ACTION_WEIGHT_FORCED => 'text-amber-500',
            self::ACTION_COURIER_ASSIGNED => 'text-cyan-500',
            self::ACTION_SCANNED_SHIPPED => 'text-indigo-500',
            self::ACTION_EDITED => 'text-gray-500',
            self::ACTION_SUPPLY_WAIT => 'text-amber-500',
            self::ACTION_TAPIN_LOCATION => 'text-teal-500',
            self::ACTION_RETURNED => 'text-red-500',
            self::ACTION_EXIT_SCANNED => 'text-emerald-500',
        ];
    }

    public function getActionLabelAttribute(): string
    {
        return self::actionLabels()[$this->action] ?? $this->action;
    }

    public function getActionColorAttribute(): string
    {
        return self::actionIcons()[$this->action] ?? 'text-gray-500';
    }

    // Relations
    public function order(): BelongsTo
    {
        return $this->belongsTo(WarehouseOrder::class, 'warehouse_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ثبت لاگ برای سفارش
     */
    public static function log(WarehouseOrder|int $order, string $action, string $message, array $meta = []): self
    {
        return self::create([
            'warehouse_order_id' => $order instanceof WarehouseOrder ? $order->id : $order,
            'user_id' => auth()->id(),
            'action' => $action,
            'message' => $message,
            'meta' => !empty($meta) ? $meta : null,
        ]);
    }
}
