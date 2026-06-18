<?php

namespace Modules\Seo\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Seo\Models\SeoMeta;
use Modules\Seo\Services\SlugHistoryRecorder;

/**
 * به هر مدلی که باید متای سئو داشته باشد اضافه می‌شود. رابطهٔ morphOne
 * به seo_meta و یک هلپر برای گرفتن/ساختِ رکورد متا.
 */
trait HasSeoMeta
{
    /**
     * بوتِ خودکارِ تریت: با تغییرِ slug، تاریخچه ثبت و redirect 301 ساخته می‌شود.
     */
    public static function bootHasSeoMeta(): void
    {
        static::updated(function ($model): void {
            app(SlugHistoryRecorder::class)->handle($model);
        });
    }

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
