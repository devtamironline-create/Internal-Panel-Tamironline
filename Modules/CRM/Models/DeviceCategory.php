<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دسته‌بندی والد دستگاه‌ها (مثل «لوازم آشپزخانه»). هر دستگاه به یک
 * دسته تعلق دارد؛ برای گروه‌بندی در منو/صفحه‌ی فهرست دستگاه‌ها.
 */
class DeviceCategory extends Model
{
    protected $table = 'crm_device_categories';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'tone',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'device_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
