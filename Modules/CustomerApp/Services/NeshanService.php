<?php

namespace Modules\CustomerApp\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * کلاینت سرویس REST نشان (neshan.org).
 *
 * دو سرویس: reverse geocode («تبدیل نقطه به آدرس») و geocode («تبدیل آدرس
 * به مختصات») — کلید service سمت سرور می‌ماند و از طریق proxy endpoint به
 * اپ سرویس داده می‌شود تا کلید لو نرود و سهمیه‌ی API قابل کنترل
 * (throttle + cache) باشد.
 *
 * مرجع: https://platform.neshan.org/api/reverse-geocoding
 *   GET https://api.neshan.org/v5/reverse?lat=..&lng=..
 * مرجع: https://platform.neshan.org/api/geocoding
 *   GET https://api.neshan.org/v6/geocoding?address=..
 *   Header: Api-Key: <SERVICE_KEY>
 *
 * ⚠️ نوع کلید مهم است: کلید باید از نوع «وب‌سرویس» با دسترسی
 * «تبدیل نقطه به آدرس» باشد — کلید Web SDK (نقشه) اینجا کار نمی‌کند
 * و نشان 483 ApiKeyTypeError برمی‌گرداند.
 */
class NeshanService
{
    private const CACHE_TTL = 86400;       // 24h — آدرس یک نقطه عوض نمی‌شود

    private const FAILURE_COOLDOWN = 60;   // 1min — جلوی کوبیدن مکرر روی خطا

    /** کدهای خطای نشان → پیام قابل‌فهم برای لاگ/دیباگ */
    private const ERROR_CODES = [
        400 => 'INVALID_ARGUMENT — پارامتر ورودی نامعتبر',
        470 => 'CoordinateParseError — مختصات نامعتبر',
        480 => 'KeyNotFound — کلید نامعتبر است یا در header ارسال نشده',
        481 => 'LimitExceeded — سهمیه‌ی کل مصرف شده',
        482 => 'RateExceeded — تعداد درخواست در دقیقه بیش از حد',
        483 => 'ApiKeyTypeError — نوع کلید با سرویس همخوانی ندارد (کلید Web را به‌جای کلید وب‌سرویس گذاشته‌اید؟)',
        484 => 'ApiWhiteListError — دامنه/IP در whitelist کلید نیست',
        485 => 'ApiServiceListError — سرویس «تبدیل نقطه به آدرس» برای این کلید فعال نیست',
    ];

    /** آخرین HTTP status خطای نشان — برای تفکیک خطای پیکربندی از خطای موقت. */
    private ?int $lastStatus = null;

    public function isConfigured(): bool
    {
        return (string) config('services.neshan.service_key') !== '';
    }

    /**
     * آیا آخرین شکست، خطای پیکربندی کلید بود؟ (نه خطای موقت شبکه/سهمیه)
     */
    public function lastFailureWasKeyMisconfiguration(): bool
    {
        return in_array($this->lastStatus, [480, 483, 484, 485], true);
    }

    /**
     * تبدیل مختصات به آدرس فارسی.
     *
     * مختصات تا ۵ رقم اعشار (دقت ~۱ متر) round می‌شود تا cache مؤثر باشد.
     * موفقیت ۲۴ ساعت cache می‌شود؛ شکست ۶۰ ثانیه (تا روی خطای تکراری،
     * هر درخواست کاربر یک round-trip ~۵۰۰ms به نشان نخورد).
     *
     * @return array<string, mixed>|null null اگر config نشده یا پاسخ نامعتبر بود
     */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $lat = round($lat, 5);
        $lng = round($lng, 5);
        $cacheKey = sprintf('neshan:reverse:%.5f,%.5f', $lat, $lng);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            if ($cached['ok'] ?? false) {
                return $cached['data'];
            }
            $this->lastStatus = $cached['status'] ?? null;

            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Api-Key' => (string) config('services.neshan.service_key')])
                ->get(rtrim((string) config('services.neshan.base_url'), '/').'/v5/reverse', [
                    'lat' => $lat,
                    'lng' => $lng,
                ]);

            if (! $response->successful()) {
                $this->lastStatus = $response->status();
                Log::warning('neshan.reverse_failed', [
                    'status' => $response->status(),
                    'reason' => self::ERROR_CODES[$response->status()] ?? 'unknown',
                    'body' => mb_substr($response->body(), 0, 500),
                    'lat' => $lat,
                    'lng' => $lng,
                ]);
                Cache::put($cacheKey, ['ok' => false, 'status' => $response->status()], self::FAILURE_COOLDOWN);

                return null;
            }

            $json = $response->json();

            // mapping مطابق مستندات رسمی v5/reverse
            $municipalityZone = $json['municipality_zone'] ?? null;
            $data = [
                'formatted_address' => $json['formatted_address'] ?? null,
                'province' => $json['state'] ?? null,
                'city' => $json['city'] ?? null,
                'neighbourhood' => $json['neighbourhood'] ?? null,
                'municipality_zone' => $municipalityZone,
                // برای نمایش مستقیم: «منطقه ۶» — اگر zone نبود، محله
                'district' => $municipalityZone !== null && $municipalityZone !== ''
                    ? 'منطقه '.$municipalityZone
                    : ($json['neighbourhood'] ?? null),
                'route' => $json['route_name'] ?? null,
                'place' => $json['place'] ?? null,
                'in_traffic_zone' => $json['in_traffic_zone'] ?? null,
                'in_odd_even_zone' => $json['in_odd_even_zone'] ?? null,
            ];

            Cache::put($cacheKey, ['ok' => true, 'data' => $data], self::CACHE_TTL);
            $this->lastStatus = null;

            return $data;
        } catch (\Throwable $e) {
            $this->lastStatus = null;
            Log::warning('neshan.reverse_exception', [
                'error' => $e->getMessage(),
                'lat' => $lat,
                'lng' => $lng,
            ]);
            Cache::put($cacheKey, ['ok' => false, 'status' => null], self::FAILURE_COOLDOWN);

            return null;
        }
    }

    /**
     * تبدیل آدرس متنی به مختصات (geocoding v6).
     *
     * ⚠️ روی همان کلید وب‌سرویس، سرویس «تبدیل آدرس به مختصات» هم باید در
     * پنل نشان فعال باشد — وگرنه 485 برمی‌گردد.
     *
     * پاسخ نشان: {"status":"OK","location":{"x":<lng>,"y":<lat>}}
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $address = trim((string) preg_replace('/\s+/u', ' ', $address));
        if ($address === '') {
            return null;
        }
        $cacheKey = 'neshan:geocode:'.md5($address);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            if ($cached['ok'] ?? false) {
                return $cached['data'];
            }
            $this->lastStatus = $cached['status'] ?? null;

            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Api-Key' => (string) config('services.neshan.service_key')])
                ->get(rtrim((string) config('services.neshan.base_url'), '/').'/v6/geocoding', [
                    'address' => $address,
                ]);

            if (! $response->successful()) {
                $this->lastStatus = $response->status();
                Log::error('neshan.geocode_failed', [
                    'status' => $response->status(),
                    'reason' => self::ERROR_CODES[$response->status()] ?? 'unknown',
                    'body' => mb_substr($response->body(), 0, 500),
                    'address' => $address,
                ]);
                Cache::put($cacheKey, ['ok' => false, 'status' => $response->status()], self::FAILURE_COOLDOWN);

                return null;
            }

            $json = $response->json();
            $lng = $json['location']['x'] ?? null;
            $lat = $json['location']['y'] ?? null;

            if (! is_numeric($lat) || ! is_numeric($lng)) {
                // آدرس پیدا نشد (status=NOT_FOUND یا بدنهٔ نامنتظره) — شکستِ
                // کوتاه cache می‌شود تا کلیکِ مکررِ اپراتور سهمیه نسوزاند.
                Cache::put($cacheKey, ['ok' => false, 'status' => null], self::FAILURE_COOLDOWN);
                $this->lastStatus = null;

                return null;
            }

            $data = ['lat' => (float) $lat, 'lng' => (float) $lng];
            Cache::put($cacheKey, ['ok' => true, 'data' => $data], self::CACHE_TTL);
            $this->lastStatus = null;

            return $data;
        } catch (\Throwable $e) {
            $this->lastStatus = null;
            Log::error('neshan.geocode_exception', [
                'error' => $e->getMessage(),
                'address' => $address,
            ]);
            Cache::put($cacheKey, ['ok' => false, 'status' => null], self::FAILURE_COOLDOWN);

            return null;
        }
    }

    /**
     * جستجوی مکان (search v1) — برای سرچِ خیابان/محله روی نقشهٔ ویزارد.
     *
     * lat/lng مرکزِ bias جستجوست (نتایجِ نزدیک‌تر بالاتر می‌آیند).
     * ⚠️ سرویس «جستجو» هم باید روی کلید فعال باشد؛ اگر نبود (485) null
     * برمی‌گردد و caller سراغ fallback می‌رود.
     *
     * @return array<int, array{title: string, address: string, lat: float, lng: float}>|null
     */
    public function searchPlaces(string $term, float $lat, float $lng): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $term = trim((string) preg_replace('/\s+/u', ' ', $term));
        if ($term === '') {
            return null;
        }
        $cacheKey = sprintf('neshan:search:%s:%.2f,%.2f', md5($term), $lat, $lng);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            if ($cached['ok'] ?? false) {
                return $cached['data'];
            }
            $this->lastStatus = $cached['status'] ?? null;

            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Api-Key' => (string) config('services.neshan.service_key')])
                ->get(rtrim((string) config('services.neshan.base_url'), '/').'/v1/search', [
                    'term' => $term,
                    'lat' => $lat,
                    'lng' => $lng,
                ]);

            if (! $response->successful()) {
                $this->lastStatus = $response->status();
                Log::error('neshan.search_failed', [
                    'status' => $response->status(),
                    'reason' => self::ERROR_CODES[$response->status()] ?? 'unknown',
                    'term' => $term,
                ]);
                Cache::put($cacheKey, ['ok' => false, 'status' => $response->status()], self::FAILURE_COOLDOWN);

                return null;
            }

            $data = [];
            foreach ((array) ($response->json()['items'] ?? []) as $item) {
                $x = $item['location']['x'] ?? null;
                $y = $item['location']['y'] ?? null;
                if (! is_numeric($x) || ! is_numeric($y)) {
                    continue;
                }
                $data[] = [
                    'title' => (string) ($item['title'] ?? ''),
                    'address' => (string) ($item['address'] ?? ''),
                    'lat' => (float) $y,
                    'lng' => (float) $x,
                ];
            }

            Cache::put($cacheKey, ['ok' => true, 'data' => $data], 21600); // 6h
            $this->lastStatus = null;

            return $data;
        } catch (\Throwable $e) {
            $this->lastStatus = null;
            Log::error('neshan.search_exception', ['error' => $e->getMessage(), 'term' => $term]);
            Cache::put($cacheKey, ['ok' => false, 'status' => null], self::FAILURE_COOLDOWN);

            return null;
        }
    }
}
