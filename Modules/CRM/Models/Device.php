<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Site\Models\Concerns\HasMedia;

class Device extends Model
{
    use HasMedia;

    protected $table = 'crm_devices';

    protected $fillable = [
        'wp_id',
        'device_category_id',
        'thumbnail_media_id',
        'name',
        'short_name',
        'slug',
        'icon',
        'thumbnail',
        'tone',
        'description',
        'subtitle',
        'eyebrow',
        'caption',
        'service_name',
        'technician_name',
        'starting_price',
        'accent',
        'bg',
        'issues',
        'faq',
        'videos',
        'hero_image',
        'meta_title',
        'meta_description',
        'warranty_text',
        'support_info',
        'service_steps',
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
        'device_category_id' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'starting_price' => 'integer',
        'issues' => 'array',
        'faq' => 'array',
        'videos' => 'array',
        'hero_image' => 'array',
        'service_steps' => 'array',
        'sections_enabled' => 'array',
    ];

    /**
     * دسته‌بندی والد این دستگاه (مثل «لوازم آشپزخانه»).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DeviceCategory::class, 'device_category_id');
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

    /**
     * برندهایی که این دستگاه را پشتیبانی می‌کنند.
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'crm_device_brands', 'device_id', 'brand_id')
            ->withPivot('sort_order')
            ->orderBy('crm_device_brands.sort_order');
    }

    /**
     * FAQهای اختصاصی این دستگاه از بانک FAQ.
     */
    public function faqs(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Site\Models\Faq::class, 'crm_device_faqs', 'device_id', 'faq_id')
            ->withPivot('sort_order')
            ->orderBy('crm_device_faqs.sort_order');
    }

    /**
     * دسته‌بندی‌های FAQ انتخاب‌شده برای این دستگاه — تمام سوالات این دسته‌ها
     * در API به‌صورت خودکار شامل می‌شوند.
     */
    public function faqCategories(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Site\Models\Taxonomy::class, 'crm_device_faq_categories', 'device_id', 'taxonomy_id')
            ->where('site_taxonomies.type', \Modules\Site\Models\Taxonomy::TYPE_FAQ)
            ->withPivot('sort_order')
            ->orderBy('crm_device_faq_categories.sort_order');
    }

    /**
     * نظرات/توصیه‌نامه‌های انتخاب‌شده برای نمایش در صفحه این دستگاه
     * (از بانک site_reviews — هم audio هم text).
     */
    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Site\Models\Review::class,
            'site_review_devices',
            'device_id',
            'review_id'
        );
    }
}
