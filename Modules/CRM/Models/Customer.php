<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

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
        'mobile',
        'first_name',
        'phone',
        'notes',
    ];

    /** شماره اشتراک = id + 10000 (هم‌سو با WP). */
    public function getSubscriptionAttribute(): int
    {
        return (int) $this->id + self::SUBSCRIPTION_OFFSET;
    }

    /** مشتری از روی شماره اشتراک */
    public static function findBySubscription(int|string $subscription): ?self
    {
        $sub = (int) $subscription;
        if ($sub <= self::SUBSCRIPTION_OFFSET) {
            return null;
        }

        return static::find($sub - self::SUBSCRIPTION_OFFSET);
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
}
