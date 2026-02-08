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
        'amadast_order_id',
        'amadast_tracking_code',
        'courier_tracking_code',
        'courier_title',
        'amadast_status',
        'sent_to_amadast_at',
        // Weight fields
        'package_weight',
        'product_weight_woo',
        'carton_weight',
        'weight_verified',
        'weight_difference_percent',
        // Courier info
        'courier_name',
        'courier_mobile',
        'courier_assigned_at',
        'courier_notified_to_customer',
        // Print tracking
        'print_count',
        'first_printed_at',
        'last_printed_at',
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
        'sent_to_amadast_at' => 'datetime',
        'courier_assigned_at' => 'datetime',
        'courier_notified_to_customer' => 'boolean',
        'first_printed_at' => 'datetime',
        'last_printed_at' => 'datetime',
        'weight_verified' => 'boolean',
        'package_weight' => 'decimal:2',
        'product_weight_woo' => 'decimal:2',
        'carton_weight' => 'decimal:2',
        'weight_difference_percent' => 'decimal:2',
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

    public function printLogs(): HasMany
    {
        return $this->hasMany(OrderPrintLog::class, 'woo_order_id');
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

        // Auto-send to Amadast for new processing orders
        if ($order->wasRecentlyCreated && $order->status === 'processing') {
            $order->sendToAmadast();
        }

        return $order;
    }

    /**
     * Send order to Amadast shipping service
     */
    public function sendToAmadast(): array
    {
        // Skip if already sent
        if ($this->amadast_order_id) {
            return ['success' => false, 'message' => 'این سفارش قبلاً به آمادست ارسال شده است'];
        }

        $amadastService = app(\Modules\Warehouse\Services\AmadastService::class);

        if (!$amadastService->isConfigured()) {
            return ['success' => false, 'message' => 'تنظیمات آمادست کامل نیست'];
        }

        // Get recipient city ID from mapping or default
        $recipientCityId = $this->getAmadastCityId();

        if (!$recipientCityId) {
            \Illuminate\Support\Facades\Log::warning('Amadast: Could not determine city ID for order', [
                'order_id' => $this->id,
                'city' => $this->shipping_city ?? $this->billing_city,
            ]);
            return ['success' => false, 'message' => 'شهر گیرنده در آمادست پیدا نشد'];
        }

        // Prepare products array
        $products = $this->items->map(function ($item) {
            return [
                'external_product_id' => $item->product_id ?? $item->id,
                'price' => (int) ($item->price * 10), // Convert to Rial
                'quantity' => $item->quantity,
                'weight' => 100, // Default weight per item in grams
                'title' => $item->name,
            ];
        })->toArray();

        // Calculate total weight
        $totalWeight = max(100, count($products) * 100);

        // Prepare order data
        $orderData = [
            'external_order_id' => $this->woo_order_id,
            'recipient_name' => $this->shipping_first_name
                ? trim($this->shipping_first_name . ' ' . $this->shipping_last_name)
                : $this->customer_name,
            'recipient_mobile' => $this->customer_phone ?? $this->billing_phone,
            'recipient_city_id' => $recipientCityId,
            'recipient_address' => $this->shipping_address_1
                ? $this->shippingAddress
                : $this->billingAddress,
            'recipient_postal_code' => $this->shipping_postcode ?? $this->billing_postcode ?? '0000000000',
            'weight' => $totalWeight,
            'value' => (int) ($this->total * 10), // Convert to Rial
            'product_type' => 1,
            'package_type' => 1,
            'products' => $products,
            'description' => $this->customer_note,
        ];

        $result = $amadastService->createOrder($orderData);

        if ($result['success'] ?? false) {
            $this->update([
                'amadast_order_id' => $result['data']['id'] ?? null,
                'sent_to_amadast_at' => now(),
            ]);

            \Illuminate\Support\Facades\Log::info('Order sent to Amadast', [
                'order_id' => $this->id,
                'amadast_order_id' => $result['data']['id'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * Update tracking info from Amadast
     */
    public function updateAmadastTracking(): bool
    {
        if (!$this->amadast_order_id) {
            return false;
        }

        $amadastService = app(\Modules\Warehouse\Services\AmadastService::class);
        $phone = $this->customer_phone ?? $this->billing_phone;

        if (!$phone) {
            return false;
        }

        $trackingInfo = $amadastService->getTrackingInfo($phone, $this->woo_order_id);

        if ($trackingInfo) {
            $this->update([
                'amadast_tracking_code' => $trackingInfo['amadast_tracking_code'] ?? null,
                'courier_tracking_code' => $trackingInfo['courier_tracking_code'] ?? null,
                'courier_title' => $trackingInfo['courier_title'] ?? null,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get Amadast city ID based on shipping/billing city
     */
    protected function getAmadastCityId(): ?int
    {
        // First check if we have a mapping in settings
        $cityMappings = \App\Models\Setting::get('amadast_city_mappings', []);
        $city = $this->shipping_city ?? $this->billing_city;

        if ($city && isset($cityMappings[$city])) {
            return $cityMappings[$city];
        }

        // Default city ID (Tehran = 360 usually)
        return \App\Models\Setting::get('amadast_default_city_id', 360);
    }

    /**
     * Get elapsed time since order creation
     */
    public function getElapsedTimeAttribute(): array
    {
        if (!$this->date_created) {
            return ['hours' => 0, 'minutes' => 0, 'seconds' => 0, 'formatted' => '00:00:00'];
        }

        $now = now();
        $diff = $this->date_created->diff($now);

        $totalHours = ($diff->days * 24) + $diff->h;

        return [
            'days' => $diff->days,
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'seconds' => $diff->s,
            'total_hours' => $totalHours,
            'total_minutes' => ($totalHours * 60) + $diff->i,
            'formatted' => sprintf('%02d:%02d:%02d', $totalHours, $diff->i, $diff->s),
        ];
    }

    /**
     * Set package weight and calculate difference
     */
    public function setPackageWeight(float $weight): self
    {
        $expectedWeight = $this->getExpectedWeight();
        $tolerance = (float) \App\Models\Setting::get('weight_tolerance_percent', 10);

        $differencePercent = 0;
        if ($expectedWeight > 0) {
            $differencePercent = abs(($weight - $expectedWeight) / $expectedWeight) * 100;
        }

        $verified = $differencePercent <= $tolerance;

        $this->update([
            'package_weight' => $weight,
            'weight_difference_percent' => round($differencePercent, 2),
            'weight_verified' => $verified,
        ]);

        return $this;
    }

    /**
     * Get expected weight (product weight from WooCommerce + carton weight)
     */
    public function getExpectedWeight(): float
    {
        $productWeight = (float) $this->product_weight_woo;
        $cartonWeight = (float) ($this->carton_weight ?? \App\Models\Setting::get('default_carton_weight', 0));

        return $productWeight + $cartonWeight;
    }

    /**
     * Record print action and handle duplicate warnings
     */
    public function recordPrint(int $userId, string $printType = 'invoice', ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $existingCount = OrderPrintLog::getUserPrintCount($this->id, $userId, $printType);
        $isDuplicate = $existingCount > 0;
        $managerNotified = false;

        // Create print log
        $printLog = OrderPrintLog::create([
            'woo_order_id' => $this->id,
            'user_id' => $userId,
            'print_type' => $printType,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'was_duplicate' => $isDuplicate,
            'manager_notified' => false,
        ]);

        // Update order print count
        $this->increment('print_count');

        if (!$this->first_printed_at) {
            $this->update(['first_printed_at' => now()]);
        }
        $this->update(['last_printed_at' => now(), 'is_printed' => true]);

        // Send SMS to manager on duplicate print
        if ($isDuplicate) {
            $managerMobile = \App\Models\Setting::get('manager_mobile_for_alerts');
            if ($managerMobile) {
                $this->notifyManagerOfDuplicatePrint($userId, $existingCount + 1);
                $printLog->update(['manager_notified' => true]);
                $managerNotified = true;
            }
        }

        return [
            'is_duplicate' => $isDuplicate,
            'previous_count' => $existingCount,
            'current_count' => $existingCount + 1,
            'manager_notified' => $managerNotified,
            'show_warning' => $isDuplicate && $existingCount < 2, // Show warning for 2nd and 3rd print
        ];
    }

    /**
     * Notify manager of duplicate print via SMS
     */
    protected function notifyManagerOfDuplicatePrint(int $userId, int $printCount): void
    {
        $managerMobile = \App\Models\Setting::get('manager_mobile_for_alerts');
        if (!$managerMobile) {
            return;
        }

        $user = \App\Models\User::find($userId);
        $userName = $user ? $user->full_name : 'کاربر ناشناس';

        $message = "هشدار پرینت تکراری!\n";
        $message .= "سفارش: #{$this->order_number}\n";
        $message .= "کاربر: {$userName}\n";
        $message .= "تعداد پرینت: {$printCount} بار";

        try {
            $smsService = app(\Modules\SMS\Services\KavenegarService::class);
            $smsService->send($managerMobile, $message);

            \Illuminate\Support\Facades\Log::info('Manager notified of duplicate print', [
                'order_id' => $this->id,
                'user_id' => $userId,
                'print_count' => $printCount,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to notify manager of duplicate print', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Assign courier and notify customer
     */
    public function assignCourier(string $name, string $mobile, bool $notifyCustomer = true): array
    {
        $this->update([
            'courier_name' => $name,
            'courier_mobile' => $mobile,
            'courier_assigned_at' => now(),
            'internal_status' => self::INTERNAL_SHIPPED,
            'is_shipped' => true,
        ]);

        $customerNotified = false;

        if ($notifyCustomer) {
            $customerNotified = $this->notifyCustomerOfCourier();
            $this->update(['courier_notified_to_customer' => $customerNotified]);
        }

        return [
            'success' => true,
            'customer_notified' => $customerNotified,
        ];
    }

    /**
     * Notify customer about courier assignment via SMS
     */
    public function notifyCustomerOfCourier(): bool
    {
        $customerPhone = $this->customer_phone ?? $this->billing_phone;
        if (!$customerPhone || !$this->courier_name || !$this->courier_mobile) {
            return false;
        }

        $message = "سفارش #{$this->order_number}\n";
        $message .= "سفارش شما توسط پیک ارسال شد.\n";
        $message .= "نام پیک: {$this->courier_name}\n";
        $message .= "شماره تماس پیک: {$this->courier_mobile}";

        if ($this->tracking_code) {
            $message .= "\nکد رهگیری: {$this->tracking_code}";
        }

        try {
            $smsService = app(\Modules\SMS\Services\KavenegarService::class);
            $result = $smsService->send($customerPhone, $message);

            \Illuminate\Support\Facades\Log::info('Customer notified of courier assignment', [
                'order_id' => $this->id,
                'customer_phone' => $customerPhone,
            ]);

            return $result['success'] ?? false;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to notify customer of courier', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if weight is within tolerance
     */
    public function isWeightWithinTolerance(): bool
    {
        if (!$this->package_weight || !$this->product_weight_woo) {
            return true; // Can't verify without data
        }

        $tolerance = (float) \App\Models\Setting::get('weight_tolerance_percent', 10);
        return $this->weight_difference_percent <= $tolerance;
    }
}
