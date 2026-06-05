<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
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
        ];
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
        $full = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $full !== '' ? $full : ($this->mobile ?? 'مشتری');
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
        static::updated($push);
    }
}
