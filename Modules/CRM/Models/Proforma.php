<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * پیش‌فاکتور (proforma / برآوردِ قیمت). قبل از انجامِ کار صادر می‌شود؛
 * مشتری با لینکِ عمومی (public_token) می‌بیند و تأیید/رد می‌کند.
 *
 * وضعیت‌ها:
 *   draft     پیش‌نویس (ساخته‌شده، هنوز برای مشتری ارسال نشده)
 *   sent      برای مشتری ارسال شده (SMS)
 *   accepted  مشتری تأیید کرده
 *   rejected  مشتری رد کرده
 *   expired   از تاریخِ اعتبار گذشته
 *   converted به فاکتورِ نهایی تبدیل شده
 */
class Proforma extends Model
{
    protected $table = 'crm_proformas';

    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'];

    protected $fillable = [
        'proforma_code',
        'public_token',
        'order_id',
        'customer_id',
        'technician_id',
        'customer_name',
        'customer_mobile',
        'device_name',
        'brand_name',
        'items',
        'subtotal',
        'discount',
        'total',
        'description',
        'valid_until',
        'status',
        'invoice_id',
        'created_by_user_id',
        'created_by_tech_id',
        'sent_at',
        'accepted_at',
    ];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'integer',
        'discount' => 'integer',
        'total' => 'integer',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $proforma) {
            if (empty($proforma->public_token)) {
                $proforma->public_token = static::generatePublicToken();
            }
            if (empty($proforma->proforma_code)) {
                $proforma->proforma_code = static::generateCode();
            }
        });
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

    public function creatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function creatorTech(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'created_by_tech_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * نرمال‌سازیِ اقلامِ ورودی + محاسبهٔ subtotal/total. ورودی آرایه‌ای از
     * {title, quantity, unit_price} است؛ خروجی با total هر ردیف پر می‌شود.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array{items: array<int, array<string, mixed>>, subtotal: int}
     */
    public static function normalizeItems(array $rawItems): array
    {
        $items = [];
        $subtotal = 0;
        foreach ($rawItems as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $unit = max(0, (int) ($row['unit_price'] ?? 0));
            $total = $qty * $unit;
            $items[] = [
                'title' => mb_substr($title, 0, 200),
                'quantity' => $qty,
                'unit_price' => $unit,
                'total' => $total,
            ];
            $subtotal += $total;
        }

        return ['items' => $items, 'subtotal' => $subtotal];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'پیش‌نویس',
            'sent' => 'ارسال‌شده',
            'accepted' => 'تأیید مشتری',
            'rejected' => 'رد مشتری',
            'expired' => 'منقضی',
            'converted' => 'تبدیل به فاکتور',
            default => $this->status,
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'accepted' => 'bg-green-100 text-green-800',
            'sent' => 'bg-blue-100 text-blue-800',
            'converted' => 'bg-purple-100 text-purple-800',
            'rejected' => 'bg-red-100 text-red-800',
            'expired' => 'bg-gray-200 text-gray-600',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /** آیا این پیش‌فاکتور هنوز قابلِ ویرایش است؟ (پس از تبدیل/تأیید قفل می‌شود) */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent', 'rejected', 'expired'], true);
    }

    /** کدِ ماهانه: PF-YYMM-NNNNN */
    public static function generateCode(): string
    {
        $prefix = 'PF-'.date('ym').'-';
        $last = static::where('proforma_code', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('proforma_code');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public static function generatePublicToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::where('public_token', $token)->exists());

        return $token;
    }

    /**
     * لینکِ عمومیِ رسیدِ پیش‌فاکتور. اگر تنظیمِ proforma_public_url_template
     * (مثلاً https://app.tamironline.com/proforma/{token}) ست باشد، به اپ/PWA
     * می‌رود؛ وگرنه به صفحهٔ رسیدِ پنل.
     */
    public function publicUrl(): string
    {
        $template = trim((string) CrmSetting::get('proforma_public_url_template', ''));
        if ($template !== '' && str_contains($template, '{token}')) {
            return str_replace('{token}', (string) $this->public_token, $template);
        }

        return route('crm.proforma.public', $this->public_token);
    }
}
