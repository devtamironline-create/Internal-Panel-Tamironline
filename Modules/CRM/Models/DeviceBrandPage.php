<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * صفحه‌ی ترکیبی (device, brand) — برای URLهایی مانند
 * /devices/dishwasher/samsung. هر pair می‌تواند مستقل تمام فیلدهای
 * hero/content/SEO و pivotهای FAQ/Reviews خودش را داشته باشد.
 *
 * منطق merge هنگام رندر API:
 *   مقدار per-pair (این رکورد) ?? per-device ?? per-brand ?? template
 */
class DeviceBrandPage extends Model
{
    protected $table = 'crm_device_brand_pages';

    protected $fillable = [
        'device_id',
        'brand_id',
        'eyebrow',
        'title',
        'subtitle',
        'caption',
        'cta_primary_label',
        'cta_primary_url',
        'cta_primary_icon',
        'cta_secondary_label',
        'cta_secondary_url',
        'cta_secondary_icon',
        'steps_image_desktop',
        'steps_image_mobile',
        'description',
        'meta_title',
        'meta_description',
        'sections_enabled',
        'is_active',
    ];

    protected $casts = [
        'sections_enabled' => 'array',
        'is_active' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function faqs(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Site\Models\Faq::class, 'crm_device_brand_page_faqs', 'page_id', 'faq_id')
            ->withPivot('sort_order')
            ->orderBy('crm_device_brand_page_faqs.sort_order');
    }

    public function faqCategories(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Site\Models\Taxonomy::class, 'crm_device_brand_page_faq_categories', 'page_id', 'taxonomy_id')
            ->where('site_taxonomies.type', \Modules\Site\Models\Taxonomy::TYPE_FAQ)
            ->withPivot('sort_order')
            ->orderBy('crm_device_brand_page_faq_categories.sort_order');
    }

    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Site\Models\Review::class,
            'crm_device_brand_page_reviews',
            'page_id',
            'review_id'
        );
    }

    /**
     * تضمین وجود رکورد برای یک pair (device, brand). اگر قبلاً ساخته
     * شده باشد دست‌نخورده برمی‌گردد — overrideهای ادمین نباید پاک شوند.
     *
     * پیش‌فرض is_active=false: ترکیب فقط «ساخته» می‌شود تا در combo-manager قابل
     * فعال‌سازی باشد، ولی تا وقتی ادمین صریحاً فعالش نکند روی سایت/فعالیت زنده
     * نمایش داده نمی‌شود (جلوگیری از ترکیب‌های بی‌ربطِ خودکار).
     */
    public static function ensureForPair(int $deviceId, int $brandId): self
    {
        return static::firstOrCreate(
            ['device_id' => $deviceId, 'brand_id' => $brandId],
            ['is_active' => false]
        );
    }
}
