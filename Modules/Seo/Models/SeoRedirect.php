<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    protected $table = 'seo_redirects';

    public const STATUS_CODES = [301, 302, 307, 410, 451];

    public const MATCH_TYPES = ['exact', 'contains', 'start', 'end', 'regex'];

    protected $fillable = [
        'source', 'target', 'status_code', 'match_type', 'is_active', 'hits', 'last_hit_at',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'is_active' => 'boolean',
        'hits' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
