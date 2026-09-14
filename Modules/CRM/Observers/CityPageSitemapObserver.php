<?php

namespace Modules\CRM\Observers;

use Illuminate\Support\Facades\Cache;
use Modules\CRM\Models\CityPage;
use Modules\Seo\Services\SitemapBuilder;

/**
 * وقتی وضعیتِ انتشارِ یک صفحهٔ شهری (به‌ویژه کامبوی شهری) عوض/حذف/بازگردانی می‌شود،
 * کشِ داغِ سایت‌مپ باید فوراً پاک شود؛ وگرنه sitemap-local.xml تا ۶۰ دقیقه
 * (CACHE_TTL_MINUTES) همان XMLِ قدیمی را می‌دهد و کامبوی غیرفعال در آن می‌ماند.
 *
 * فقط کشِ سبک را می‌ریزد (چند Cache::forget) — hookِ سنگین/HTTP این‌جا نیست تا
 * همگام‌سازیِ گروهیِ صفحات (Generator) و ذخیرهٔ پیش‌نویس‌ها کند نشود. بازاعتبارسنجیِ
 * فرانت در خودِ اکشن‌های کنترلر (togglePublish/publishAll/destroy) انجام می‌شود.
 */
class CityPageSitemapObserver
{
    public function saved(CityPage $page): void
    {
        // فقط تغییری که روی حضور در سایت‌مپ اثر دارد (وضعیت/تاریخِ انتشار/مسیر).
        if ($page->wasChanged(['status', 'published_at', 'path'])) {
            $this->flushSitemapCache();
        }
    }

    public function deleted(CityPage $page): void
    {
        $this->flushSitemapCache();
    }

    public function restored(CityPage $page): void
    {
        $this->flushSitemapCache();
    }

    public function forceDeleted(CityPage $page): void
    {
        $this->flushSitemapCache();
    }

    /** پاک‌کردنِ کشِ داغِ سایت‌مپ (هم‌ارزِ seo:sitemap-flush بدونِ --hard). */
    private function flushSitemapCache(): void
    {
        Cache::forget('seo:sitemap:spec-index:xml');
        foreach (array_keys(SitemapBuilder::SPEC_FILES) as $name) {
            Cache::forget('seo:sitemap:spec:'.$name.':xml');
        }
    }
}
