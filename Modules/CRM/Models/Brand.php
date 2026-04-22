<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'crm_brands';

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
