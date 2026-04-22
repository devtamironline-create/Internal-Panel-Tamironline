<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    protected $table = 'crm_cities';

    protected $fillable = [
        'province_id',
        'name',
        'slug',
        'sort_order',
    ];

    protected $casts = [
        'province_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
