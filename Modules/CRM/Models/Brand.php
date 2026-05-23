<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'crm_brands';

    protected $fillable = [
        'wp_id',
        'name',
        'slug',
        'logo',
        'tagline',
        'description',
        'tone',
        'bg',
        'stats',
        'issues',
        'why_us',
        'faq',
        'meta_title',
        'meta_description',
        'warranty_text',
        'support_info',
        'supported_device_slugs',
        'sort_order',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'stats'   => 'array',
        'issues'  => 'array',
        'why_us'  => 'array',
        'faq'     => 'array',
        'supported_device_slugs' => 'array',
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
