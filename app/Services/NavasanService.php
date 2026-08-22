<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * کلاینت وب‌سرویس نوسان (api.navasan.tech) برای صندوق سرمایه.
 *
 * همهٔ آیتم‌های config/investment.php با یک درخواست گرفته و چند دقیقه کش
 * می‌شوند. اگر وب‌سرویس از دسترس خارج شود، آخرین پاسخِ سالم (تا یک هفته)
 * برگردانده می‌شود تا صفحهٔ صندوق نخوابد — تاریخِ قیمت هم همراهش هست تا
 * UI بگوید قیمت مالِ چه زمانی است.
 */
class NavasanService
{
    private const CACHE_KEY = 'investment:navasan:fresh';

    private const STALE_KEY = 'investment:navasan:stale';

    public function isConfigured(): bool
    {
        return (string) config('investment.navasan.api_key') !== '';
    }

    /** دور انداختنِ کشِ تازه و گرفتنِ قیمتِ نو — برای زمان‌بندِ روزانه. */
    public static function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        app(self::class)->prices();
    }

    /**
     * @return array{prices: array<string, int>, fetched_at: string|null}
     *                                                                    prices: item نوسان => قیمت به تومان
     */
    public function prices(): array
    {
        if (! $this->isConfigured()) {
            return ['prices' => [], 'fetched_at' => null];
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        // دارایی‌های با قیمتِ ثابت (item=null، مثل پول نقد) از درخواستِ نوسان
        // حذف می‌شوند — filter تا item خالی در query string ننشیند.
        $items = collect(config('investment.assets'))->pluck('item')->filter()->unique()->values();

        try {
            $response = Http::timeout(15)->get(
                rtrim((string) config('investment.navasan.base_url'), '/').'/latest/',
                ['api_key' => (string) config('investment.navasan.api_key'), 'item' => $items->implode(',')]
            );

            if ($response->successful() && is_array($response->json())) {
                $prices = [];
                foreach ($response->json() as $item => $row) {
                    $value = is_array($row) ? ($row['value'] ?? null) : $row;
                    if (is_numeric($value)) {
                        $prices[$item] = (int) round((float) $value);
                    }
                }

                if ($prices !== []) {
                    $data = ['prices' => $prices, 'fetched_at' => now()->toIso8601String()];
                    Cache::put(self::CACHE_KEY, $data, (int) config('investment.navasan.cache_seconds', 300));
                    Cache::put(self::STALE_KEY, $data, now()->addWeek());

                    return $data;
                }
            }

            Log::warning('investment.navasan_bad_response', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);
        } catch (\Throwable $e) {
            Log::warning('investment.navasan_failed', ['error' => $e->getMessage()]);
        }

        return Cache::get(self::STALE_KEY, ['prices' => [], 'fetched_at' => null]);
    }
}
