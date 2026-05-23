<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'crm_devices';

    protected $fillable = [
        'wp_id',
        'name',
        'short_name',
        'slug',
        'icon',
        'thumbnail',
        'tone',
        'description',
        'service_name',
        'technician_name',
        'starting_price',
        'accent',
        'bg',
        'issues',
        'faq',
        'meta_title',
        'meta_description',
        'sort_order',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'starting_price' => 'integer',
        'issues' => 'array',
        'faq'    => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
