<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Seo\Concerns\HasSeoMeta;

/**
 * تاپیک‌های مقاله — برای فیلتر و marquee بالای /blog و badge کارت‌ها.
 */
class BlogTopic extends Model
{
    use HasSeoMeta;

    protected $table = 'site_blog_topics';

    protected $fillable = [
        'wp_term_id',
        'slug',
        'name',
        'device_id',
        'brand_id',
        'icon',
        'color_bg',
        'color_fg',
        'color_border',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'wp_term_id' => 'integer',
        'device_id' => 'integer',
        'brand_id' => 'integer',
    ];

    /** دستگاهِ گره‌خورده به این تاپیک (اختیاری). */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    /** برندِ گره‌خورده به این تاپیک (اختیاری). */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'site_blog_article_topics', 'topic_id', 'article_id')
            ->withPivot('sort_order')
            ->orderBy('site_blog_article_topics.sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
