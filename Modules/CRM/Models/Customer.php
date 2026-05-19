<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مشتری CRM — هم‌سو با مدل WP که فقط mobile/first_name/phone را نگه می‌دارد.
 *
 * شماره اشتراک (subscription) در WP محاسبه‌شده است: user_id + 10000.
 * در اینجا همان قاعده با accessor پیاده‌سازی شده تا نیاز به ستون اضافی نباشد.
 */
class Customer extends Model
{
    protected $table = 'crm_customers';

    /** آفست شماره اشتراک نمایشی نسبت به id (مثل WP). */
    public const SUBSCRIPTION_OFFSET = 10000;

    protected $fillable = [
        'wp_id',
        'mobile',
        'first_name',
        'phone',
        'notes',
    ];

    protected $casts = [
        'wp_id' => 'integer',
    ];

    /**
     * شماره اشتراک = (wp_id ?? id) + 10000.
     *
     * برای مشتریان سینک‌شده از WP، wp_id مرجع است تا شمارهٔ اشتراکشان
     * با همان مقدار تاریخی WP ثابت بماند. برای مشتریان جدیدِ لاراولی
     * که wp_id ندارند، از id لاراول استفاده می‌شود.
     */
    public function getSubscriptionAttribute(): int
    {
        $base = $this->wp_id ?? $this->id;

        return (int) $base + self::SUBSCRIPTION_OFFSET;
    }

    /** مشتری از روی شماره اشتراک — اول wp_id را امتحان می‌کند، سپس id لاراول. */
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

    /** مشتری از روی موبایل (دقیق، مثل WP). */
    public static function findByMobile(string $mobile): ?self
    {
        return static::where('mobile', $mobile)->first();
    }

    /** نام نمایشی — فقط first_name (WP اصلاً last_name ندارد). */
    public function getDisplayNameAttribute(): string
    {
        return trim($this->first_name ?? '') !== '' ? $this->first_name : $this->mobile;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Push تغییرات به WP. */
    protected static function booted(): void
    {
        $push = function (self $c) {
            if (app()->runningInConsole() && ! app()->bound('crm.wp_push.force')) return;
            if (app()->bound('crm.suppress_outbound_push')) return;
            try {
                app(\Modules\CRM\Services\WpPushService::class)->pushCustomer($c);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('crm.wp_push.customer_failed', [
                    'id' => $c->id, 'error' => $e->getMessage(),
                ]);
            }
        };
        static::created($push);
        static::updated($push);
    }
}
