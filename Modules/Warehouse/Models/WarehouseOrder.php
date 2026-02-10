<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number', 'customer_name', 'customer_mobile', 'description',
        'status', 'shipping_type', 'assigned_to', 'created_by',
        'wc_order_id', 'wc_order_data', 'barcode',
        'total_weight', 'actual_weight', 'weight_verified', 'box_size_id',
        'timer_deadline', 'supply_deadline', 'printed_at', 'print_count', 'packed_at',
        'status_changed_at', 'shipped_at', 'delivered_at',
        'notes', 'tracking_code', 'amadest_barcode', 'post_tracking_code', 'driver_name', 'driver_phone',
    ];

    protected $casts = [
        'wc_order_data' => 'array',
        'weight_verified' => 'boolean',
        'total_weight' => 'float',
        'actual_weight' => 'float',
        'timer_deadline' => 'datetime',
        'supply_deadline' => 'datetime',
        'printed_at' => 'datetime',
        'packed_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_SUPPLY_WAIT = 'supply_wait';
    const STATUS_PREPARING = 'preparing';
    const STATUS_PACKED = 'packed';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_RETURNED = 'returned';

    public static array $statuses = [
        self::STATUS_PENDING,
        self::STATUS_SUPPLY_WAIT,
        self::STATUS_PREPARING,
        self::STATUS_PACKED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_RETURNED,
    ];

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'در حال پردازش',
            self::STATUS_SUPPLY_WAIT => 'در انتظار تامین',
            self::STATUS_PREPARING => 'در حال آماده‌سازی',
            self::STATUS_PACKED => 'آماده ارسال',
            self::STATUS_SHIPPED => 'ارسال شده',
            self::STATUS_DELIVERED => 'تحویل شده',
            self::STATUS_RETURNED => 'مرجوعی',
        ];
    }

    public static function statusColors(): array
    {
        return [
            self::STATUS_PENDING => 'blue',
            self::STATUS_SUPPLY_WAIT => 'amber',
            self::STATUS_PREPARING => 'orange',
            self::STATUS_PACKED => 'cyan',
            self::STATUS_SHIPPED => 'indigo',
            self::STATUS_DELIVERED => 'green',
            self::STATUS_RETURNED => 'red',
        ];
    }

    public static function statusIcons(): array
    {
        return [
            self::STATUS_PENDING => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            self::STATUS_SUPPLY_WAIT => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>',
            self::STATUS_PREPARING => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>',
            self::STATUS_PACKED => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>',
            self::STATUS_SHIPPED => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>',
            self::STATUS_DELIVERED => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
            self::STATUS_RETURNED => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>',
        ];
    }

    public static function nextStatus(string $current): ?string
    {
        $flow = [
            self::STATUS_PENDING => self::STATUS_PREPARING,
            self::STATUS_PREPARING => self::STATUS_PACKED,
            self::STATUS_PACKED => self::STATUS_SHIPPED,
            self::STATUS_SHIPPED => self::STATUS_DELIVERED,
        ];
        return $flow[$current] ?? null;
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

    public function getIsTimerExpiredAttribute(): bool
    {
        return $this->timer_deadline && $this->timer_deadline->isPast();
    }

    public function getTimerRemainingSecondsAttribute(): int
    {
        if (!$this->timer_deadline) return 0;
        $remaining = now()->diffInSeconds($this->timer_deadline, false);
        return max(0, (int) $remaining);
    }

    /**
     * تبدیل وزن به گرم - اگه کمتر از 100 باشه یعنی kg هست
     */
    public static function toGrams($weight): int
    {
        if (!$weight || $weight == 0) return 0;
        return (int) round($weight < 100 ? $weight * 1000 : $weight);
    }

    public function getTotalWeightGramsAttribute(): int
    {
        // محاسبه از روی آیتم‌ها اگه لود شده باشن
        if ($this->relationLoaded('items') && $this->items->count() > 0) {
            return $this->items->sum(fn($item) => self::toGrams($item->weight) * $item->quantity);
        }
        return self::toGrams($this->total_weight);
    }

    public function getActualWeightGramsAttribute(): int
    {
        return self::toGrams($this->actual_weight);
    }

    /**
     * وزن کل شامل وزن کارتن
     */
    public function getTotalWeightWithBoxGramsAttribute(): int
    {
        $itemsWeight = $this->total_weight_grams;
        $box = $this->boxSize ?? $this->recommended_box;
        $boxWeight = $box ? $box->weight : 0;
        return $itemsWeight + $boxWeight;
    }

    /**
     * پیشنهاد کارتن مناسب بر اساس ابعاد آیتم‌ها (یا وزن به عنوان فالبک)
     */
    public function getRecommendedBoxAttribute(): ?WarehouseBoxSize
    {
        if (!$this->relationLoaded('items')) return null;

        $items = $this->items->map(fn($item) => [
            'length' => $item->length,
            'width' => $item->width,
            'height' => $item->height,
            'quantity' => $item->quantity,
        ])->toArray();

        return WarehouseBoxSize::recommend($items, $this->total_weight_grams);
    }

    public function getWeightDifferencePercentAttribute(): ?float
    {
        if (!$this->total_weight || $this->total_weight == 0 || !$this->actual_weight) return null;
        return abs($this->actual_weight - $this->total_weight) / $this->total_weight * 100;
    }

    // Relations
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WarehouseOrderItem::class);
    }

    public function boxSize(): BelongsTo
    {
        return $this->belongsTo(WarehouseBoxSize::class, 'box_size_id');
    }

    public function shippingTypeRelation(): BelongsTo
    {
        return $this->belongsTo(WarehouseShippingType::class, 'shipping_type', 'slug');
    }

    // Scopes
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
              ->orWhere('tracking_code', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%");
        });
    }

    // Methods
    public function updateStatus(string $newStatus): self
    {
        $this->status = $newStatus;
        $this->status_changed_at = now();

        match ($newStatus) {
            self::STATUS_PREPARING => $this->printed_at = now(),
            self::STATUS_PACKED => $this->packed_at = now(),
            self::STATUS_SHIPPED => $this->shipped_at = now(),
            self::STATUS_DELIVERED => $this->delivered_at = now(),
            default => null,
        };

        $this->save();
        return $this;
    }

    public function setTimerFromShippingType(): void
    {
        $shippingType = WarehouseShippingType::where('slug', $this->shipping_type)->first();
        if ($shippingType) {
            $this->timer_deadline = now()->addMinutes($shippingType->timer_minutes);
            $this->save();
        }
    }

    public static function generateBarcode(): string
    {
        do {
            $barcode = 'WH' . now()->format('ymd') . strtoupper(substr(md5(uniqid()), 0, 6));
        } while (self::where('barcode', $barcode)->exists());
        return $barcode;
    }

    public static function generateOrderNumber(): string
    {
        $jalali = \Morilog\Jalali\Jalalian::now();
        $prefix = 'WH-' . $jalali->format('Ymd');
        $lastOrder = self::where('order_number', 'like', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();
        $newNumber = $lastOrder ? ((int) substr($lastOrder->order_number, -4)) + 1 : 1;
        return $prefix . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public static function getStatusCounts(): array
    {
        $counts = self::selectRaw('status, COUNT(*) as count')
            ->whereNull('deleted_at')
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
