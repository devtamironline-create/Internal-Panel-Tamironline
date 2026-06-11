<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\City;
use Modules\CRM\Models\Province;
use Modules\CustomerApp\Services\NeshanService;

/**
 * GET /v1/customer/locations/states
 * GET /v1/customer/locations/cities
 * GET /v1/customer/locations/districts?city_id=N
 * GET /v1/customer/locations/reverse-geocode?lat=..&lng=.. (private)
 *
 * فلوی اپ: استان از کاربر پرسیده نمی‌شود. کاربر فقط «شهر» و سپس «منطقه»
 * انتخاب می‌کند؛ استان سمت سرور از شهر تشخیص داده می‌شود.
 *
 * فقط شهرهای استان‌های سرویس‌دهی نمایش داده می‌شوند — لیست استان‌ها از
 * Setting «customer_app_service_provinces» (پیش‌فرض «1,18» = تهران، البرز).
 *
 * فقط رکوردهای is_active=true بازگشت می‌گیرند تا ادمین بتواند مناطق
 * ساخته‌نشده را با toggle کردن، از picker اپ حذف کند بدون حذف داده.
 */
class LocationController extends Controller
{
    public function states(): JsonResponse
    {
        $rows = Province::query()
            ->active()
            ->whereIn('id', $this->serviceProvinceIds())
            ->ordered()
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'data' => $rows->map(fn (Province $p) => [
                'id' => (int) $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * شهرهای اصلی (بدون ردیف‌های منطقه) استان‌های سرویس‌دهی.
     * state_id اختیاری برای سازگاری با نسخه‌های قبلی فرانت.
     */
    public function cities(Request $request): JsonResponse
    {
        $stateId = $request->integer('state_id');

        $query = City::query()
            ->active()
            ->mainCities()
            ->whereIn('province_id', $this->serviceProvinceIds())
            ->ordered();

        if ($stateId > 0) {
            $query->where('province_id', $stateId);
        }

        $rows = $query->withCount(['districts as districts_count' => fn ($q) => $q->where('is_active', true)])
            ->get(['id', 'province_id', 'name', 'slug']);

        return response()->json([
            'data' => $rows->map(fn (City $c) => [
                'id' => (int) $c->id,
                'state_id' => (int) $c->province_id,
                'name' => $c->name,
                'slug' => $c->slug,
                'has_districts' => ((int) $c->districts_count) > 0,
            ])->values(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * مناطق یک شهر — مثل ۲۲ منطقه تهران. city_id اجباری.
     */
    public function districts(Request $request): JsonResponse
    {
        $cityId = $request->integer('city_id');

        if ($cityId <= 0) {
            return response()->json([
                'message' => 'برای دیدن لیست مناطق، ابتدا شهر را انتخاب کنید.',
                'code' => 'city_id_required',
            ], 422);
        }

        $city = City::query()->mainCities()->whereKey($cityId)->first(['id', 'province_id']);
        if (! $city) {
            return response()->json([
                'message' => 'شهر انتخاب‌شده معتبر نیست.',
                'code' => 'invalid_city',
            ], 422);
        }

        $rows = $city->districts()->where('is_active', true)->ordered()
            ->get(['id', 'province_id', 'parent_city_id', 'name', 'slug']);

        return response()->json([
            'data' => $rows->map(fn (City $d) => [
                'id' => (int) $d->id,
                'city_id' => (int) $d->parent_city_id,
                'name' => $d->name,
                'slug' => $d->slug,
            ])->values(),
            'meta' => [
                'city_id' => (int) $city->id,
                'total' => $rows->count(),
            ],
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * تبدیل نقطه به آدرس از طریق نشان — کلید سمت سرور می‌ماند.
     * Private (auth:sanctum) + throttle تا سهمیه‌ی API هدر نرود.
     */
    public function reverseGeocode(Request $request, NeshanService $neshan): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ], [
            'lat.required' => 'مختصات نقطه (lat) الزامی است.',
            'lng.required' => 'مختصات نقطه (lng) الزامی است.',
        ]);

        if (! $neshan->isConfigured()) {
            return response()->json([
                'message' => 'سرویس نقشه هنوز فعال نشده است.',
                'code' => 'neshan_not_configured',
            ], 503);
        }

        $result = $neshan->reverseGeocode((float) $data['lat'], (float) $data['lng']);

        if ($result === null) {
            // خطای پیکربندی کلید (480/483/484/485) را از خطای موقت جدا کن —
            // فرانت پیام مناسب نشان می‌دهد و ادمین در لاگ دلیل دقیق را می‌بیند.
            if ($neshan->lastFailureWasKeyMisconfiguration()) {
                return response()->json([
                    'message' => 'پیکربندی سرویس نقشه اشتباه است. (نوع کلید نشان را بررسی کنید — جزئیات در لاگ سرور)',
                    'code' => 'neshan_key_misconfigured',
                ], 503);
            }

            return response()->json([
                'message' => 'تبدیل مختصات به آدرس ناموفق بود. دوباره تلاش کنید.',
                'code' => 'reverse_geocode_failed',
            ], 502);
        }

        return response()->json([
            'data' => $result,
        ])->header('Cache-Control', 'private, max-age=86400');
    }

    /**
     * @return array<int>
     */
    private function serviceProvinceIds(): array
    {
        $raw = (string) Setting::get('customer_app_service_provinces', '1,18');

        $ids = array_values(array_filter(array_map(
            fn ($v) => (int) trim($v),
            explode(',', $raw)
        ), fn ($v) => $v > 0));

        // اگر تنظیم خالی/خراب بود، پیش‌فرض تهران + البرز
        return $ids ?: [1, 18];
    }
}
