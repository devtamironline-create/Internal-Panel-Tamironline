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
