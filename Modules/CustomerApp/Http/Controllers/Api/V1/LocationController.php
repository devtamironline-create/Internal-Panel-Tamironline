<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
 * منبع واحد نمایش در اپ: فقط رکوردهای is_active=true بازگشت می‌گیرند —
 * یعنی همان تاگل «نمایش در اپ» در صفحات استان/شهر/منطقهٔ پنل. شهر و
 * منطقه‌ای که استانِ والدش غیرفعال باشد هم نمایش داده نمی‌شود.
 */
class LocationController extends Controller
{
    public function states(): JsonResponse
    {
        $rows = Province::query()
            ->active()
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
            ->whereHas('province', fn ($q) => $q->active())
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

        $city = City::query()
            ->mainCities()
            ->active()
            ->whereHas('province', fn ($q) => $q->active())
            ->whereKey($cityId)
            ->first(['id', 'province_id']);
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

        // تشخیص spam فرانت: اگر همین کاربر دقیقاً همان مختصات را در ۵ ثانیه
        // اخیر بیش از ۳ بار خواست، هشدار در لاگ — بدون 4xx کردن (cache هنوز
        // سرویس می‌دهد) ولی ادمین می‌فهمد فرانت در حال spam است.
        $userId = optional($request->user())->id ?: 'guest';
        $sigKey = sprintf(
            'neshan:rev_sig:%s:%.5f,%.5f',
            $userId,
            round((float) $data['lat'], 5),
            round((float) $data['lng'], 5)
        );
        $count = (int) \Illuminate\Support\Facades\Cache::get($sigKey, 0);
        \Illuminate\Support\Facades\Cache::put($sigKey, $count + 1, 5);
        if ($count + 1 === 5) {
            \Illuminate\Support\Facades\Log::warning('neshan.reverse_spam_detected', [
                'user_id' => $userId,
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'count_in_5s' => $count + 1,
                'hint' => 'Frontend likely missing debounce or has a state/effect loop.',
            ]);
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

}
