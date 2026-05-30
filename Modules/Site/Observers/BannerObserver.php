<?php

namespace Modules\Site\Observers;

use Modules\Site\Models\Banner;
use Modules\Site\Models\BannerZone;
use Modules\Site\Services\PageSectionService;
use Modules\Site\Support\BannerCache;

/**
 * Auto-invalidate cache هر بار که Banner ایجاد/تغییر/حذف می‌شود.
 * این تضمین می‌کند بنر جدید/تغییر در ≤۱ ثانیه در فرانت ظاهر شود
 * (به‌علاوه‌ی webhook revalidation برای ISR Next.js).
 *
 * علاوه بر کش بنر، صفحاتی که این زون را inline-hydrate می‌کنند نیز
 * با تگ `page:{slug}` به فرانت اطلاع داده می‌شوند تا ISR آن صفحات رفرش شود.
 */
class BannerObserver
{
    public function created(Banner $banner): void
    {
        BannerCache::forgetForBanner($banner);
        $this->revalidateDependentPages([$banner->zone_id]);
    }

    public function updated(Banner $banner): void
    {
        // اگر zone_id عوض شده، هردو زون قبلی و جدید فراموش شوند
        $oldZoneId = $banner->getOriginal('zone_id');
        $changedZone = $oldZoneId !== $banner->zone_id;
        BannerCache::forgetForBanner($banner, $changedZone ? $oldZoneId : null);
        $this->revalidateDependentPages(array_filter([$banner->zone_id, $changedZone ? $oldZoneId : null]));
    }

    public function deleted(Banner $banner): void
    {
        BannerCache::forgetForBanner($banner);
        $this->revalidateDependentPages([$banner->zone_id]);
    }

    /**
     * بر اساس zone_id های متأثر، slug زون‌ها را پیدا می‌کنیم و سپس صفحاتی
     * که از این زون‌ها استفاده می‌کنند را به Next.js اطلاع می‌دهیم.
     *
     * @param  array<int, int|null>  $zoneIds
     */
    private function revalidateDependentPages(array $zoneIds): void
    {
        $zoneIds = array_values(array_filter(array_unique($zoneIds)));
        if ($zoneIds === []) {
            return;
        }

        $slugs = BannerZone::query()->whereIn('id', $zoneIds)->pluck('slug')->all();
        if ($slugs === []) {
            return;
        }

        $service = app(PageSectionService::class);
        $pageSlugs = [];
        foreach ($slugs as $zoneSlug) {
            foreach ($service->pagesUsingBannerZone($zoneSlug) as $pageSlug) {
                $pageSlugs[$pageSlug] = true;
            }
        }
        foreach (array_keys($pageSlugs) as $pageSlug) {
            BannerCache::pingPage($pageSlug);
        }
    }
}
