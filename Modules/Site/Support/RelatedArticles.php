<?php

namespace Modules\Site\Support;

use Illuminate\Support\Collection;
use Modules\Site\Models\Article;

/**
 * مقالاتِ مرتبط برای صفحاتِ کاتالوگ (دستگاه / برند / ترکیبی).
 *
 * منبع: رابطهٔ واقعیِ مقاله↔دستگاه/برند (pivotهای فعال)، نه متن‌کاوی.
 *   - forDevice : مقالاتِ متصل به آن دستگاه.
 *   - forBrand  : مقالاتِ متصل به آن برند.
 *   - forCombo  : مقالاتِ دستگاه×برند؛ اگر نبود، fallback به مقالاتِ خودِ دستگاه
 *                 (طبقِ خواست: «اگر صفحهٔ ترکیبی مقاله نداشت، از مقالاتِ دستگاه استفاده کن»).
 */
final class RelatedArticles
{
    public const LIMIT = 6;

    /** @return array<int, array<string, mixed>> */
    public static function forDevice(int $deviceId, int $limit = self::LIMIT): array
    {
        return self::shape(
            self::baseQuery()
                ->whereHas('activeDevices', fn ($q) => $q->where('crm_devices.id', $deviceId))
                ->limit($limit)->get()
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function forBrand(int $brandId, int $limit = self::LIMIT): array
    {
        return self::shape(
            self::baseQuery()
                ->whereHas('activeBrands', fn ($q) => $q->where('crm_brands.id', $brandId))
                ->limit($limit)->get()
        );
    }

    /**
     * ترکیبی: مقالاتِ متصل به «هم» این دستگاه «هم» این برند. اگر هیچ‌کدام نبود،
     * به مقالاتِ خودِ دستگاه برمی‌گردد.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forCombo(int $deviceId, int $brandId, int $limit = self::LIMIT): array
    {
        $combo = self::baseQuery()
            ->whereHas('activeDevices', fn ($q) => $q->where('crm_devices.id', $deviceId))
            ->whereHas('activeBrands', fn ($q) => $q->where('crm_brands.id', $brandId))
            ->limit($limit)->get();

        if ($combo->isNotEmpty()) {
            return self::shape($combo);
        }

        // fallback: مقالاتِ دستگاه (بدونِ محدودیتِ برند).
        return self::forDevice($deviceId, $limit);
    }

    private static function baseQuery()
    {
        return Article::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }

    /**
     * @param  Collection<int, Article>  $articles
     * @return array<int, array<string, mixed>>
     */
    private static function shape(Collection $articles): array
    {
        return $articles->map(fn (Article $a) => [
            'id' => (int) $a->id,
            'title' => $a->title,
            'slug' => $a->slug,
            'url' => '/blog/'.$a->slug,
            'excerpt' => $a->excerpt ? mb_substr(strip_tags((string) $a->excerpt), 0, 160) : null,
            'image' => $a->cover_image ? MediaUrl::resolve($a->cover_image) : null,
            'read_time' => $a->read_time_minutes ?: null,
            'published_at' => $a->published_at?->toDateString(),
        ])->values()->all();
    }
}
