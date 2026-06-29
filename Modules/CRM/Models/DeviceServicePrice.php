<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قیمتِ یک خدمت برای یک دستگاه (تعرفهٔ نمایش‌داده‌شده در سایت).
 */
class DeviceServicePrice extends Model
{
    protected $table = 'crm_device_service_prices';

    protected $fillable = [
        'device_id', 'title', 'price', 'compare_at_price',
        'is_free', 'note', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'is_free' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    /**
     * نمایشِ متنیِ قیمت برای API/پنل.
     */
    public function priceLabel(): string
    {
        if ($this->is_free) {
            return 'رایگان';
        }
        if ($this->price === null) {
            return $this->note ?: 'توافقی';
        }

        return number_format($this->price).' تومان';
    }
}
