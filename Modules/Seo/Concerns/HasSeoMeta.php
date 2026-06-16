<?php

namespace Modules\Seo\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Seo\Models\SeoMeta;

/**
 * به هر مدلی که باید متای سئو داشته باشد اضافه می‌شود. رابطهٔ morphOne
 * به seo_meta و یک هلپر برای گرفتن/ساختِ رکورد متا.
 */
trait HasSeoMeta
{
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * رکورد متا را برمی‌گرداند؛ اگر نبود یک نمونهٔ خالی (ذخیره‌نشده) می‌سازد
     * تا فرم‌ها بدون null-check کار کنند.
     */
    public function seo(): SeoMeta
    {
        return $this->seoMeta()->firstOrNew([]);
    }
}
