<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * منطقه (Region/District) ذیل یک شهر.
 *
 * مدل سادهٔ سلسله‌مراتبی: استان→شهر→منطقه. خود مفهوم منطقه اختیاری
 * است — شهرهای کوچک حتی یک ردیف در این جدول هم لازم ندارند، و سفارش
 * می‌تواند region_id=NULL داشته باشد.
 */
class Region extends Model
{
    protected $table = 'crm_regions';

    protected $fillable = [
        'city_id',
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'city_id'    => 'integer',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }
}
