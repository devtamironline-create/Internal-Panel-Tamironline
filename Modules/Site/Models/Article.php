<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Seo\Concerns\HasSeoMeta;
use Modules\Site\Models\Concerns\HasComments;
use Modules\Site\Models\Concerns\HasMedia;

/**
 * مقاله‌ی بلاگ — می‌تواند به چند تاپیک، چند دستگاه و چند برند مرتبط باشد.
 */
class Article extends Model
{
    use HasComments;
    use HasMedia;
    use HasSeoMeta;

    protected $table = 'site_blog_articles';

    protected $fillable = [
        'wp_id',
        'slug',
        'title',
        'excerpt',
        'content',
        'cover_image',
        'cover_media_id',
        'cover_color',
        'read_time_minutes',
        'is_published',
        'published_at',
        'views_count',
        'sort_order',
        'meta_title',
        'meta_description',
        'author_name',
        'original_url',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'read_time_minutes' => 'integer',
        'views_count' => 'integer',
        'sort_order' => 'integer',
        'wp_id' => 'integer',
    ];

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(BlogTopic::class, 'site_blog_article_topics', 'article_id', 'topic_id')
            ->withPivot('sort_order')
            ->orderBy('site_blog_article_topics.sort_order');
    }

    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'site_blog_article_devices', 'article_id', 'device_id')
            ->withPivot('sort_order', 'is_active')
            ->orderBy('site_blog_article_devices.sort_order');
    }

    /** فقط دستگاه‌های فعالِ این مقاله (برای API عمومی). */
    public function activeDevices(): BelongsToMany
    {
        return $this->devices()->wherePivot('is_active', true);
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'site_blog_article_brands', 'article_id', 'brand_id')
            ->withPivot('sort_order', 'is_active')
            ->orderBy('site_blog_article_brands.sort_order');
    }

    /** فقط برندهای فعالِ این مقاله (برای API عمومی). */
    public function activeBrands(): BelongsToMany
    {
        return $this->brands()->wherePivot('is_active', true);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeLatest($query, string $column = 'published_at')
    {
        return $query->orderByDesc($column);
    }
}
