<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * یک پرسش متداول متصل به یک صفحهٔ seoable (polymorphic). در خروجی متا و
 * در FAQPage schema (§38) مصرف می‌شود.
 */
class SeoFaq extends Model
{
    protected $table = 'seo_faqs';

    protected $fillable = [
        'faqable_type',
        'faqable_id',
        'question',
        'answer',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function faqable(): MorphTo
    {
        return $this->morphTo();
    }

    /** فقط FAQهای فعال، مرتب‌شده بر اساس sort_order. */
    public function scopeActive($q)
    {
        return $q->where('is_active', true)->orderBy('sort_order');
    }
}
