<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Seo\Concerns\HasSeoMeta;
use Modules\Site\Models\Concerns\HasMedia;

class Brand extends Model
{
    use HasMedia;
    use HasSeoMeta;

    protected $table = 'crm_brands';

    protected $fillable = [
        'wp_id',
        'name',
        'slug',
        'logo',
        'logo_media_id',
        'tagline',
        'eyebrow',
        'subtitle',
        'caption',
        'description',
        'tone',
        'bg',
        'stats',
        'issues',
        'why_us',
        'faq',
        'videos',
        'hero_image',
        'meta_title',
        'meta_description',
        'warranty_text',
        'support_info',
        'cta_primary_label',
        'cta_primary_url',
        'cta_primary_icon',
        'cta_secondary_label',
        'cta_secondary_url',
        'cta_secondary_icon',
        'steps_image_desktop',
        'steps_image_mobile',
        'sections_enabled',
        'sort_order',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'stats' => 'array',
        'issues' => 'array',
        'why_us' => 'array',
        'faq' => 'array',
        'videos' => 'array',
        'hero_image' => 'array',
        'sections_enabled' => 'array',
    ];

    /**
     * دستگاه‌هایی که این برند پشتیبانی می‌کند.
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'crm_device_brands', 'brand_id', 'device_id')
            ->withPivot('sort_order')
            ->orderBy('crm_device_brands.sort_order');
    }

    /**
     * FAQهای اختصاصی این برند از بانک FAQ.
     */
    public function faqs(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Site\Models\Faq::class, 'crm_brand_faqs', 'brand_id', 'faq_id')
            ->withPivot('sort_order')
            ->orderBy('crm_brand_faqs.sort_order');
    }

    /**
     * دسته‌بندی‌های FAQ انتخاب‌شده برای این برند.
     */
    public function faqCategories(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Site\Models\Taxonomy::class, 'crm_brand_faq_categories', 'brand_id', 'taxonomy_id')
            ->where('site_taxonomies.type', \Modules\Site\Models\Taxonomy::TYPE_FAQ)
            ->withPivot('sort_order')
            ->orderBy('crm_brand_faq_categories.sort_order');
    }

    /**
     * نظرات/توصیه‌نامه‌های انتخاب‌شده برای نمایش در صفحه این برند.
     */
    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Site\Models\Review::class,
            'site_review_brands',
            'brand_id',
            'review_id'
        );
    }

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
