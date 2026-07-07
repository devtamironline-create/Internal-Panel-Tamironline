<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;

/**
 * فعالیت‌های زنده‌ی سایت — دیتای واقع‌نمایانه اما فقط از ترکیب‌های معتبر.
 *
 * نکته‌ی کلیدی: برند فقط وقتی به یک دستگاه نسبت داده می‌شود که «صفحه‌ی ترکیبیِ
 * فعال» (DeviceBrandPage با is_active=true) برای آن جفت وجود داشته باشد. این
 * تضمین می‌کند هیچ‌وقت برندی به دستگاهی که نمی‌سازد نسبت داده نشود (مثلِ
 * «آبسال + ماشین لباسشویی»). اگر دستگاهی هیچ ترکیبِ فعالی نداشته باشد، فقط
 * نام دستگاه ارسال می‌شود (که همیشه درست است).
 *
 * منبع داده: device/brandهای فعال + DeviceBrandPageهای فعال + مشکلاتِ واقعیِ
 * هر دستگاه (فیلدِ issues) + مناطقِ config('site.activity-areas') + تولید
 * تصادفیِ minutes_ago/status با seedِ مبتنی بر دقیقه (هم‌خوان با کش ۶۰ ثانیه‌ای).
 *
 * مشکل (problem) فقط وقتی به آیتم اضافه می‌شود که برای آن دستگاه در پنل تعریف
 * شده باشد — هیچ‌وقت مشکلِ ساختگی تولید نمی‌شود.
 *
 * today_count («تعمیر موفقِ امروز»): عددِ ساختگی اما طبیعی که از ۲۰ در ابتدای
 * روز شروع می‌شود و با گذرِ زمان (منحنیِ نرمِ ساعاتِ کاری) تا حداکثر ۱۰۰ بالا
 * می‌رود؛ شبانه روی کفِ ۲۰، نیمه‌شب ریست. در طولِ روز هیچ‌وقت کم نمی‌شود
 * (یکنواخت صعودی) و per-context است: صفحهٔ اصلی تا ۱۰۰، صفحاتِ دستگاه/برند/
 * ترکیبی سقفِ کمتر (زیرمجموعه‌ی منطقیِ کلِ سایت) تا واقع‌بینانه بماند.
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

        // شمارِ «تعمیر موفقِ امروز» — ساختگی اما طبیعی، per-context (در همهٔ
        // مسیرهای خروج یکسان است؛ یک‌بار حساب می‌شود).
        $todayCount = $this->todayCount($deviceSlug, $brandSlug);

        $devices = $this->devices($deviceSlug);
        $brand = $brandSlug ? Brand::query()
            ->where('slug', $brandSlug)
            ->where('is_active', true)
            ->first(['id', 'name', 'slug'])
            : null;

        if ($devices->isEmpty() || ($brandSlug && ! $brand)) {
            return $this->respond([], $todayCount);
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
                return $this->respond([], $todayCount);
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
        $prevProblem = null;
        for ($i = 0; $i < $limit; $i++) {
            $device = $this->pickDistinct($devices, $prevDeviceId, $rng, fn ($d) => $d->id);
            $prevDeviceId = $device->id;

            $area = $this->pickDistinctScalar($areas, $prevArea, $rng);
            $prevArea = $area;

            // مشکلِ واقعیِ این دستگاه (اگر تعریف شده باشد) — هیچ‌وقت مشکلِ ساختگی
            // ساخته نمی‌شود؛ نبودِ مشکل ⇒ متن فقط «تعمیر دستگاه/برند» می‌ماند.
            $problems = $this->problemTitles($device);
            $problem = $problems === [] ? null : $this->pickDistinctScalar($problems, $prevProblem, $rng);
            $prevProblem = $problem;

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
            $timeLabel = $this->timeLabel($minutesAgo);

            // پایه: «تعمیر دستگاه [برند]» — و اگر مشکلِ واقعی داشت، با « – مشکل».
            $base = $rowBrand
                ? sprintf('تعمیر %s %s', $device->name, $rowBrand->name)
                : sprintf('تعمیر %s', $device->name);
            $label = $problem ? sprintf('%s – %s', $base, $problem) : $base;

            $data[] = [
                'id' => 'act_'.substr(md5($seedKey.$i), 0, 16),
                'device_slug' => $device->slug,
                'device_name' => $device->name,
                'device_label' => $label,
                'brand' => $rowBrand?->name,
                'brand_slug' => $rowBrand?->slug,
                'brand_label' => $rowBrand?->name,
                'problem' => $problem,
                'area' => $area,
                'status' => $status,
                'minutes_ago' => $minutesAgo,
                // برچسبِ آماده‌ی نمایش: زیرِ یک ساعت «X دقیقه پیش»، بالاتر «X ساعت
                // پیش» (تا سقفِ ۳ ساعت) — فرانت همین را مستقیم نشان دهد.
                'time_label' => $timeLabel,
            ];
        }

        // مرتب‌سازی: جدیدترها اول
        usort($data, fn ($a, $b) => $a['minutes_ago'] <=> $b['minutes_ago']);

        return $this->respond($data, $todayCount);
    }

    /**
     * پاسخ استاندارد + شمارِ «تعمیر موفقِ امروز» (top-level، backward-compatible:
     * data همچنان آرایه‌ی آیتم‌هاست).
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    private function respond(array $data, int $todayCount): JsonResponse
    {
        return response()
            ->json([
                'data' => $data,
                'today_count' => $todayCount,
            ])
            ->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }

    /**
     * شمارِ «تعمیر موفقِ امروز» — ساختگی اما طبیعی و واقع‌بینانه.
     *
     * - از ۲۰ شروع می‌شود (کفِ روز) و با گذرِ زمان تا سقفِ مشخص بالا می‌رود.
     * - رشد روی منحنیِ نرمِ smoothstep در «ساعاتِ کاری» (≈۶ صبح تا نیمه‌شب) است:
     *   سحرگاه آرام، روز سریع‌تر، شب فلات — مثلِ روندِ واقعیِ سفارش‌ها.
     * - یکنواخت صعودی است؛ در طولِ روز هیچ‌وقت کم نمی‌شود (هم‌خوان با کش ۶۰ث؛
     *   هر چند دقیقه یک پله بالا می‌رود). نیمه‌شب به ۲۰ ریست می‌شود.
     * - per-context: صفحهٔ اصلی تا ۱۰۰؛ صفحاتِ دستگاه/برند/ترکیبی سقفِ کمتر تا
     *   به‌صورتِ زیرمجموعه‌ی منطقیِ کلِ سایت واقع‌بینانه بمانند.
     */
    private function todayCount(?string $deviceSlug, ?string $brandSlug): int
    {
        $tz = config('app.timezone', 'Asia/Tehran');
        $now = now($tz);
        $secondsIntoDay = $now->getTimestamp() - $now->copy()->startOfDay()->getTimestamp();
        $dayFraction = max(0.0, min(1.0, $secondsIntoDay / 86400));

        // پنجره‌ی کاری: از ۶ صبح تا نیمه‌شب رشد می‌کند؛ پیش از آن روی کفِ ۲۰.
        $windowStart = 6 / 24;
        $w = max(0.0, min(1.0, ($dayFraction - $windowStart) / (1.0 - $windowStart)));
        // smoothstep — رشدِ نرمِ طبیعی (بدونِ شروع/پایانِ ناگهانی).
        $progress = $w * $w * (3 - 2 * $w);

        // سقفِ هر context (واقع‌بینانه: زیرمجموعه‌ها کمتر از کلِ سایت).
        $ceil = 100;                               // صفحه‌ی اصلی
        if ($deviceSlug && $brandSlug) {
            $ceil = 55;                            // صفحه‌ی ترکیبی
        } elseif ($deviceSlug) {
            $ceil = 72;                            // صفحه‌ی دستگاه
        } elseif ($brandSlug) {
            $ceil = 64;                            // صفحه‌ی برند
        }

        // تنوعِ کوچکِ قطعی برای هر صفحه (نه وابسته به زمان) تا اعداد یک‌نواخت نباشند
        // ولی ترتیبِ منطقی و یکنواختیِ زمانی حفظ شود.
        $ctx = ($deviceSlug ?? '').'|'.($brandSlug ?? '');
        if ($ctx !== '|') {
            $ceil -= (int) (crc32($ctx) % 7);      // ۰ تا ۶ واحد کمتر، مخصوصِ همان صفحه
        }

        $count = 20 + (int) round(($ceil - 20) * $progress);

        return max(20, min(100, $count));
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

        // issues = مشکلاتِ رایجِ واقعیِ هر دستگاه ([{title, description}])؛ برای
        // متنِ فعالیت زنده فقط عنوان‌های غیرخالی استفاده می‌شوند.
        return $query->get(['id', 'name', 'slug', 'issues']);
    }

    /**
     * عنوان‌های غیرخالیِ مشکلاتِ واقعیِ یک دستگاه (از فیلدِ issues).
     * فقط رشته‌ها/{title:...} با عنوانِ ناتهی برگردانده می‌شوند.
     *
     * @return array<int, string>
     */
    private function problemTitles(Device $device): array
    {
        $issues = $device->issues;
        if (! is_array($issues)) {
            return [];
        }

        $out = [];
        foreach ($issues as $issue) {
            $title = is_array($issue) ? ($issue['title'] ?? '') : (is_string($issue) ? $issue : '');
            $title = trim((string) $title);
            if ($title !== '') {
                $out[] = $title;
            }
        }

        return $out;
    }

    /**
     * توزیع زمان واقع‌نمایانه با سقفِ «۳ ساعت» — ۶۰٪ احتمال در ۳۰ دقیقه اخیر،
     * ۳۰٪ بین ۳۰ تا ۹۰ دقیقه، ۱۰٪ بین ۹۰ دقیقه تا ۳ ساعت. سفارشِ قدیمی‌تر از
     * ۳ ساعت هرگز نمایش داده نمی‌شود.
     */
    private function pickMinutesAgo(\Random\Randomizer $rng): int
    {
        $r = $rng->getInt(0, 99);
        if ($r < 60) {
            return $rng->getInt(1, 30);
        }
        if ($r < 90) {
            return $rng->getInt(30, 90);
        }

        return $rng->getInt(90, 180);
    }

    /**
     * برچسبِ فارسیِ زمان: زیرِ ۶۰ دقیقه «X دقیقه پیش»، از آن به بعد بر حسبِ
     * «ساعت» (۱ تا ۳ ساعت پیش) — به اسمِ ساعت، نه دقیقه.
     */
    private function timeLabel(int $minutesAgo): string
    {
        $fa = fn (int $n) => strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        if ($minutesAgo < 60) {
            return $fa(max(1, $minutesAgo)).' دقیقه پیش';
        }

        $hours = min(3, max(1, (int) round($minutesAgo / 60)));

        return $hours === 1 ? 'حدود یک ساعت پیش' : $fa($hours).' ساعت پیش';
    }
}
