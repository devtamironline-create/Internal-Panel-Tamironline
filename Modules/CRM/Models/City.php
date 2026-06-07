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
        'name',
        'slug',
        'sort_order',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'province_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
