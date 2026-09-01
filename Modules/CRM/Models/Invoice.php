<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $table = 'crm_invoices';

    protected $fillable = [
        'wp_id',
        'invoice_code',
        'public_token',
        'order_id',
        'customer_id',
        'technician_id',
        'total_amount',
        'description',
        'items_snapshot',
        'tech_share',
        'company_share',
        'calc_type',
        'commission_percent',
        'in_wallet',
        'status',
        'collection_method',
        'issued_at',
        'paid_at',
        'superseded_at',
        'superseded_by_id',
        'created_by',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'total_amount' => 'integer',
        'items_snapshot' => 'array',
        'tech_share' => 'integer',
        'company_share' => 'integer',
        'commission_percent' => 'integer',
        'in_wallet' => 'boolean',
        'issued_at' => 'datetime',
        'paid_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    /**
     * فاکتورهای superseded به‌صورت پیش‌فرض از queryها خارج می‌شوند تا
     * در لیست‌ها/گزارش‌ها/محاسبه‌های مالی (مثل invoice_debt) نیایند.
     * برای دیدن همه از withSuperseded()/withoutGlobalScope استفاده کنید.
     */
    /**
     * آیا ستون‌های snapshotِ شرح/اقلام روی DB موجودند؟ (سازگاری با پنجرهٔ
     * دیپلوی یا مهاجرتِ ناتمام — تا نوشتن روی ستونِ ناموجود ۵۰۰ ندهد.)
     */
    public static function supportsSnapshot(): bool
    {
        // بدونِ کشِ استاتیک: صدورِ فاکتور پرتکرار نیست و کشِ استاتیک بین
        // تست‌ها/مهاجرت آلوده می‌شود.
        return \Illuminate\Support\Facades\Schema::hasColumn('crm_invoices', 'description');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active', function (Builder $q) {
            $q->whereNull($q->getModel()->getTable().'.superseded_at');
        });

        // توکنِ عمومیِ غیرقابل‌حدس برای لینکِ فاکتور — جلوگیری از IDOR
        // (نمی‌شود با تغییر invoice_code فاکتورِ دیگران را دید).
        static::creating(function (self $invoice) {
            if (empty($invoice->public_token)) {
                $invoice->public_token = static::generatePublicToken();
            }
        });

        // Push فاکتور به WP — وقتی فاکتور در پنل لاراول ساخته یا به‌روز
        // می‌شود، اگر سفارش مرتبط اجازهٔ push داشته باشد و فاکتور هنوز
        // wp_id ندارد، یک financial post جدید در WP ساخته می‌شود. اگر
        // wp_id دارد، همان post به‌روزرسانی می‌شود.
        $push = function (self $invoice) {
            if (app()->runningInConsole() && ! app()->bound('crm.wp_push.force')) {
                return;
            }
            // در طول inbound sync پاسخ ندهیم — pushInvoice که داخلش
            // pushOrder صدا می‌زند، می‌توانست status قدیمی Laravel را
            // به WP برگرداند و باعث «بسته نشدن سفارش از سمت WP» شود.
            if (app()->bound('crm.suppress_outbound_push')) {
                return;
            }
            try {
                app(\Modules\CRM\Services\WpPushService::class)->pushInvoice($invoice);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('crm.wp_push.invoice_failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        };
        static::created($push);
        static::updated($push);
    }

    /** Scope: شامل فاکتورهای superseded هم بشود (برای صفحهٔ تاریخچهٔ سفارش). */
    public function scopeWithSuperseded(Builder $q): Builder
    {
        return $q->withoutGlobalScope('active');
    }

    /** Scope: فقط فاکتورهای superseded. */
    /**
     * فاکتورِ جایگزینِ نهاییِ این فاکتورِ باطل‌شده — دنبال‌کردنِ زنجیرهٔ
     * superseded_by تا رسیدن به فاکتورِ فعال. برای ریدایرکتِ لینکِ قدیمی.
     *
     * fallback برای رکوردهای قدیمی (بدونِ اشاره‌گر): اولین فاکتورِ فعالِ
     * همان سفارش که بعد از باطل‌شدنِ این ساخته شده؛ وگرنه آخرین فاکتورِ فعال.
     */
    public function resolveReplacement(int $maxHops = 10): ?Invoice
    {
        $current = $this;

        while ($current->superseded_at !== null && $maxHops-- > 0) {
            $next = $current->superseded_by_id
                ? Invoice::withSuperseded()->find($current->superseded_by_id)
                : null;

            if (! $next) {
                $next = Invoice::where('order_id', $current->order_id)
                    ->when($current->superseded_at, fn ($q) => $q->where('created_at', '>=', $current->superseded_at))
                    ->orderBy('id')
                    ->first()
                    ?? Invoice::where('order_id', $current->order_id)->latest('id')->first();
            }

            if (! $next || $next->id === $current->id) {
                return null;
            }
            $current = $next;
        }

        return $current->superseded_at === null ? $current : null;
    }

    public function scopeOnlySuperseded(Builder $q): Builder
    {
        return $q->withoutGlobalScope('active')->whereNotNull('superseded_at');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /** آیا این فاکتور نقدی تسویه می‌شود؟ (تکنسین در محل پول گرفته) */
    public function isCashCollected(): bool
    {
        return $this->collection_method === 'cash';
    }

    /**
     * سفارشِ این فاکتور «بدونِ انجامِ کار» بسته شده است؟
     *
     * لغو / ردِ سفارش / ایاب و ذهاب وضعیت‌های پایانی‌اند که در آن‌ها کاری
     * تحویلِ مشتری نشده — اگر سفارش قبلاً تکمیل و فاکتور صادر شده بوده و
     * بعداً به یکی از این‌ها برگشته، فاکتورِ قدیمی نباید همچنان درگاهِ
     * پرداخت باز نگه دارد. (خودِ «انجام کار» طبیعتاً مستثناست.)
     */
    public function orderClosedWithoutWork(): bool
    {
        $status = $this->order?->status;

        return $status instanceof \Modules\CRM\Enums\OrderStatus
            && $status->isFinal()
            && $status !== \Modules\CRM\Enums\OrderStatus::Completed;
    }

    /**
     * تنها مرجعِ «آیا درگاهِ آنلاین باید نشان داده شود؟» — صفحهٔ پرداخت،
     * endpoint وضعیت، رسیدِ عمومی و اپِ مشتری همه از همین می‌خوانند.
     * فاکتورِ نقدی (انتخابِ تکنسین هنگام تکمیل) درگاه ندارد.
     */
    public function isPayableOnline(): bool
    {
        return $this->superseded_at === null
            && ! in_array($this->status, ['paid', 'cancelled'], true)
            && (int) $this->total_amount > 0
            && ! $this->isCashCollected()
            && ! $this->orderClosedWithoutWork();
    }

    /** چرا درگاه بسته است؟ null یعنی قابلِ پرداخت است. متنِ آمادهٔ نمایش. */
    public function notPayableReason(): ?string
    {
        return match (true) {
            $this->status === 'paid' => 'این فاکتور قبلاً پرداخت شده است.',
            $this->status === 'cancelled' => 'این فاکتور لغو شده است.',
            $this->superseded_at !== null => 'این فاکتور با نسخهٔ جدیدتری جایگزین شده است.',
            $this->isCashCollected() => 'تسویهٔ این فاکتور نقدی و در محل انجام می‌شود.',
            (int) $this->total_amount <= 0 => 'این فاکتور مبلغی برای پرداخت ندارد.',
            $this->orderClosedWithoutWork() => 'این سفارش بسته شده است و پرداخت آنلاین ندارد.',
            default => null,
        };
    }

    /** برچسبِ فارسیِ روشِ دریافت — null یعنی قدیمی/نامشخص. */
    public function collectionMethodLabel(): ?string
    {
        return match ($this->collection_method) {
            'cash' => 'نقدی (دریافت در محل)',
            'online' => 'اعتباری (پرداخت آنلاین)',
            default => null,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'پیش‌نویس',
            'issued' => 'صادر شده',
            'paid' => 'پرداخت شده',
            'cancelled' => 'لغو شده',
            default => $this->status,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'paid' => 'bg-green-100 text-green-800',
            'issued' => 'bg-blue-100 text-blue-800',
            'cancelled' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Generate invoice_code. Format: INV-YYMM-NNNNN (monthly sequence).
     */
    public static function generateInvoiceCode(): string
    {
        $prefix = 'INV-'.date('ym').'-';
        // withoutGlobalScope تا فاکتورهای superseded هم شمرده شوند —
        // وگرنه ممکن است کد قبلی (که superseded شده) تکرار شود.
        $last = static::withoutGlobalScope('active')
            ->where('invoice_code', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_code');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * توکنِ تصادفیِ یکتا برای لینکِ عمومیِ فاکتور (۴۰ کاراکتر = آنتروپیِ کافی
     * تا حدس/شمارش ناممکن باشد).
     */
    public static function generatePublicToken(): string
    {
        do {
            $token = \Illuminate\Support\Str::random(40);
        } while (static::withoutGlobalScope('active')->where('public_token', $token)->exists());

        return $token;
    }

    /**
     * آدرسِ عمومیِ رسیدِ فاکتور (لینکی که با پیامک به مشتری می‌رود) — همیشه با
     * توکن، نه با invoice_code.
     *
     * اگر تنظیمِ `invoice_public_url_template` ست شده باشد (مثلاً
     * `https://app.tamironline.com/invoice/{token}`)، لینک به اپ/PWAی مشتری
     * می‌رود؛ وگرنه به صفحه‌ی رسیدِ پنل. این‌طور وقتی PWA آماده شد، فقط با یک
     * تنظیم (بدون دیپلوی) لینک سوییچ می‌شود.
     */
    public function publicUrl(): string
    {
        $template = trim((string) CrmSetting::get('invoice_public_url_template', ''));
        if ($template !== '' && str_contains($template, '{token}')) {
            return str_replace('{token}', (string) $this->public_token, $template);
        }

        return route('crm.invoice.public', $this->public_token);
    }
}
