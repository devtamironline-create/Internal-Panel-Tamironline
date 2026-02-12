<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseWcShippingMethod extends Model
{
    protected $table = 'warehouse_wc_shipping_methods';

    protected $fillable = [
        'wc_instance_id',
        'method_id',
        'method_title',
        'zone_id',
        'zone_name',
        'enabled',
        'order_count',
        'mapped_shipping_type',
        'raw_data',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'order_count' => 'integer',
        'raw_data' => 'array',
    ];

    public function shippingType()
    {
        return $this->belongsTo(WarehouseShippingType::class, 'mapped_shipping_type', 'slug');
    }

    public function getAutoDetectedTypeAttribute(): ?string
    {
        $title = mb_strtolower($this->method_title);
        $mId = strtolower($this->method_id);

        if (str_contains($title, 'حضوری') || str_contains($mId, 'local_pickup') || str_contains($mId, 'pickup')) {
            return 'pickup';
        }
        if (str_contains($title, 'فوری') || str_contains($title, 'پیک') || str_contains($mId, 'courier') || str_contains($mId, 'local_delivery')) {
            return 'courier';
        }
        if (str_contains($title, 'پست') || str_contains($title, 'پیشتاز') || str_contains($mId, 'flat_rate')) {
            return 'post';
        }
        if (str_contains($mId, 'free_shipping')) {
            return 'post';
        }

        return null;
    }
}
