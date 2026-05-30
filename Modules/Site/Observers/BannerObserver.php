<?php

namespace Modules\Site\Observers;

use Modules\Site\Models\Banner;
use Modules\Site\Support\BannerCache;

/**
 * Auto-invalidate cache هر بار که Banner ایجاد/تغییر/حذف می‌شود.
 * این تضمین می‌کند بنر جدید/تغییر در ≤۱ ثانیه در فرانت ظاهر شود
 * (به‌علاوه‌ی webhook revalidation برای ISR Next.js).
 */
class BannerObserver
{
    public function created(Banner $banner): void
    {
        BannerCache::forgetForBanner($banner);
    }

    public function updated(Banner $banner): void
    {
        // اگر zone_id عوض شده، هردو زون قبلی و جدید فراموش شوند
        $oldZoneId = $banner->getOriginal('zone_id');
        BannerCache::forgetForBanner($banner, $oldZoneId !== $banner->zone_id ? $oldZoneId : null);
    }

    public function deleted(Banner $banner): void
    {
        BannerCache::forgetForBanner($banner);
    }
}
