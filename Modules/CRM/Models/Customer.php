<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * مشتری CRM + هویت لاگین سایت/اپ.
 *
 * این جدول هم محل ذخیره‌ی داده‌های CRM مشتری است (سفارش، فاکتور، یادداشت)
 * و هم مدل Authenticatable برای ورود از طریق OTP. ادمین/staff همچنان از
 * App\Models\User استفاده می‌کنند؛ تکنسین‌ها از Modules\CRM\Models\Technician.
 *
 * شماره اشتراک (subscription) همان فرمول قدیمی: wp_id + 10000 (یا id + 10000).
 */
class Customer extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    // حذفِ نرمِ حساب (درخواستِ خودِ کاربر از اپ) — ادمین رکورد را می‌بیند و
    // می‌تواند بازگرداند؛ سفارش‌ها/فاکتورها دست نمی‌خورند.
    use SoftDeletes;

    protected $table = 'crm_customers';

    /** آفست شماره اشتراک نمایشی نسبت به id (مثل WP). */
    public const SUBSCRIPTION_OFFSET = 10000;

    protected $fillable = [
        'wp_id',
        'mobile',
        'mobile_verified_at',
        'email',
        'email_verified_at',
        'first_name',
        'last_name',
        'phone',
        'password',
        'avatar',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'notes',
        'is_blocked',
        'block_reason',
        'blocked_by',
        'blocked_at',
        'bale_user_id',
        'wallet_balance',
        'referred_by',
        'referral_rewarded_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'wp_id' => 'integer',
            'mobile_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
            'blocked_at' => 'datetime',
            'wallet_balance' => 'integer',
            'referred_by' => 'integer',
            'referral_rewarded_at' => 'datetime',
        ];
    }

    // ─── Wallet & referral ────────────────────────────────────────

    /** کدِ معرفِ این مشتری = ۶ رقمِ آخرِ موبایلِ او (بدونِ ستونِ جدا). */
    public function referralCode(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->mobile);

        return strlen((string) $digits) >= 6 ? substr($digits, -6) : ($digits ?: null);
    }

    /**
     * یافتنِ معرف از روی «کدِ معرف» (۶ رقمِ آخرِ موبایل). ورودی می‌تواند کدِ
     * ۶رقمی یا کلِ موبایل باشد. چون ۶ رقمِ آخر یکتا نیست، فقط وقتی دقیقاً یک
     * مشتریِ فعال بخورد معرف برگردانده می‌شود؛ در تطابقِ چندگانه null (تا پاداش
     * به فردِ اشتباه نرسد). با excludeMobile می‌توان خودمعرفی را حذف کرد.
     */
    public static function findByReferralCode(?string $code, ?string $excludeMobile = null): ?self
    {
        $digits = preg_replace('/\D/', '', (string) $code);
        if (strlen((string) $digits) < 6) {
            return null;
        }
        $suffix = substr($digits, -6);

        $matches = static::query()->active()
            ->where('mobile', 'like', '%'.$suffix)
            ->get(['id', 'mobile'])
            ->filter(function (self $c) use ($suffix, $excludeMobile) {
                $m = preg_replace('/\D/', '', (string) $c->mobile);

                return substr((string) $m, -6) === $suffix && $c->mobile !== $excludeMobile;
            })
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** تراکنش‌های کیف‌پول. */
    public function walletTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerWalletTransaction::class, 'customer_id');
    }

    /** درخواست‌های برداشتِ وجه. */
    public function withdrawalRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CustomerWithdrawalRequest::class, 'customer_id');
    }

    /** معرفِ این مشتری (کسی که او را دعوت کرده). */
    public function referrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_by');
    }

    /** مشتریانی که این کاربر معرفی کرده. */
    public function referrals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Customer::class, 'referred_by');
    }

    // ─── Auth helpers ──────────────────────────────────────────────

    public function isMobileVerified(): bool
    {
        return ! is_null($this->mobile_verified_at);
    }

    public function isActive(): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    public function recordLogin(?string $ip = null): void
    {
        $this->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByMobile($query, string $mobile)
    {
        return $query->where('mobile', $mobile);
    }

    // ─── Display ─────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        // هرگز شماره موبایل را به‌عنوان نام نمایشی برنگردان (حریم خصوصی —
        // این accessor در انجمن و سطوح عمومی هم استفاده می‌شود).
        return $this->composedName() ?? 'کاربر تعمیرآنلاین';
    }

    /**
     * نام + نام خانوادگیِ ترکیب‌شده با حذفِ تکرار؛ null اگر هیچ نامی نباشد.
     *
     * در دیتای قدیمی گاهی کلِ نام داخل first_name ذخیره شده و بعداً last_name
     * (که زیرمجموعه‌ی همان است) اضافه شده؛ در این حالت نباید دوباره append شود
     * (وگرنه «صادق نقیبی نقیبی» می‌شود).
     */
    public function composedName(): ?string
    {
        $first = trim((string) ($this->first_name ?? ''));
        $last = trim((string) ($this->last_name ?? ''));

        if ($first === '' && $last === '') {
            return null;
        }
        if ($first === '') {
            return $last;
        }
        if ($last === '') {
            return $first;
        }

        $firstWords = preg_split('/\s+/u', $first) ?: [];

        return in_array($last, $firstWords, true) ? $first : $first.' '.$last;
    }

    /** نام نمایشی legacy — هم‌خوان با کد قدیمی که این accessor را صدا می‌زد. */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name;
    }

    // ─── Subscription number (legacy formula) ─────────────────────

    public function getSubscriptionAttribute(): int
    {
        $base = $this->wp_id ?? $this->id;

        return (int) $base + self::SUBSCRIPTION_OFFSET;
    }

    public static function findBySubscription(int|string $subscription): ?self
    {
        $sub = (int) $subscription;
        if ($sub <= self::SUBSCRIPTION_OFFSET) {
            return null;
        }

        $candidate = $sub - self::SUBSCRIPTION_OFFSET;

        return static::where('wp_id', $candidate)->first()
            ?? static::find($candidate);
    }

    public static function findByMobile(string $mobile): ?self
    {
        return static::where('mobile', $mobile)->first();
    }

    // ─── Relations ───────────────────────────────────────────────

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * آدرس‌های مشتری (multi-address — اپ موبایل).
     * فیلدهای province_id/city_id/address/postal_code روی همین مدل برای
     * سازگاری legacy حفظ شده‌اند ولی اپ موبایل از این رابطه استفاده می‌کند.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default')->orderByDesc('id');
    }

    public function defaultAddress(): ?CustomerAddress
    {
        return $this->addresses()->where('is_default', true)->first()
            ?? $this->addresses()->orderBy('id')->first();
    }

    // ─── WP push observer — async via afterResponse, env-toggleable ─

    protected static function booted(): void
    {
        $push = function (self $c) {
            // در CLI فقط در صورتی push شود که صراحتاً force شده باشد (seeders/commands)
            if (app()->runningInConsole() && ! app()->bound('crm.wp_push.force')) {
                return;
            }
            // kill switch سراسری — اگر WP خاموش است، در .env بگذارید: CRM_WP_PUSH_ENABLED=false
            if (! config('crm.wp_push.enabled', true)) {
                return;
            }

            // HTTP push بعد از ارسال response اجرا می‌شود تا کاربر منتظر نماند
            dispatch(function () use ($c) {
                try {
                    app(\Modules\CRM\Services\WpPushService::class)->pushCustomer($c);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('crm.wp_push.customer_failed', [
                        'id' => $c->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();
        };

        static::created($push);
        static::updated(function (self $c) use ($push) {
            // تغییرِ فقط-کیف‌پول/معرف به WP push نمی‌شود (WP این مفاهیم را ندارد).
            $walletOnly = ['wallet_balance', 'referred_by', 'referral_rewarded_at', 'updated_at'];
            $changed = array_keys($c->getChanges());
            if ($changed !== [] && empty(array_diff($changed, $walletOnly))) {
                return;
            }
            $push($c);
        });
    }
}
