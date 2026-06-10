<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * انواع خدمات قابل ارائه — منبع حقیقت برای picker اپ موبایل و backward
 * compat با Order.order_type (string) و Technician.service_types (JSON).
 */
class ServiceType extends Model
{
    protected $table = 'crm_service_types';

    protected $fillable = [
        'slug',
        'name',
        'icon',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}
