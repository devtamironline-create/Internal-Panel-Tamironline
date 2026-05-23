<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;

/**
 * فعالیت‌های زنده‌ی سایت — تولید دیتای فیک واقع‌نمایانه.
 *
 * منبع داده: لیست device/brand‌های فعال CRM + لیست مناطق در
 * config('site.activity-areas') + تولید تصادفی minutes_ago و status.
 *
 * بدون پارامتر:   home → ترکیب تصادفی device + area
 * با ?device_slug: صفحه‌ی device → فقط همان دستگاه با مناطق متنوع
 * با ?brand_slug:  صفحه‌ی برند → برند مشخص + ترکیب با devices تصادفی
 *
 * هر بار صدا زدن، dataset جدیدی تولید می‌شود ولی با seed مبتنی بر زمان
 * تا در دوره‌ی کش (s-maxage=60) ثابت بماند.
 */
class ActivityController extends Controller
{
    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        // پشتیبانی از هر دو ?device و ?device_slug (alias کوتاه و طولانی)
        $deviceSlug = $request->query('device') ?? $request->query('device_slug');
        $brandSlug  = $request->query('brand')  ?? $request->query('brand_slug');

        $devices = $this->devices($deviceSlug);
        $brand   = $brandSlug ? Brand::query()
            ->where('slug', $brandSlug)
            ->where('is_active', true)
            ->first(['id', 'name', 'slug'])
            : null;

        if ($devices->isEmpty() || ($brandSlug && ! $brand)) {
            return response()
                ->json(['data' => []])
                ->header('Cache-Control', 'public, max-age=60, s-maxage=60');
        }

        $areas = config('site.activity-areas', []);
        if (empty($areas)) {
            $areas = ['تهران'];
        }

        // برندهای فعال برای انتساب تصادفی وقتی brand فیلتر نشده
        $allBrands = $brand
            ? collect([$brand])
            : Brand::query()->where('is_active', true)->get(['id', 'name', 'slug']);

        // seed مبتنی بر دقیقه — در یک پنجره‌ی ۶۰ ثانیه‌ای ثابت می‌ماند
        // تا با کش HTTP هم‌خوان باشد.
        $seedKey = (int) floor(time() / 60) . ($deviceSlug ?? '') . ($brandSlug ?? '');
        $rng = new \Random\Randomizer(new \Random\Engine\Mt19937(crc32($seedKey)));

        $data = [];
        for ($i = 0; $i < $limit; $i++) {
            $device = $devices[$rng->getInt(0, $devices->count() - 1)];
            $area   = $areas[$rng->getInt(0, count($areas) - 1)];

            // برند: اگر فیلتر شده، همان؛ در غیر این صورت یکی تصادفی (اگر برندی موجود باشد)
            $rowBrand = $brand;
            if (! $rowBrand && $allBrands->isNotEmpty()) {
                $rowBrand = $allBrands[$rng->getInt(0, $allBrands->count() - 1)];
            }

            $minutesAgo = $this->pickMinutesAgo($rng);
            $status     = $rng->getInt(0, 100) < 75 ? 'completed' : 'in_progress';

            $label = $rowBrand
                ? sprintf('تعمیر %s %s', $device->name, $rowBrand->name)
                : sprintf('تعمیر %s', $device->name);

            $data[] = [
                // ULID-style id برای فرانت (برای React key)
                'id'           => 'act_' . substr(md5($seedKey . $i), 0, 16),
                'device_slug'  => $device->slug,
                'device_name'  => $device->name,         // spec field
                'device_label' => $label,                // backward compat (متن کامل)
                'brand'        => $rowBrand?->name,      // spec field
                'brand_slug'   => $rowBrand?->slug,
                'brand_label'  => $rowBrand?->name,      // backward compat
                'area'         => $area,
                'status'       => $status,
                'minutes_ago'  => $minutesAgo,
            ];
        }

        // مرتب‌سازی: جدیدترها اول
        usort($data, fn ($a, $b) => $a['minutes_ago'] <=> $b['minutes_ago']);

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }

    /**
     * لیست دستگاه‌ها بر اساس فیلتر slug.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Device>
     */
    private function devices(?string $slug): \Illuminate\Database\Eloquent\Collection
    {
        $query = Device::query()->where('is_active', true);
        if ($slug) {
            $query->where('slug', $slug);
        }
        return $query->get(['id', 'name', 'slug']);
    }

    /**
     * توزیع زمان واقع‌نمایانه — ۶۰٪ احتمال در ۳۰ دقیقه اخیر،
     * ۳۰٪ بین ۳۰ دقیقه تا ۶ ساعت، ۱۰٪ بین ۶ تا ۴۸ ساعت.
     */
    private function pickMinutesAgo(\Random\Randomizer $rng): int
    {
        $r = $rng->getInt(0, 99);
        if ($r < 60) {
            return $rng->getInt(1, 30);
        }
        if ($r < 90) {
            return $rng->getInt(30, 360);
        }
        return $rng->getInt(360, 2880);
    }
}
