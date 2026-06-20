<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\Objection;
use Modules\CRM\Models\ServiceType;
use Modules\CustomerApp\Http\Resources\ObjectionResource;
use Modules\CustomerApp\Http\Resources\ServiceTypeResource;
use Modules\Site\Models\Banner;
use Modules\Site\Models\BannerZone;
use Modules\Site\Support\MediaUrl;

/**
 * Picker endpoints برای فرم ثبت سفارش اپ موبایل.
 *
 *   GET /v1/customer/services/types
 *   GET /v1/customer/services/objections?device_id=N
 *   GET /v1/customer/services/categories             ← دستگاه‌ها (UX = دسته‌بندی)
 *   GET /v1/customer/services/brands?category_id=N   ← برندها، اختیاری per-device
 *   GET /v1/customer/services/banners?placement=...  ← بنرها بر اساس zone slug
 *
 * همه public با cache بلندمدت.
 */
class ServiceController extends Controller
{
    public function types(): JsonResponse
    {
        $rows = ServiceType::query()->active()->ordered()->get();

        return response()->json([
            'data' => ServiceTypeResource::collection($rows),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    public function objections(Request $request): JsonResponse
    {
        $deviceId = $request->integer('device_id');

        // device_id اجباری — لیست عمومی همه‌ی ایرادات معنی ندارد
        if ($deviceId <= 0) {
            return response()->json([
                'message' => 'برای دیدن لیست ایرادات، باید ابتدا دستگاه انتخاب شود.',
                'code' => 'device_id_required',
            ], 422);
        }

        // اطمینان از وجود دستگاه — جلوگیری از بازگشت array خالی بی‌توضیح
        if (! \Modules\CRM\Models\Device::query()->whereKey($deviceId)->exists()) {
            return response()->json([
                'message' => 'دستگاه انتخاب‌شده معتبر نیست.',
                'code' => 'invalid_device',
            ], 422);
        }

        $rows = Objection::query()->active()->ordered()->forDevice($deviceId)->get();

        return response()->json([
            'data' => ObjectionResource::collection($rows),
            'meta' => [
                'device_id' => $deviceId,
                'total' => $rows->count(),
            ],
        ])->header('Cache-Control', 'public, max-age=1800');
    }

    /**
     * GET /v1/customer/services/categories
     *
     * در ادبیات فرانت، «category» همان دستگاه است (مثلاً «ماشین لباسشویی»).
     * در DB ما این‌ها crm_devices هستند. این endpoint alias می‌دهد تا فرانت
     * مجبور به دانستن تفاوت داخلی نباشد.
     */
    public function categories(): JsonResponse
    {
        // نمایش در اپ با فلگِ مستقلِ is_active_app کنترل می‌شود (جدا از is_active
        // که مخصوص نمایش در سایت است).
        $rows = Device::query()
            ->where('is_active_app', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'thumbnail', 'description', 'sort_order', 'is_featured']);

        $data = $rows->map(fn (Device $d) => [
            'id' => (int) $d->id,
            'name' => $d->name,
            'slug' => $d->slug,
            'icon' => $d->icon,
            'image' => MediaUrl::resolve($d->thumbnail),
            'description' => $d->description ? mb_substr(strip_tags($d->description), 0, 280) : null,
            'badge' => null,
            // ترتیب نمایش (پنل). لیست از قبل بر اساس is_featured DESC، سپس
            // sort_order ASC، سپس name مرتب شده است.
            'sort_order' => (int) $d->sort_order,
            'is_featured' => (bool) $d->is_featured,
        ])->values();

        return response()->json([
            'data' => $data,
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * GET /v1/customer/services/brands
     *
     * بدون category_id: همه‌ی برندهای فعال.
     * با category_id (=device_id): فقط برندهایی که این دستگاه را پشتیبانی می‌کنند.
     */
    public function brands(Request $request): JsonResponse
    {
        $categoryId = $request->integer('category_id');

        $query = Brand::query()
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($categoryId > 0) {
            $query->whereHas('devices', fn ($q) => $q->where('crm_devices.id', $categoryId));
        }

        $rows = $query->get(['id', 'name', 'slug', 'logo', 'tone', 'bg']);

        $data = $rows->map(fn (Brand $b) => [
            'id' => (int) $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'logo' => MediaUrl::resolve($b->logo),
            'icon' => null,
            'badge' => null,
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'total' => $data->count(),
            ],
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * GET /v1/customer/services/banners
     *
     * Frontend's `placement` به zone slug ما map می‌شود (مثلاً home_top).
     * بدون placement: همه‌ی bannerهای live گروه‌بندی‌شده بر اساس zone slug.
     * با placement: فقط آرایه‌ی bannerهای آن zone.
     */
    public function banners(Request $request): JsonResponse
    {
        $placement = trim((string) $request->query('placement', ''));

        if ($placement !== '') {
            $zone = BannerZone::query()->where('slug', $placement)->where('is_active', true)->first();
            if (! $zone) {
                return response()->json(['data' => []])
                    ->header('Cache-Control', 'public, max-age=300');
            }

            $banners = Banner::query()
                ->live()
                ->where('zone_id', $zone->id)
                ->with(['media', 'mediaMobile'])
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'data' => $banners->map(fn (Banner $b) => $this->shapeBanner($b, $placement))->values(),
            ])->header('Cache-Control', 'public, max-age=300');
        }

        // بدون placement: گروه‌بندی بر اساس zone slug
        $zones = BannerZone::query()->where('is_active', true)->get(['id', 'slug']);
        $bannersByZone = Banner::query()
            ->live()
            ->whereIn('zone_id', $zones->pluck('id'))
            ->with(['media', 'mediaMobile'])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('zone_id');

        $grouped = [];
        foreach ($zones as $zone) {
            $items = $bannersByZone->get($zone->id, collect());
            $grouped[$zone->slug] = $items->map(fn (Banner $b) => $this->shapeBanner($b, $zone->slug))->values();
        }

        return response()->json([
            'data' => $grouped,
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeBanner(Banner $b, string $placement): array
    {
        // اولویت: media → image_url (raw DB). در هر دو حالت absolute URL برمی‌گردد.
        $imageUrl = null;
        if ($b->media) {
            $imageUrl = $b->media->url();
        } elseif ($b->image_url) {
            $imageUrl = MediaUrl::resolve($b->image_url);
        }

        return [
            'id' => $b->id,
            'title' => $b->title,
            'image_url' => $imageUrl,
            'link_url' => $b->link_url,
            'placement' => $placement,
            'active' => (bool) $b->is_published,
            'order' => (int) $b->sort_order,
        ];
    }
}
