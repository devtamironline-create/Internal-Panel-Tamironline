<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\CustomerApp\Services\NeshanService;

/**
 * جستجوی خیابان/محله برای نقشهٔ ویزارد ثبت سفارش — اپراتور وسط تماس
 * تلفنی باید سریع محلِ آدرس را پیدا کند.
 *
 * اول جستجوی نشان (بهترین دادهٔ ایران)؛ اگر روی کلید فعال نبود یا خطا
 * داد، fallback رایگان Nominatim (OSM) — همان نقشه‌ای که کاشی‌هایش را در
 * ویزارد نشان می‌دهیم. نتیجه‌ها cache می‌شوند تا سهمیه نسوزد.
 */
class PlaceSearch
{
    public function __construct(private NeshanService $neshan) {}

    /**
     * @return array<int, array{title: string, address: string, lat: float, lng: float}>
     */
    public function search(string $term, ?float $lat, ?float $lng): array
    {
        $term = trim((string) preg_replace('/\s+/u', ' ', $term));
        if (mb_strlen($term) < 3) {
            return [];
        }

        // bias پیش‌فرض: تهران — عمدهٔ تماس‌ها.
        $results = $this->neshan->searchPlaces($term, $lat ?? 35.6892, $lng ?? 51.3890);
        if (is_array($results) && $results !== []) {
            return array_slice($results, 0, 6);
        }

        return $this->nominatim($term, $lat, $lng);
    }

    /**
     * تبدیل آدرس به مختصات — مسیرِ رایگان (Nominatim). وقتی سهمیهٔ نشان
     * تمام شده (481) یا سرویسش فعال نیست، این جایگزین می‌شود.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocodeFree(string $address, ?float $lat, ?float $lng): ?array
    {
        $results = $this->nominatim($address, $lat, $lng);

        return $results === []
            ? null
            : ['lat' => $results[0]['lat'], 'lng' => $results[0]['lng']];
    }

    /**
     * تبدیل نقطه به آدرس — مسیرِ رایگان (Nominatim reverse) با همان شکلِ
     * خروجیِ NeshanService::reverseGeocode تا ویزارد فرقی نبیند.
     *
     * برای تهران، فیلد city_district در OSM معمولاً «منطقه N» است —
     * municipality_zone از همان استخراج می‌شود.
     *
     * @return array<string, mixed>|null
     */
    public function reverseFree(float $lat, float $lng): ?array
    {
        $lat = round($lat, 5);
        $lng = round($lng, 5);
        $cacheKey = sprintf('placesearch:osm_rev:%.5f,%.5f', $lat, $lng);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached === [] ? null : $cached;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'TamirOnline-Panel/1.0 (+https://panel.tamironline.com)'])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'jsonv2',
                    'accept-language' => 'fa',
                    'zoom' => 16,
                ]);

            if (! $response->successful()) {
                Log::error('placesearch.osm_reverse_failed', ['status' => $response->status(), 'lat' => $lat, 'lng' => $lng]);
                Cache::put($cacheKey, [], 60);

                return null;
            }

            $json = (array) $response->json();
            $addr = (array) ($json['address'] ?? []);
            if ($addr === []) {
                Cache::put($cacheKey, [], 600);

                return null;
            }

            $zone = $this->zoneFromOsmAddress($addr);
            $neighbourhood = $addr['neighbourhood'] ?? $addr['suburb'] ?? $addr['quarter'] ?? null;
            $data = [
                'formatted_address' => (string) ($json['display_name'] ?? ''),
                'province' => $addr['state'] ?? null,
                'city' => $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? null,
                'neighbourhood' => $neighbourhood,
                'municipality_zone' => $zone,
                'district' => $zone !== null ? 'منطقه '.$zone : $neighbourhood,
                'route' => $addr['road'] ?? null,
                'place' => null,
                'in_traffic_zone' => null,
                'in_odd_even_zone' => null,
            ];

            Cache::put($cacheKey, $data, 86400);

            return $data;
        } catch (\Throwable $e) {
            Log::error('placesearch.osm_reverse_exception', ['error' => $e->getMessage(), 'lat' => $lat, 'lng' => $lng]);
            Cache::put($cacheKey, [], 60);

            return null;
        }
    }

    /** استخراجِ شمارهٔ منطقهٔ شهرداری از فیلدهای district مانندِ OSM. */
    private function zoneFromOsmAddress(array $addr): ?string
    {
        foreach (['city_district', 'district', 'borough', 'suburb'] as $field) {
            $value = (string) ($addr[$field] ?? '');
            if ($value === '') {
                continue;
            }
            $latin = strtr($value, [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            ]);
            // «منطقه ۶»، «District 6»، «منطقه 6 تهران» …
            if (preg_match('/(?:منطقه|district)\s*(\d{1,2})/ui', $latin, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{title: string, address: string, lat: float, lng: float}>
     */
    private function nominatim(string $term, ?float $lat, ?float $lng): array
    {
        $cacheKey = sprintf('placesearch:osm:%s:%s', md5($term), $lat !== null ? sprintf('%.2f,%.2f', $lat, $lng) : 'ir');
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $params = [
                'q' => $term,
                'format' => 'jsonv2',
                'limit' => 6,
                'accept-language' => 'fa',
                'countrycodes' => 'ir',
            ];
            if ($lat !== null && $lng !== null) {
                // محدود به حدودِ شهرِ انتخابی تا «ولی‌عصر» شهرِ دیگری نیاید.
                $params['viewbox'] = sprintf('%.4f,%.4f,%.4f,%.4f', $lng - 0.45, $lat + 0.35, $lng + 0.45, $lat - 0.35);
                $params['bounded'] = 1;
            }

            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'TamirOnline-Panel/1.0 (+https://panel.tamironline.com)'])
                ->get('https://nominatim.openstreetmap.org/search', $params);

            if (! $response->successful()) {
                Log::error('placesearch.nominatim_failed', ['status' => $response->status(), 'term' => $term]);
                Cache::put($cacheKey, [], 60);

                return [];
            }

            $data = [];
            foreach ((array) $response->json() as $item) {
                if (! is_numeric($item['lat'] ?? null) || ! is_numeric($item['lon'] ?? null)) {
                    continue;
                }
                $display = (string) ($item['display_name'] ?? '');
                $data[] = [
                    'title' => (string) ($item['name'] ?? '') !== '' ? (string) $item['name'] : mb_substr($display, 0, 60),
                    'address' => $display,
                    'lat' => (float) $item['lat'],
                    'lng' => (float) $item['lon'],
                ];
            }

            Cache::put($cacheKey, $data, $data === [] ? 600 : 21600);

            return $data;
        } catch (\Throwable $e) {
            Log::error('placesearch.nominatim_exception', ['error' => $e->getMessage(), 'term' => $term]);
            Cache::put($cacheKey, [], 60);

            return [];
        }
    }
}
