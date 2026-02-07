<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WooOrder extends Model
{
    protected $fillable = [
        'woo_order_id',
        'order_number',
        'status',
        'currency',
        'total',
        'subtotal',
        'total_tax',
        'shipping_total',
        'discount_total',
        'customer_id',
        'customer_email',
        'customer_phone',
        'customer_name',
        'billing_first_name',
        'billing_last_name',
        'billing_company',
        'billing_address_1',
        'billing_address_2',
        'billing_city',
        'billing_state',
        'billing_postcode',
        'billing_country',
        'billing_phone',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_company',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'payment_method',
        'payment_method_title',
        'transaction_id',
        'shipping_method',
        'customer_note',
        'meta_data',
        'coupon_lines',
        'fee_lines',
        'assigned_to',
        'internal_note',
        'internal_status',
        'is_printed',
        'is_packed',
        'is_shipped',
        'tracking_code',
        'shipping_carrier',
        'date_created',
        'date_modified',
        'date_paid',
        'date_completed',
        'last_synced_at',
    ];

    protected $casts = [
        'meta_data' => 'array',
        'coupon_lines' => 'array',
        'fee_lines' => 'array',
        'is_printed' => 'boolean',
        'is_packed' => 'boolean',
        'is_shipped' => 'boolean',
        'date_created' => 'datetime',
        'date_modified' => 'datetime',
        'date_paid' => 'datetime',
        'date_completed' => 'datetime',
        'last_synced_at' => 'datetime',
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
    ];

    // Status constants based on WooCommerce
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_ON_HOLD = 'on-hold';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_FAILED = 'failed';
    const STATUS_TRASH = 'trash';

    // Internal status constants
    const INTERNAL_NEW = 'new';
    const INTERNAL_CONFIRMED = 'confirmed';
    const INTERNAL_PICKING = 'picking';
    const INTERNAL_PACKED = 'packed';
    const INTERNAL_SHIPPED = 'shipped';
    const INTERNAL_DELIVERED = 'delivered';
    const INTERNAL_RETURNED = 'returned';

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'در انتظار پرداخت',
            self::STATUS_PROCESSING => 'در حال پردازش',
            self::STATUS_ON_HOLD => 'در انتظار',
            self::STATUS_COMPLETED => 'تکمیل شده',
            self::STATUS_CANCELLED => 'لغو شده',
            self::STATUS_REFUNDED => 'مسترد شده',
            self::STATUS_FAILED => 'ناموفق',
            self::STATUS_TRASH => 'حذف شده',
        ];
    }

    public static function getInternalStatuses(): array
    {
        return [
            self::INTERNAL_NEW => 'جدید',
            self::INTERNAL_CONFIRMED => 'تایید شده',
            self::INTERNAL_PICKING => 'در حال جمع‌آوری',
            self::INTERNAL_PACKED => 'بسته‌بندی شده',
            self::INTERNAL_SHIPPED => 'ارسال شده',
            self::INTERNAL_DELIVERED => 'تحویل داده شده',
            self::INTERNAL_RETURNED => 'مرجوعی',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(WooOrderItem::class, 'woo_order_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getInternalStatusLabelAttribute(): string
    {
        return self::getInternalStatuses()[$this->internal_status] ?? ($this->internal_status ?: 'جدید');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_PROCESSING => 'blue',
            self::STATUS_ON_HOLD => 'orange',
            self::STATUS_COMPLETED => 'green',
            self::STATUS_CANCELLED, self::STATUS_FAILED => 'red',
            self::STATUS_REFUNDED => 'purple',
            default => 'gray',
        };
    }

    public function getInternalStatusColorAttribute(): string
    {
        return match ($this->internal_status) {
            self::INTERNAL_NEW => 'blue',
            self::INTERNAL_CONFIRMED => 'indigo',
            self::INTERNAL_PICKING => 'yellow',
            self::INTERNAL_PACKED => 'orange',
            self::INTERNAL_SHIPPED => 'cyan',
            self::INTERNAL_DELIVERED => 'green',
            self::INTERNAL_RETURNED => 'red',
            default => 'gray',
        };
    }

    public function getCustomerFullNameAttribute(): string
    {
        if ($this->customer_name) {
            return $this->customer_name;
        }
        return trim($this->billing_first_name . ' ' . $this->billing_last_name) ?: 'مهمان';
    }

    public function getBillingAddressAttribute(): string
    {
        $parts = array_filter([
            $this->billing_address_1,
            $this->billing_address_2,
            $this->billing_city,
            $this->billing_state,
            $this->billing_postcode,
        ]);
        return implode('، ', $parts);
    }

    public function getShippingAddressAttribute(): string
    {
        $parts = array_filter([
            $this->shipping_address_1,
            $this->shipping_address_2,
            $this->shipping_city,
            $this->shipping_state,
            $this->shipping_postcode,
        ]);
        return implode('، ', $parts);
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total) . ' ' . ($this->currency === 'IRR' ? 'ریال' : 'تومان');
    }

    // Scopes
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeInternalStatus($query, string $status)
    {
        return $query->where('internal_status', $status);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeNotShipped($query)
    {
        return $query->where('is_shipped', false);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('order_number', 'like', "%{$search}%")
              ->orWhere('woo_order_id', 'like', "%{$search}%")
              ->orWhere('customer_name', 'like', "%{$search}%")
              ->orWhere('customer_phone', 'like', "%{$search}%")
              ->orWhere('customer_email', 'like', "%{$search}%")
              ->orWhere('billing_phone', 'like', "%{$search}%")
              ->orWhere('tracking_code', 'like', "%{$search}%");
        });
    }

    public function scopeDateRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where('date_created', '>=', $from);
        }
        if ($to) {
            $query->where('date_created', '<=', $to);
        }
        return $query;
    }

    // Methods
    public function markAsPrinted(): self
    {
        $this->update(['is_printed' => true]);
        return $this;
    }

    public function markAsPacked(): self
    {
        $this->update([
            'is_packed' => true,
            'internal_status' => self::INTERNAL_PACKED,
        ]);
        return $this;
    }

    public function markAsShipped(string $trackingCode, ?string $shippingCarrier = null): self
    {
        $this->update([
            'is_shipped' => true,
            'tracking_code' => $trackingCode,
            'shipping_carrier' => $shippingCarrier,
            'internal_status' => self::INTERNAL_SHIPPED,
        ]);
        return $this;
    }

    public function assignTo(int $userId): self
    {
        $this->update(['assigned_to' => $userId]);
        return $this;
    }

    public function updateInternalStatus(string $status): self
    {
        $this->update(['internal_status' => $status]);
        return $this;
    }

    public static function createFromWooCommerce(array $data): self
    {
        $billing = $data['billing'] ?? [];
        $shipping = $data['shipping'] ?? [];

        $order = self::updateOrCreate(
            ['woo_order_id' => $data['id']],
            [
                'order_number' => $data['number'] ?? $data['id'],
                'status' => $data['status'],
                'currency' => $data['currency'] ?? 'IRR',
                'total' => $data['total'] ?? 0,
                'subtotal' => $data['subtotal'] ?? null,
                'total_tax' => $data['total_tax'] ?? null,
                'shipping_total' => $data['shipping_total'] ?? null,
                'discount_total' => $data['discount_total'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_email' => $billing['email'] ?? null,
                'customer_phone' => $billing['phone'] ?? null,
                'customer_name' => trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '')),
                'billing_first_name' => $billing['first_name'] ?? null,
                'billing_last_name' => $billing['last_name'] ?? null,
                'billing_company' => $billing['company'] ?? null,
                'billing_address_1' => $billing['address_1'] ?? null,
                'billing_address_2' => $billing['address_2'] ?? null,
                'billing_city' => $billing['city'] ?? null,
                'billing_state' => $billing['state'] ?? null,
                'billing_postcode' => $billing['postcode'] ?? null,
                'billing_country' => $billing['country'] ?? null,
                'billing_phone' => $billing['phone'] ?? null,
                'shipping_first_name' => $shipping['first_name'] ?? null,
                'shipping_last_name' => $shipping['last_name'] ?? null,
                'shipping_company' => $shipping['company'] ?? null,
                'shipping_address_1' => $shipping['address_1'] ?? null,
                'shipping_address_2' => $shipping['address_2'] ?? null,
                'shipping_city' => $shipping['city'] ?? null,
                'shipping_state' => $shipping['state'] ?? null,
                'shipping_postcode' => $shipping['postcode'] ?? null,
                'shipping_country' => $shipping['country'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_method_title' => $data['payment_method_title'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'meta_data' => $data['meta_data'] ?? null,
                'coupon_lines' => $data['coupon_lines'] ?? null,
                'fee_lines' => $data['fee_lines'] ?? null,
                'date_created' => isset($data['date_created']) ? \Carbon\Carbon::parse($data['date_created']) : null,
                'date_modified' => isset($data['date_modified']) ? \Carbon\Carbon::parse($data['date_modified']) : null,
                'date_paid' => isset($data['date_paid']) ? \Carbon\Carbon::parse($data['date_paid']) : null,
                'date_completed' => isset($data['date_completed']) ? \Carbon\Carbon::parse($data['date_completed']) : null,
                'last_synced_at' => now(),
            ]
        );

        // Sync order items
        if (isset($data['line_items']) && is_array($data['line_items'])) {
            $existingItemIds = [];
            foreach ($data['line_items'] as $item) {
                $orderItem = WooOrderItem::updateOrCreate(
                    [
                        'woo_order_id' => $order->id,
                        'woo_item_id' => $item['id'],
                    ],
                    [
                        'product_id' => $item['product_id'] ?? null,
                        'variation_id' => $item['variation_id'] ?? null,
                        'name' => $item['name'],
                        'sku' => $item['sku'] ?? null,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'] ?? 0,
                        'subtotal' => $item['subtotal'] ?? 0,
                        'total' => $item['total'] ?? 0,
                        'total_tax' => $item['total_tax'] ?? null,
                        'meta_data' => $item['meta_data'] ?? null,
                        'image_url' => $item['image']['src'] ?? null,
                    ]
                );
                $existingItemIds[] = $orderItem->id;
            }
            // Remove items that no longer exist
            $order->items()->whereNotIn('id', $existingItemIds)->delete();
        }

        return $order;
    }
}
