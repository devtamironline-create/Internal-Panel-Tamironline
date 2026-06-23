<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\CRM\Models\Order;

/**
 * فعالیت‌های زنده‌ی سایت — دیتای واقع‌نمایانه اما فقط از ترکیب‌های معتبر.
 *
 * نکته‌ی کلیدی: برند فقط وقتی به یک دستگاه نسبت داده می‌شود که «صفحه‌ی ترکیبیِ
 * فعال» (DeviceBrandPage با is_active=true) برای آن جفت وجود داشته باشد. این
 * تضمین می‌کند هیچ‌وقت برندی به دستگاهی که نمی‌سازد نسبت داده نشود (مثلِ
 * «آبسال + ماشین لباسشویی»). اگر دستگاهی هیچ ترکیبِ فعالی نداشته باشد، فقط
 * نام دستگاه ارسال می‌شود (که همیشه درست است).
 *
 * منبع داده: device/brandهای فعال + DeviceBrandPageهای فعال + مناطقِ
 * config('site.activity-areas') + تولید تصادفیِ minutes_ago/status با seedِ
 * مبتنی بر دقیقه (هم‌خوان با کش ۶۰ ثانیه‌ای).
 *
 * بدون پارامتر:   home → دستگاهِ تصادفی + برندِ سازگار (یا بدون برند)
 * با ?device_slug: صفحه‌ی device → همان دستگاه + برندِ سازگار
 * با ?brand_slug:  صفحه‌ی برند → همان برند + فقط دستگاه‌هایی که با آن ترکیبِ فعال دارند
 */
class ActivityController extends Controller
{
    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        // پشتیبانی از هر دو ?device و ?device_slug (alias کوتاه و طولانی)
        $deviceSlug = $request->query('device') ?? $request->query('device_slug');
        $brandSlug = $request->query('brand') ?? $request->query('brand_slug');

        $devices = $this->devices($deviceSlug);
        $brand = $brandSlug ? Brand::query()
            ->where('slug', $brandSlug)
            ->where('is_active', true)
            ->first(['id', 'name', 'slug'])
            : null;

        if ($devices->isEmpty() || ($brandSlug && ! $brand)) {
            return $this->respond([]);
        }

        // ─── نقشه‌ی برندهای سازگار با هر دستگاه از روی ترکیب‌های فعال ───
        // device_id => [brand_id, ...]  (فقط ترکیب‌های is_active=true)
        $comboQuery = DeviceBrandPage::query()
            ->where('is_active', true)
            ->whereIn('device_id', $devices->pluck('id')->all());
        if ($brand) {
            $comboQuery->where('brand_id', $brand->id);
        }
        $combos = $comboQuery->get(['device_id', 'brand_id']);

        $brandIdsByDevice = $combos->groupBy('device_id')
            ->map(fn ($g) => $g->pluck('brand_id')->all());

        // فقط برندهای فعال (combo ممکن است به برندِ غیرفعال اشاره کند).
        $brandPool = Brand::query()
            ->whereIn('id', $combos->pluck('brand_id')->unique()->all())
            ->where('is_active', true)
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        // اگر برند فیلتر شده: دستگاه‌ها را به آن‌هایی محدود کن که با این برند
        // ترکیبِ فعال دارند — تا هیچ آیتمِ ناسازگاری ساخته نشود.
        if ($brand) {
            $devices = $devices->filter(function ($d) use ($brandIdsByDevice, $brandPool, $brand) {
                return in_array($brand->id, $brandIdsByDevice[$d->id] ?? [], true)
                    && $brandPool->has($brand->id);
            })->values();

            if ($devices->isEmpty()) {
                return $this->respond([]);
            }
        }

        $areas = config('site.activity-areas', []);
        if (empty($areas)) {
            $areas = ['تهران'];
        }

        // seed مبتنی بر دقیقه — در پنجره‌ی ۶۰ ثانیه‌ای ثابت می‌ماند (هم‌خوان با کش).
        $seedKey = (int) floor(time() / 60).($deviceSlug ?? '').($brandSlug ?? '');
        $rng = new \Random\Randomizer(new \Random\Engine\Mt19937(crc32($seedKey)));

        $data = [];
        $prevDeviceId = null;
        $prevArea = null;
        for ($i = 0; $i < $limit; $i++) {
            $device = $this->pickDistinct($devices, $prevDeviceId, $rng, fn ($d) => $d->id);
            $prevDeviceId = $device->id;

            $area = $this->pickDistinctScalar($areas, $prevArea, $rng);
            $prevArea = $area;

            // برندِ سازگار: اگر فیلتر شده، همان؛ وگرنه یکی از comboهای فعالِ این
            // دستگاه (با ۳۰٪ احتمال «بدون برند» برای تنوعِ طبیعی).
            $rowBrand = null;
            if ($brand) {
                $rowBrand = $brand;
            } else {
                $compatIds = array_values(array_filter(
                    $brandIdsByDevice[$device->id] ?? [],
                    fn ($id) => $brandPool->has($id)
                ));
                if (! empty($compatIds) && $rng->getInt(0, 99) < 70) {
                    $rowBrand = $brandPool[$compatIds[$rng->getInt(0, count($compatIds) - 1)]];
                }
            }

            $minutesAgo = $this->pickMinutesAgo($rng);
            $status = $rng->getInt(0, 100) < 75 ? 'completed' : 'in_progress';

            $label = $rowBrand
                ? sprintf('تعمیر %s %s', $device->name, $rowBrand->name)
                : sprintf('تعمیر %s', $device->name);

            $data[] = [
                'id' => 'act_'.substr(md5($seedKey.$i), 0, 16),
                'device_slug' => $device->slug,
                'device_name' => $device->name,
                'device_label' => $label,
                'brand' => $rowBrand?->name,
                'brand_slug' => $rowBrand?->slug,
                'brand_label' => $rowBrand?->name,
                'area' => $area,
                'status' => $status,
                'minutes_ago' => $minutesAgo,
            ];
        }

        // مرتب‌سازی: جدیدترها اول
        usort($data, fn ($a, $b) => $a['minutes_ago'] <=> $b['minutes_ago']);

        return $this->respond($data);
    }

    /**
     * پاسخ استاندارد + شمارِ واقعیِ «تعمیر موفقِ امروز» (top-level، backward-compatible:
     * data همچنان آرایه‌ی آیتم‌هاست).
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    private function respond(array $data): JsonResponse
    {
        return response()
            ->json([
                'data' => $data,
                'today_count' => $this->todayCount(),
            ])
            ->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }

    /**
     * تعداد سفارش‌های «انجام‌شده»ی امروز (timezone اپ = Asia/Tehran).
     * در صورت هر خطا null تا activity هیچ‌وقت ۵۰۰ نشود.
     */
    private function todayCount(): ?int
    {
        try {
            return Order::query()
                ->where('status', OrderStatus::Completed->value)
                ->where('completed_at', '>=', now()->startOfDay())
                ->count();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * یک آیتم تصادفی که (در صورت امکان) با آیتمِ قبلی فرق دارد — برای جلوگیری
     * از تکرارِ پشت‌سرهم.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $items
     * @param  callable(object): mixed  $keyOf
     */
    private function pickDistinct($items, $prevKey, \Random\Randomizer $rng, callable $keyOf)
    {
        $count = $items->count();
        $pick = $items[$rng->getInt(0, $count - 1)];
        if ($count > 1 && $keyOf($pick) === $prevKey) {
            $pick = $items[$rng->getInt(0, $count - 1)];
        }

        return $pick;
    }

    /**
     * نسخه‌ی scalar از pickDistinct برای آرایه‌ی رشته‌ای (مناطق).
     *
     * @param  array<int, string>  $items
     */
    private function pickDistinctScalar(array $items, ?string $prev, \Random\Randomizer $rng): string
    {
        $count = count($items);
        $pick = $items[$rng->getInt(0, $count - 1)];
        if ($count > 1 && $pick === $prev) {
            $pick = $items[$rng->getInt(0, $count - 1)];
        }

        return $pick;
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
