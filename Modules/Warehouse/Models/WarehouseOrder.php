<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_mobile',
        'description',
        'status',
        'assigned_to',
        'created_by',
        'status_changed_at',
        'shipped_at',
        'delivered_at',
        'notes',
        'tracking_code',
    ];

    protected $casts = [
        'status_changed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    const STATUS_PROCESSING = 'processing';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY_TO_SHIP = 'ready_to_ship';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';

    public static array $statuses = [
        self::STATUS_PROCESSING,
        self::STATUS_PREPARING,
        self::STATUS_READY_TO_SHIP,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PROCESSING => 'در حال پردازش',
            self::STATUS_PREPARING => 'در حال آماده‌سازی',
            self::STATUS_READY_TO_SHIP => 'آماده ارسال',
            self::STATUS_SHIPPED => 'ارسال شده',
            self::STATUS_DELIVERED => 'تحویل داده شده',
        ];
    }

    public static function statusColors(): array
    {
        return [
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_PREPARING => 'orange',
            self::STATUS_READY_TO_SHIP => 'yellow',
            self::STATUS_SHIPPED => 'indigo',
            self::STATUS_DELIVERED => 'green',
        ];
    }

    public static function statusIcons(): array
    {
        return [
            self::STATUS_PROCESSING => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            self::STATUS_PREPARING => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>',
            self::STATUS_READY_TO_SHIP => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>',
            self::STATUS_SHIPPED => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>',
            self::STATUS_DELIVERED => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::statusColors()[$this->status] ?? 'gray';
    }

    public function getStatusIconAttribute(): string
    {
        return self::statusIcons()[$this->status] ?? '';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('order_number', 'like', "%{$search}%")
              ->orWhere('customer_name', 'like', "%{$search}%")
              ->orWhere('customer_mobile', 'like', "%{$search}%")
              ->orWhere('tracking_code', 'like', "%{$search}%");
        });
    }

    public function updateStatus(string $newStatus): self
    {
        $this->status = $newStatus;
        $this->status_changed_at = now();

        if ($newStatus === self::STATUS_SHIPPED) {
            $this->shipped_at = now();
        }
        if ($newStatus === self::STATUS_DELIVERED) {
            $this->delivered_at = now();
        }

        $this->save();
        return $this;
    }

    public static function generateOrderNumber(): string
    {
        $jalali = \Morilog\Jalali\Jalalian::now();
        $prefix = 'WH-' . $jalali->format('Ymd');
        $lastOrder = self::where('order_number', 'like', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastOrder) {
            $lastNumber = (int) substr($lastOrder->order_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public static function getStatusCounts(): array
    {
        $counts = self::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $result = [];
        foreach (self::$statuses as $status) {
            $result[$status] = $counts[$status] ?? 0;
        }

        return $result;
    }
}
