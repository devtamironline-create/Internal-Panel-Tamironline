<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Page extends Model
{
    use HasUlids;

    protected $table = 'site_pages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'title',
        'meta_title',
        'meta_description',
        'content',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function testimonials(): BelongsToMany
    {
        return $this->belongsToMany(
            Review::class,
            'site_page_testimonials',
            'page_id',
            'testimonial_id'
        )->where('site_reviews.type', Review::TYPE_AUDIO)
            ->withPivot('sort_order')
            ->orderBy('site_page_testimonials.sort_order');
    }

    public function faqs(): BelongsToMany
    {
        return $this->belongsToMany(
            Faq::class,
            'site_page_faqs',
            'page_id',
            'faq_id'
        )->withPivot('sort_order')->orderBy('site_page_faqs.sort_order');
    }
}
