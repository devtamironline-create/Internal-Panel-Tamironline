<?php

namespace Modules\Site\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Site\Models\Banner;
use Modules\Site\Models\BannerZone;

/**
 * مدیریت cache زون‌های بنر + اطلاع‌رسانی به فرانت Next.js.
 *
 *   - keyForZone: ساخت کلید استاندارد cache
 *   - forgetZone: حذف کلید + (اختیاری) POST webhook به فرانت برای revalidateTag
 *   - forgetAll: همه‌ی زون‌ها (وقتی Banner Zone خاصی مشخص نیست)
 */
final class BannerCache
{
    public static function keyForZone(string $zoneSlug): string
    {
        return 'banners:zone:'.$zoneSlug;
    }

    /**
     * فراموش‌کردن یک زون + webhook به فرانت برای revalidateTag.
     */
    public static function forgetZone(string $zoneSlug): void
    {
        Cache::forget(self::keyForZone($zoneSlug));
        self::pingFrontend("banner:zone:{$zoneSlug}");
    }

    /**
     * فراموش‌کردن همه‌ی زون‌ها (مثلاً وقتی Banner زون قبلی و جدید متفاوت دارد).
     */
    public static function forgetAll(): void
    {
        $slugs = BannerZone::query()->pluck('slug')->all();
        foreach ($slugs as $slug) {
            Cache::forget(self::keyForZone($slug));
        }
        self::pingFrontend('banner:all');
    }

    /**
     * فراموش‌کردن زون قبلی + جدید برای یک Banner که zone_id آن عوض شده.
     */
    public static function forgetForBanner(Banner $banner, ?int $oldZoneId = null): void
    {
        $zoneIds = array_filter([$banner->zone_id, $oldZoneId]);
        if (empty($zoneIds)) {
            return;
        }
        $slugs = BannerZone::query()->whereIn('id', $zoneIds)->pluck('slug')->all();
        foreach ($slugs as $slug) {
            self::forgetZone($slug);
        }
    }

    /**
     * POST به Next.js برای revalidateTag فوری (همراه با fallback TTL backend).
     * Config: services.site_revalidation.url + services.site_revalidation.secret
     */
    private static function pingFrontend(string $tag): void
    {
        $url = config('services.site_revalidation.url');
        $secret = config('services.site_revalidation.secret');
        if (! $url || ! $secret) {
            return;
        }
        try {
            Http::timeout(2)->withHeaders(['X-Revalidation-Secret' => $secret])
                ->post($url, ['tag' => $tag]);
        } catch (\Throwable $e) {
            Log::warning('Site revalidation webhook failed', ['tag' => $tag, 'error' => $e->getMessage()]);
        }
    }
}
