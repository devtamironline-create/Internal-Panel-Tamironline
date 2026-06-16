<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Seo\Concerns\HasSeoMeta;

class Taxonomy extends Model
{
    use HasSeoMeta;

    protected $table = 'site_taxonomies';

    protected $fillable = [
        'type',
        'slug',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const TYPE_FAQ = 'faq';

    public const TYPE_TESTIMONIAL = 'testimonial';

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function faqs(): BelongsToMany
    {
        return $this->belongsToMany(Faq::class, 'site_faq_taxonomies', 'taxonomy_id', 'faq_id');
    }

    /**
     * نظرات/توصیه‌نامه‌ها (پس از یکپارچه‌سازی reviews — فقط audio reviews تاکسونومی دارند).
     */
    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'site_review_taxonomies', 'taxonomy_id', 'review_id');
    }

    /**
     * Alias backward-compat برای کدهایی که هنوز testimonials() را صدا می‌زنند.
     */
    public function testimonials(): BelongsToMany
    {
        return $this->reviews();
    }
}
