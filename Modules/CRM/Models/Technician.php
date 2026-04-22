<?php

namespace Modules\CRM\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Technician extends Model
{
    protected $table = 'crm_technicians';

    protected $fillable = [
        'user_id',
        'tech_code',
        'national_code',
        'first_name',
        'last_name',
        'mobile',
        'phone',
        'phone_force',
        'email',
        'gender',
        'province_id',
        'city_id',
        'address',
        'specialty',
        'tech_type',
        'description',
        'personal_image',
        'card_image',
        'commission_percent',
        'max_concurrent_orders',
        'max_order_price',
        'calc_type',
        'bank_sheba',
        'bank_card',
        'bank_account',
        'is_active',
        'ready_for_delivery',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ready_for_delivery' => 'boolean',
        'commission_percent' => 'integer',
        'max_concurrent_orders' => 'integer',
        'max_order_price' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function getFullNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

        return $name !== '' ? $name : $this->mobile;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeReady($query)
    {
        return $query->where('is_active', true)->where('ready_for_delivery', true);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function ($q) use ($term) {
            $q->where('mobile', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('tech_code', 'like', "%{$term}%")
                ->orWhere('national_code', 'like', "%{$term}%")
                ->orWhere('specialty', 'like', "%{$term}%");
        });
    }
}
