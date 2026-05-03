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

    /**
     * رویدادهای WP (لیست پیام‌ها) — accessor.
     * منبع: order_description_content — در WP به‌صورت آرایهٔ PHP-serialized
     * یا JSON ذخیره/سینک می‌شود. خروجی همیشه آرایهٔ مرتب‌شدهٔ نزولی بر
     * اساس date (مثل krsort در WP).
     *
     * هر آیتم: ['subject' => string, 'content' => string,
     *           'author' => int|string, 'date' => string, 'status' => string]
     */
    public function getWpEventsAttribute(): array
    {
        return $this->parseWpLogField($this->order_description_content);
    }

    /** یادداشت‌های WP — منبع: order_note_content */
    public function getWpNotesAttribute(): array
    {
        return $this->parseWpLogField($this->order_note_content);
    }

    /** لاگ بازگشت سفارش — منبع: log_return */
    public function getWpReturnLogsAttribute(): array
    {
        return $this->parseWpLogField($this->log_return);
    }

    /**
     * تجزیهٔ یکی از فیلدهای لاگ WP — اول json_decode، در صورت ناموفق
     * unserialize. خروجی همیشه array (در صورت خرابی، []).
     */
    protected function parseWpLogField($raw): array
    {
        if (empty($raw)) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw)) {
            return [];
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        // 1) JSON
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 2) PHP serialize (فرمت WP postmeta)
        $unserialized = @unserialize($trimmed);
        if (is_array($unserialized)) {
            return $unserialized;
        }

        return [];
    }

    /**
     * خلاصهٔ مالی فاکتور — هم‌ارز با Orders/show_order.php در WP CRM.
     *
     * در WP، سه عدد مستقل ذخیره می‌شود:
     *   - price_customer : جمع کل صورت حساب (مبلغ کلی فاکتور)
     *   - cost_price     : جمع هزینه‌ها (قیمت قطعات و ...)
     *   - total_invoice  : مانده پس از کسر هزینه‌ها (همان عددی که سهم
     *                       تکنسین/شرکت روی آن محاسبه می‌شود)
     *
     * منطق سهم‌ها (طبق WP):
     *   if (type_of_calc_tech == 1):
     *       tech_per       = 1 - (tech_per_of_all / 100)
     *       all_price_calc = total_invoice + cost_price
     *       tech_share     = all_price_calc * tech_per
     *       company_share  = all_price_calc - tech_share
     *   else:
     *       tech_share     = total_invoice * percent / 100
     *       company_share  = total_invoice - tech_share
     *
     * در حالت status = transit (10)، تکنسین ۱۰۰٪ می‌گیرد.
     *
     * @return array{
     *     has_data: bool,
     *     customer_total: int,
     *     cost_total: int,
     *     remaining: int,
     *     tech_share: int,
     *     company_share: int,
     *     percent: int,
     *     calc_type: ?string,
     * }
     */
    public function financialSummary(): array
    {
        $customerTotal = (int) ($this->price_customer ?? 0);
        $costTotal     = (int) ($this->cost_price ?? 0);
        $remaining     = (int) ($this->total_invoice ?? 0);

        $hasData = ($customerTotal > 0) || ($costTotal > 0) || ($remaining > 0)
            || ! empty($this->piece_list);

        $tech = $this->technician;
        $percent      = $tech ? (int) ($tech->percent ?? 0) : 0;
        $techPerOfAll = $tech ? (int) ($tech->tech_per_of_all ?? 0) : 0;
        $calcType     = $tech ? (string) ($tech->type_of_calc_tech ?? '') : '';

        $statusValue = $this->status instanceof OrderStatus
            ? $this->status->value
            : (string) $this->status;

        $techShare = 0;
        $companyShare = 0;

        if ($remaining > 0) {
            if ($statusValue === OrderStatus::Transit->value) {
                $techShare = $remaining;
                $companyShare = 0;
            } elseif ($calcType === '1' || $calcType === 'internal') {
                $techPer       = (100 - $techPerOfAll) / 100;
                $allPriceCalc  = $remaining + $costTotal;
                $techShare     = (int) round($allPriceCalc * $techPer);
                $companyShare  = max(0, $allPriceCalc - $techShare);
            } else {
                $techShare    = (int) intdiv($remaining * max(0, min(100, $percent)), 100);
                $companyShare = max(0, $remaining - $techShare);
            }
        }

        return [
            'has_data'       => $hasData,
            'customer_total' => $customerTotal,
            'cost_total'     => $costTotal,
            'remaining'      => $remaining,
            'tech_share'     => $techShare,
            'company_share'  => $companyShare,
            'percent'        => $percent,
            'calc_type'      => $calcType !== '' ? $calcType : null,
        ];
    }
}
