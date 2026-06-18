<?php

namespace Modules\Seo\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Seo\Models\SeoFaq;

/**
 * رابطهٔ polymorphic پرسش‌های متداول برای یک مدل seoable.
 *
 * نکته: این تریت عمداً روی مدل‌های دامنه (CRM/Site) نصب نشده تا تغییری
 * خارج از دامنهٔ سئو رخ ندهد. SchemaGenerator و MetaResolver مستقیماً با
 * faqable_type + faqable_id کوئری می‌زنند. این تریت برای استفادهٔ اختیاری
 * در آینده فراهم شده است.
 */
trait HasFaqs
{
    public function faqs(): MorphMany
    {
        return $this->morphMany(SeoFaq::class, 'faqable');
    }
}
