<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BannerZone extends Model
{
    protected $table = 'site_banner_zones';

    protected $fillable = [
        'slug', 'name', 'description',
        'recommended_width', 'recommended_height',
        'max_count', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'recommended_width' => 'integer',
        'recommended_height' => 'integer',
        'max_count' => 'integer',
        'sort_order' => 'integer',
    ];

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class, 'zone_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
