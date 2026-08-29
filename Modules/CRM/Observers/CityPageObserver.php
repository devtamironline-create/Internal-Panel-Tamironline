<?php

namespace Modules\CRM\Observers;

use Modules\CRM\Models\City;
use Modules\CRM\Services\CityPageGenerator;

/**
 * با ساختهٔ شدنِ یک «شهرِ اصلی»، درختِ صفحاتِ سئوِ آن به‌صورت پیش‌نویس
 * ساخته می‌شود (SEO-024). مناطق (districts) نادیده گرفته می‌شوند.
 *
 * تولید در همان تراکنشِ درخواست انجام می‌شود اما برای شهرِ تازه سبک است
 * (هنوز تکنسینی تگ نخورده ⇒ فقط سه صفحهٔ ثابت). خطا نباید ساختِ شهر را
 * شکست دهد؛ پس داخلِ try نگه داشته می‌شود.
 */
class CityPageObserver
{
    public function created(City $city): void
    {
        if ($city->isDistrict() || empty($city->slug)) {
            return;
        }

        try {
            app(CityPageGenerator::class)->sync($city);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('crm.city_pages.generate_failed', [
                'city_id' => $city->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
