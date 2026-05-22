<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Testimonial extends Model
{
    use HasUlids;

    protected $table = 'testimonials';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'customer_name',
        'topic',
        'rating',
        'audio_url',
        'duration_seconds',
        'is_published',
        'sort_order',
        'published_at',
    ];

    protected $casts = [
        'rating'           => 'integer',
        'duration_seconds' => 'integer',
        'is_published'     => 'boolean',
        'sort_order'       => 'integer',
        'published_at'     => 'datetime',
    ];

    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, 'site_testimonial_taxonomies', 'testimonial_id', 'taxonomy_id')
            ->where('site_taxonomies.type', Taxonomy::TYPE_TESTIMONIAL);
    }
}
