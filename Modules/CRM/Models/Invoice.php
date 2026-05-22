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
        'order_id',
        'customer_id',
        'technician_id',
        'total_amount',
        'tech_share',
        'company_share',
        'calc_type',
        'commission_percent',
        'in_wallet',
        'status',
        'issued_at',
        'paid_at',
        'superseded_at',
        'created_by',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'total_amount' => 'integer',
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
    protected static function booted(): void
    {
        static::addGlobalScope('active', function (Builder $q) {
            $q->whereNull($q->getModel()->getTable() . '.superseded_at');
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
            if (app()->bound('crm.suppress_outbound_push')) return;
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
        $prefix = 'INV-' . date('ym') . '-';
        // withoutGlobalScope تا فاکتورهای superseded هم شمرده شوند —
        // وگرنه ممکن است کد قبلی (که superseded شده) تکرار شود.
        $last = static::withoutGlobalScope('active')
            ->where('invoice_code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_code');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
