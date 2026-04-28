<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CRM\Enums\OrderStatus;

class Order extends Model
{
    protected $table = 'crm_orders';

    protected $fillable = [
        // پایه
        'order_code', 'wp_id',
        'customer_id', 'subscription', 'introduction',
        'brand_id', 'device_id', 'technician_id', 'order_type',
        'customer_name', 'customer_mobile', 'customer_phone',
        'province_id', 'city_id', 'address', 'postal_code',
        'problem_title', 'problem_description',
        'visit_scheduled_at',

        // وضعیت
        'status', 'cancel_reason',
        'return_type', 'return_description', 'status_internal_order', 'qc_status',
        'send_technician', 'send_sms_tec', 'send_sms_customer', 'save_as_draft',

        // مالی
        'estimated_price', 'final_price', 'deposit',
        'customer_price', 'buy_price', 'price_customer', 'cost_price',
        'total_invoice', 'negative_invoice', 'price_return',
        'have_invoice', 'type_of_send_invoice',
        'invoice_email', 'invoice_paper', 'invoice_descripotion',

        // متا
        'created_by', 'assigned_at', 'completed_at', 'notes',

        // تکنسین
        'description_tech', 'description_tech1', 'description_tech2',
        'piece_list', 'customer_price_list', 'buy_price_list',
        'hire', 'transportation', 'discount',
        'device_img1', 'device_image_input',

        // happy call
        'happy_call', 'hc_customer', 'hc_customer_data', 'hc_tech', 'hc_tech_data',

        // logging
        'order_description_content', 'order_note_content', 'log_return',
        'finish_order', 'finish_order_sh',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'visit_scheduled_at' => 'datetime',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',

        // financial — همه integer
        'estimated_price' => 'integer',
        'final_price' => 'integer',
        'deposit' => 'integer',
        'customer_price' => 'integer',
        'buy_price' => 'integer',
        'price_customer' => 'integer',
        'cost_price' => 'integer',
        'total_invoice' => 'integer',
        'negative_invoice' => 'integer',
        'price_return' => 'integer',
        'hire' => 'integer',
        'transportation' => 'integer',
        'discount' => 'integer',

        // boolean flags
        'have_invoice' => 'boolean',
        'send_technician' => 'boolean',
        'send_sms_tec' => 'boolean',
        'send_sms_customer' => 'boolean',
        'save_as_draft' => 'boolean',
        'happy_call' => 'boolean',
        'hc_customer' => 'boolean',
        'hc_tech' => 'boolean',
        'finish_order' => 'boolean',
        'finish_order_sh' => 'boolean',

        // json
        'piece_list' => 'array',
        'customer_price_list' => 'array',
        'buy_price_list' => 'array',
        'hc_customer_data' => 'array',
        'hc_tech_data' => 'array',

        'wp_id' => 'integer',
        'subscription' => 'integer',
    ];

    // ─────────────────── Relations ────────────────────────────────
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->latest('created_at');
    }

    // ─────────────────── Scopes ───────────────────────────────────
    public function scopeForTechnician($query, int $technicianId)
    {
        return $query->where('technician_id', $technicianId);
    }

    public function scopeOfStatus($query, string|OrderStatus $status)
    {
        return $query->where('status', $status instanceof OrderStatus ? $status->value : $status);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function ($q) use ($term) {
            $q->where('order_code', 'like', "%{$term}%")
                ->orWhere('customer_mobile', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('problem_title', 'like', "%{$term}%");
        });
    }

    // ─────────────────── Helpers ──────────────────────────────────
    /**
     * Generate the next order_code. Format: ORD-YYMM-NNNNN (monthly sequence).
     */
    public static function generateOrderCode(): string
    {
        $prefix = 'ORD-' . date('ym') . '-';

        $last = static::where('order_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('order_code');

        $next = 1;
        if ($last) {
            $tail = (int) substr($last, strlen($prefix));
            $next = $tail + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function getItemsSubtotalAttribute(): int
    {
        return (int) $this->items->sum('total_price');
    }

    public function getBalanceDueAttribute(): int
    {
        $total = $this->final_price ?? $this->items_subtotal;

        return max(0, $total - ($this->deposit ?? 0));
    }
}
