<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * یک رویدادِ تحلیلیِ خام. مصرفِ نوشتن از طریقِ App\Support\Analytics::track()
 * انجام می‌شود؛ این مدل بیشتر برای پرسش/گزارش‌گیری استفاده می‌شود.
 *
 * @property string $event
 * @property string|null $source
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $device_id
 * @property int|null $brand_id
 * @property int|null $customer_id
 * @property string|null $session_id
 * @property array|null $props
 * @property \Illuminate\Support\Carbon|null $occurred_at
 */
class AnalyticsEvent extends Model
{
    protected $table = 'analytics_events';

    protected $fillable = [
        'event', 'source', 'subject_type', 'subject_id',
        'device_id', 'brand_id', 'customer_id', 'session_id',
        'ip_hash', 'user_agent', 'url', 'props', 'occurred_at',
    ];

    protected $casts = [
        'props' => 'array',
        'occurred_at' => 'datetime',
        'device_id' => 'integer',
        'brand_id' => 'integer',
        'customer_id' => 'integer',
        'subject_id' => 'integer',
    ];

    /** رویدادهای یک نوعِ مشخص. */
    public function scopeOfEvent($query, string $event)
    {
        return $query->where('event', $event);
    }

    /** رویدادهای بازه‌ی زمانی بر اساسِ occurred_at. */
    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('occurred_at', [$from, $to]);
    }
}
