<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * آدرس مشتری (چند-آدرسه — برای اپلیکیشن موبایل).
 *
 * هر مشتری می‌تواند چند آدرس داشته باشد. is_default روی یکی است
 * (تضمین در سرویس‌لایر، نه constraint سخت DB).
 */
class CustomerAddress extends Model
{
    protected $table = 'crm_customer_addresses';

    protected $fillable = [
        'customer_id',
        'label',
        'province_id',
        'city_id',
        'district_id',
        'full_address',
        'latitude',
        'longitude',
        'postal_code',
        'phone',
        'is_default',
        'is_transient',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'province_id' => 'integer',
        'city_id' => 'integer',
        'district_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_default' => 'boolean',
        'is_transient' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** منطقه (ردیف crm_cities با parent_city_id) — اختیاری. */
    public function district(): BelongsTo
    {
        return $this->belongsTo(City::class, 'district_id');
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
