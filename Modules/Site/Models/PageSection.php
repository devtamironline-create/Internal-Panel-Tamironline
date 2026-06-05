<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;

class PageSection extends Model
{
    protected $table = 'site_page_sections';

    protected $fillable = [
        'page_slug',
        'section_key',
        'payload',
        'is_published',
    ];

    protected $casts = [
        'payload'      => 'array',
        'is_published' => 'boolean',
    ];

    public function scopeForPage($query, string $slug)
    {
        return $query->where('page_slug', $slug);
    }
}
