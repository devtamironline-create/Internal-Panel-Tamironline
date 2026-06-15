<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $table = 'crm_cities';

    protected $fillable = [
        'wp_id',
        'province_id',
        'parent_city_id',
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'province_id' => 'integer',
        'parent_city_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** شهر والد — فقط برای ردیف‌های «منطقه». */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_city_id');
    }

    /** مناطق این شهر (مثلاً ۲۲ منطقه تهران). */
    public function districts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_city_id');
    }

    public function isDistrict(): bool
    {
        return $this->parent_city_id !== null;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** فقط شهرهای اصلی — بدون ردیف‌های منطقه. */
    public function scopeMainCities($query)
    {
        return $query->whereNull('parent_city_id');
    }
}
