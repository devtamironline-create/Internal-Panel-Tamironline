<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * ایراد قابل انتخاب توسط مشتری در فرم ثبت سفارش.
 *
 * هر ایراد می‌تواند به چند دستگاه متصل باشد. در API موبایل بر اساس
 * device_id انتخاب‌شده، لیست objection های مرتبط بازگشت می‌گیرد.
 */
class Objection extends Model
{
    protected $table = 'crm_objections';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'crm_device_objection', 'objection_id', 'device_id')
            ->withPivot('sort_order')
            ->orderBy('crm_device_objection.sort_order');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    public function scopeForDevice($q, int $deviceId)
    {
        return $q->whereHas('devices', fn ($d) => $d->where('crm_devices.id', $deviceId));
    }
}
