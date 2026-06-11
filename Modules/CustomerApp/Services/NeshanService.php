<?php

namespace Modules\CustomerApp\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * کلاینت سرویس REST نشان (neshan.org).
 *
 * فعلاً فقط reverse geocode («تبدیل نقطه به آدرس») — کلید service سمت سرور
 * می‌ماند و از طریق proxy endpoint به اپ سرویس داده می‌شود تا کلید لو نرود
 * و سهمیه‌ی API قابل کنترل (throttle + cache) باشد.
 *
 * مرجع: https://platform.neshan.org/api/reverse-geocoding
 *   GET https://api.neshan.org/v5/reverse?lat=..&lng=..
 *   Header: Api-Key: <SERVICE_KEY>
 */
class NeshanService
{
    private const CACHE_TTL = 86400; // 24h — آدرس یک نقطه عوض نمی‌شود

    public function isConfigured(): bool
    {
        return (string) config('services.neshan.service_key') !== '';
    }

    /**
     * تبدیل مختصات به آدرس فارسی.
     *
     * مختصات تا ۵ رقم اعشار (دقت ~۱ متر) round می‌شود تا cache مؤثر باشد.
     *
     * @return array{formatted_address: ?string, province: ?string, city: ?string, district: ?string, route: ?string}|null
     *                                                                                                                     null اگر سرویس config نشده یا پاسخ نامعتبر بود
     */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $lat = round($lat, 5);
        $lng = round($lng, 5);
        $cacheKey = sprintf('neshan:reverse:%.5f,%.5f', $lat, $lng);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lat, $lng) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders(['Api-Key' => (string) config('services.neshan.service_key')])
                    ->get(rtrim((string) config('services.neshan.base_url'), '/').'/v5/reverse', [
                        'lat' => $lat,
                        'lng' => $lng,
                    ]);

                if (! $response->successful()) {
                    Log::warning('neshan.reverse_failed', [
                        'status' => $response->status(),
                        'lat' => $lat,
                        'lng' => $lng,
                    ]);

                    return null;
                }

                $json = $response->json();

                return [
                    'formatted_address' => $json['formatted_address'] ?? null,
                    'province' => $json['state'] ?? null,
                    'city' => $json['city'] ?? null,
                    'district' => $json['municipality_zone'] ?? ($json['neighbourhood'] ?? null),
                    'route' => $json['route_name'] ?? null,
                ];
            } catch (\Throwable $e) {
                Log::warning('neshan.reverse_exception', [
                    'error' => $e->getMessage(),
                    'lat' => $lat,
                    'lng' => $lng,
                ]);

                return null;
            }
        });
    }
}
