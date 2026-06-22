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
        'brand_id', 'device_id', 'technician_id', 'technician_wp_id', 'order_type',
        'source_of_truth',
        'customer_name', 'customer_mobile', 'customer_phone',
        'province_id', 'city_id', 'district_id', 'address', 'postal_code', 'address_id',
        'problem_title', 'problem_description',
        'visit_scheduled_at', 'visit_scheduled_slot',

        // وضعیت
        'status', 'cancel_reason', 'cancel_reason_id',
        'return_type', 'return_description', 'status_internal_order', 'qc_status',
        'send_technician', 'send_sms_tec', 'send_sms_customer', 'save_as_draft',
        'is_legacy_closed', 'legacy_tech_share', 'legacy_company_share',
        'is_lead', 'lead_reason_id', 'lead_notes',

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
        'is_legacy_closed' => 'boolean',
        'is_lead' => 'boolean',
        'legacy_tech_share' => 'integer',
        'legacy_company_share' => 'integer',
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

    /**
     * Self-healing برای فیلدهای aggregate مالی هنگام ذخیره.
     *
     * هدف: هر سفارشی که از این پس ذخیره می‌شود، اگر آرایه‌های piece_list/
     * buy_price_list/customer_price_list پر باشد ولی scalarهای متناظر
     * (price_customer/cost_price/total_invoice) صفر یا خالی باشد،
     * aggregateها از روی آرایه‌ها استخراج و ذخیره شود تا
     * financialSummary و InvoiceService همیشه روی مقادیر سازگار اجرا
     * شوند.
     *
     * فقط پر می‌کند و هرگز مقدار صریحاً ست‌شده را override نمی‌کند:
     * اگر price_customer > 0 از قبل دارد، دست‌نخورده می‌ماند. روی
     * رکوردهای موجود تا زمانی که save() جدیدی روی آن‌ها فراخوانی
     * نشود، اثری ندارد — پس مانده‌های دستی تکنسین‌ها امن هستند.
     */
    /**
     * مقادیر مجاز برای source_of_truth + برچسب فارسی برای UI.
     */
    public const SOURCE_OF_TRUTH_OPTIONS = [
        'auto' => 'بر اساس تنظیم تکنسین (پیش‌فرض)',
        'panel' => 'پنل لاراول اصل است',
        'crm' => 'WP CRM اصل است',
    ];

    /**
     * آیا inbound از WP برای این سفارش پذیرفته شود؟
     *
     * منطق:
     *   - panel  → نه (پنل لاراول اصل است)
     *   - crm    → بله (WP اصل است)
     *   - auto   → fallback به تنظیم تکنسین (canReceiveOrderFromWp)
     */
    public function shouldAcceptInboundFromWp(): bool
    {
        $sot = $this->source_of_truth ?: 'auto';
        if ($sot === 'panel') {
            return false;
        }
        if ($sot === 'crm') {
            return true;
        }

        $tech = $this->technician_id ? Technician::find($this->technician_id) : null;

        return $tech ? $tech->canReceiveOrderFromWp() : true;
    }

    /**
     * آیا outbound (Laravel → WP) برای این سفارش مجاز است؟
     *
     * منطق:
     *   - panel  → بله (پنل اصل است، push می‌شود)
     *   - crm    → نه (WP اصل است، نباید overwrite کنیم)
     *   - auto   → fallback به تنظیم تکنسین (canPushOrder)
     */
    public function shouldPushToWp(): bool
    {
        $sot = $this->source_of_truth ?: 'auto';
        if ($sot === 'panel') {
            return true;
        }
        if ($sot === 'crm') {
            return false;
        }

        $tech = $this->technician_id ? Technician::find($this->technician_id) : null;

        return $tech ? $tech->canPushOrder() : true;
    }

    protected static function booted(): void
    {
        static::saving(function (self $order) {
            // cost_price ← sum(buy_price_list) (در صورت خالی بودن)
            if ((int) ($order->cost_price ?? 0) === 0
                && is_array($order->buy_price_list)
                && ! empty($order->buy_price_list)) {
                $sum = array_sum(array_map(static fn ($v) => (int) $v, $order->buy_price_list));
                if ($sum > 0) {
                    $order->cost_price = $sum;
                }
            }

            // price_customer ← sum(customer_price_list) (در صورت خالی بودن)
            if ((int) ($order->price_customer ?? 0) === 0
                && is_array($order->customer_price_list)
                && ! empty($order->customer_price_list)) {
                $sum = array_sum(array_map(static fn ($v) => (int) $v, $order->customer_price_list));
                if ($sum > 0) {
                    $order->price_customer = $sum;
                }
            }

            // total_invoice ← price_customer − cost_price (در صورت خالی بودن)
            // معادل total_invoice WP CRM که مانده پس از کسر هزینه‌ها است.
            if ((int) ($order->total_invoice ?? 0) === 0
                && (int) ($order->price_customer ?? 0) > 0) {
                $order->total_invoice = max(0, (int) $order->price_customer - (int) ($order->cost_price ?? 0));
            }
        });

        // Push ساخت سفارش جدید به WP — وقتی اپراتور در پنل لاراول
        // سفارش ثبت می‌کند، باید در WP CRM هم بسازد. CREATE همیشه push
        // می‌شود (بدون توجه به order_sync_direction تکنسین) چون قانون:
        // ایجاد اولیه همیشه باید برود تا سفارش در دو سیستم وجود داشته
        // باشد. UPDATEها هستند که طبق تنظیم direction کنترل می‌شوند.
        //
        // اگر سفارش از WP inbound آمده، قبل از Order::create، wp_id ست
        // می‌شود (در SyncOrderController::upsertOne). پس روی این آلودگی
        // event create، wp_id pre-set است و pushOrderCreate بلافاصله
        // return می‌کند (early return).
        static::created(function (self $order) {
            if ($order->wp_id) {
                return;
            } // از WP inbound آمده، push لازم نیست
            if (app()->runningInConsole() && ! app()->bound('crm.wp_push.force')) {
                return; // در artisan/seedها push اتوماتیک نمی‌خواهیم
            }
            // در طول inbound sync پاسخ ندهیم تا حلقه نشود
            if (app()->bound('crm.suppress_outbound_push')) {
                return;
            }
            try {
                app(\Modules\CRM\Services\WpPushService::class)->pushOrderCreate($order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('crm.wp_push.create_failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        // Push تغییرات به WP — فقط روی update (نه create) و فقط برای
        // سفارش‌هایی که wp_id دارند (یعنی منشأ WP دارند یا قبلاً
        // sync شده‌اند). برای جلوگیری از حلقه، اگر تغییرات از خود WP
        // inbound آمده‌اند، یک flag روی request set می‌شود که اینجا
        // skip شود — به‌علاوه پلاگین خودش یک transient suppress دارد.
        static::updated(function (self $order) {
            if (! $order->wp_id) {
                return;
            }
            if (app()->runningInConsole() && ! app()->bound('crm.wp_push.force')) {
                // در artisan/queue پیش‌فرض push نمی‌کنیم تا backfillها
                // و ResolveOrphanTechnicians باعث push انبوه نشوند.
                return;
            }
            // در طول inbound sync پاسخ ندهیم تا حلقه نشود
            if (app()->bound('crm.suppress_outbound_push')) {
                return;
            }
            // فیلدهایی که اگر تغییر کنند پوش لازم است. بقیه فیلدها
            // (مثل تنظیمات داخلی) ربطی به WP ندارند.
            $relevant = [
                'status', 'technician_id', 'visit_scheduled_at',
                'description_tech', 'description_tech1', 'description_tech2',
                'piece_list', 'buy_price_list', 'customer_price_list',
                'price_customer', 'cost_price', 'total_invoice',
                'hire', 'transportation', 'discount',
                'invoice_descripotion', 'cancel_reason',
                'return_type', 'return_description', 'save_as_draft',
            ];
            foreach ($relevant as $f) {
                if ($order->wasChanged($f)) {
                    try {
                        app(\Modules\CRM\Services\WpPushService::class)->pushOrder($order);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('crm.wp_push.observer_failed', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    break;
                }
            }
        });
    }

    // ─────────────────── Relations ────────────────────────────────
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * نامِ نمایشیِ مشتری برای پنل. نامِ زنده‌ی مشتری در اولویت است تا تغییراتِ
     * بعدیِ نام/نام‌خانوادگی دیده شود (مثلاً سفارش‌های اپ که فقط نامِ کوچک را
     * snapshot کرده‌اند ولی مشتری بعداً نام خانوادگی‌اش را تکمیل کرده). اگر
     * مشتریِ متصل نامِ واقعی نداشت، به snapshotِ زمانِ ثبت برمی‌گردیم. هرگز
     * شماره موبایل برنمی‌گردد.
     */
    public function customerDisplayName(): string
    {
        return $this->customer?->composedName() ?? ($this->customer_name ?: '—');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function leadReason(): BelongsTo
    {
        return $this->belongsTo(LeadReason::class, 'lead_reason_id');
    }

    public function scopeLeads($query)
    {
        return $query->where('is_lead', true);
    }

    public function scopeRealOrders($query)
    {
        return $query->where(function ($q) {
            $q->where('is_lead', false)->orWhereNull('is_lead');
        });
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

    /**
     * منطقهٔ snapshotِ سفارش از نوع crm_cities (ردیف فرزند با parent_city_id).
     * این همان منطقه‌ای است که آدرس مشتری/اپ استفاده می‌کند و تنها سیستم
     * منطقه در پروژه است (crm_regions بازنشسته شد).
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(City::class, 'district_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * آدرس انتخاب‌شده از لیست multi-address مشتری (اپ موبایل).
     * snapshot فیلدهای province_id/city_id/address روی همین مدل به‌عنوان
     * fallback همچنان معتبر است.
     *
     * نام relation عمداً `customerAddress` است نه `address` — چون ستون
     * `address` (متن snapshot) روی همین جدول وجود دارد و Eloquent در
     * تصادم نام، ستون را برمی‌گرداند نه relation را.
     */
    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'address_id');
    }

    /**
     * ایرادات انتخاب‌شده از منوی dropdown اپ.
     */
    public function objections(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Objection::class, 'crm_order_objection', 'order_id', 'objection_id')
            ->withPivot('sort_order')
            ->orderBy('crm_order_objection.sort_order');
    }

    /**
     * نظرسنجی مشتری روی این سفارش (اگر ثبت شده باشد).
     */
    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrderReview::class);
    }

    /**
     * فاکتورهای این سفارش (معمولاً یک، ولی در بازنویسی/cancel چند فاکتور
     * ممکن است). Global scope روی Invoice فاکتورهای superseded را حذف می‌کند.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->latest('created_at');
    }

    public function adminNotes(): HasMany
    {
        return $this->hasMany(OrderAdminNote::class)->latest('created_at');
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
     *
     * مرتب‌سازی بر اساس خود order_code انجام می‌شود (نه id) — چون با
     * sync از WP رکوردهای قدیمی با id بالاتر ولی شمارهٔ کوچک‌تر وارد
     * می‌شوند و در غیر این صورت ممکن است کدِ تکراری تولید شود.
     * در صورت برخورد با duplicate (race condition بین دو ثبت هم‌زمان)
     * تا ۵ بار retry می‌شود.
     */
    public static function generateOrderCode(): string
    {
        $prefix = 'ORD-'.date('ym').'-';

        $last = static::where('order_code', 'like', $prefix.'%')
            ->orderByDesc('order_code')
            ->value('order_code');

        $next = 1;
        if ($last) {
            $tail = (int) substr($last, strlen($prefix));
            $next = $tail + 1;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $prefix.str_pad((string) ($next + $attempt), 5, '0', STR_PAD_LEFT);
            if (! static::where('order_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        // در حالت بسیار نادر همه‌ی ۵ کاندید پر بودند — یک suffix تصادفی
        // اضافه می‌کنیم تا حتماً یکتا باشد.
        return $prefix.str_pad((string) ($next + random_int(10, 999)), 5, '0', STR_PAD_LEFT);
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
     * خلاصهٔ مالی فاکتور.
     *
     * منطق سهم‌ها (سپس از تصمیم ادمین، ۲۰۲۶/۰۵):
     *   - percent = «سهم شرکت از کل مبلغ فاکتور» (مثلاً ۳۰ = ۳۰٪ شرکت)
     *   - پایهٔ محاسبه = price_customer (جمع کل) — هزینهٔ قطعات کسر نمی‌شود
     *   - tech_share    = price_customer × (100 - percent) / 100
     *   - company_share = price_customer × percent / 100
     *
     * در حالت status = transit (10)، تکنسین ۱۰۰٪ price_customer می‌گیرد.
     *
     * type_of_calc_tech == 1 (internal): همان منطق ولی با tech_per_of_all
     * به عنوان درصد شرکت (که از قبل همین معنا را داشت).
     *
     * فیلد total_invoice (= price_customer − cost_price) فقط برای نمایش
     * «مانده پس از کسر هزینه‌ها» در UI استفاده می‌شود و در محاسبهٔ سهم
     * دیگر نقشی ندارد.
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
        $costTotal = (int) ($this->cost_price ?? 0);
        $remaining = (int) ($this->total_invoice ?? 0);

        // ─── Fallbackها برای داده‌های ناقص (display-only) ───────────────
        if ($costTotal === 0 && is_array($this->buy_price_list) && ! empty($this->buy_price_list)) {
            $costTotal = (int) array_sum(array_map(static fn ($v) => (int) $v, $this->buy_price_list));
        }
        if ($customerTotal === 0 && is_array($this->customer_price_list) && ! empty($this->customer_price_list)) {
            $customerTotal = (int) array_sum(array_map(static fn ($v) => (int) $v, $this->customer_price_list));
        }
        if ($remaining === 0 && $customerTotal > 0) {
            $remaining = max(0, $customerTotal - $costTotal);
        }

        $hasData = ($customerTotal > 0) || ($costTotal > 0) || ($remaining > 0)
            || ! empty($this->piece_list);

        $tech = $this->technician;
        $percent = $tech ? (int) ($tech->percent ?? 0) : 0;
        $techPerOfAll = $tech ? (int) ($tech->tech_per_of_all ?? 0) : 0;
        $calcType = $tech ? (string) ($tech->type_of_calc_tech ?? '') : '';

        $statusValue = $this->status instanceof OrderStatus
            ? $this->status->value
            : (string) $this->status;

        // ─── سفارش‌های legacy_closed: اعداد دقیقاً از لاگ WP خوانده‌شده‌اند ─
        // CommissionCalculator اجرا نمی‌شود چون فرمول WP می‌تواند متفاوت
        // باشد (مثلاً WP × total_invoice، پنل × price_customer).
        if ($this->is_legacy_closed && $this->legacy_tech_share !== null) {
            $legacyTech = (int) $this->legacy_tech_share;
            $legacyCompany = (int) ($this->legacy_company_share ?? 0);
            $base = $legacyTech + $legacyCompany;
            $legacyPercent = $base > 0 ? (int) round(($legacyCompany / $base) * 100) : 0;

            return [
                'has_data' => $hasData,
                'customer_total' => $customerTotal,
                'cost_total' => $costTotal,
                'remaining' => $remaining,
                'tech_share' => $legacyTech,
                'company_share' => $legacyCompany,
                'percent' => $legacyPercent,
                'calc_type' => 'legacy_wp',
            ];
        }

        $techShare = 0;
        $companyShare = 0;

        if ($customerTotal > 0) {
            if ($statusValue === OrderStatus::Transit->value) {
                $techShare = $customerTotal;
                $companyShare = 0;
            } elseif ($calcType === '1' || $calcType === 'internal') {
                $companyPct = max(0, min(100, $techPerOfAll));
                $companyShare = (int) intdiv($customerTotal * $companyPct, 100);
                $techShare = max(0, $customerTotal - $companyShare);
            } else {
                $companyPct = max(0, min(100, $percent));
                $companyShare = (int) intdiv($customerTotal * $companyPct, 100);
                $techShare = max(0, $customerTotal - $companyShare);
            }
        }

        return [
            'has_data' => $hasData,
            'customer_total' => $customerTotal,
            'cost_total' => $costTotal,
            'remaining' => $remaining,
            'tech_share' => $techShare,
            'company_share' => $companyShare,
            'percent' => $percent,
            'calc_type' => $calcType !== '' ? $calcType : null,
        ];
    }
}
