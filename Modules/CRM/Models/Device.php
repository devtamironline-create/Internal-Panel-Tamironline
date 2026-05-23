<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Device extends Model
{
    protected $table = 'crm_devices';

    protected $fillable = [
        'wp_id',
        'name',
        'short_name',
        'slug',
        'icon',
        'thumbnail',
        'tone',
        'description',
        'subtitle',
        'eyebrow',
        'service_name',
        'technician_name',
        'starting_price',
        'accent',
        'bg',
        'issues',
        'faq',
        'meta_title',
        'meta_description',
        'warranty_text',
        'support_info',
        'service_steps',
        'sort_order',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'wp_id' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'starting_price' => 'integer',
        'issues' => 'array',
        'faq' => 'array',
        'service_steps' => 'array',
    ];

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
