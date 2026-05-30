<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Site\Models\Banner;
use Modules\Site\Models\BannerZone;
use Modules\Site\Support\BannerCache;

/**
 * Public API بنر — `/v1/banners?zone={slug}`
 *
 * استراتژی بهینه‌سازی:
 *   1. هر زون cache key مستقل دارد (`banners:zone:{slug}`) با TTL=60s
 *   2. invalidate خودکار با Banner saved/deleted observer (BannerCache::forgetZone)
 *   3. ETag و Last-Modified از max(updated_at) برای 304 conditional GET
 *   4. payload سبک (فقط فیلدهای مورد نیاز فرانت) با URLهای variant
 */
class BannerController extends Controller
{
    public function byZone(Request $request, string $zoneSlug): JsonResponse
    {
        $key = BannerCache::keyForZone($zoneSlug);

        $payload = Cache::remember($key, 60, function () use ($zoneSlug) {
            $zone = BannerZone::query()->active()->where('slug', $zoneSlug)->first();
            if (! $zone) {
                return null;
            }
            $banners = Banner::query()->live()
                ->where('zone_id', $zone->id)
                ->with(['media.variants', 'mediaMobile.variants'])
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get();

            return [
                'zone' => [
                    'slug' => $zone->slug,
                    'name' => $zone->name,
                    'recommended' => $zone->recommended_width && $zone->recommended_height
                        ? ['width' => $zone->recommended_width, 'height' => $zone->recommended_height]
                        : null,
                ],
                'banners' => $banners->map(fn (Banner $b) => $this->shape($b))->values()->all(),
                'updated_at' => optional($banners->max('updated_at'))->toIso8601String(),
            ];
        });

        if ($payload === null) {
            return response()->json(['message' => 'Zone not found'], 404);
        }

        $updatedAt = $payload['updated_at'] ?? null;
        $etag = '"'.md5($zoneSlug.':'.($updatedAt ?? '')).'"';

        // Conditional GET — اگر فرانت هنوز همان نسخه را دارد، 304 می‌فرستیم
        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->json(null, 304)->setEtag($etag, false)
                ->header('Cache-Control', 'public, max-age=60, s-maxage=60');
        }

        return response()->json($payload)
            ->setEtag($etag, false)
            ->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }

    /**
     * POST /v1/banners/{id}/impression  (counter سبک)
     */
    public function impression(string $id): JsonResponse
    {
        Banner::where('id', $id)->increment('impressions');

        return response()->json(['ok' => true]);
    }

    /**
     * POST /v1/banners/{id}/click  (redirect-aware counter)
     */
    public function click(string $id): JsonResponse
    {
        Banner::where('id', $id)->increment('clicks');

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Banner $b): array
    {
        return [
            'id' => $b->id,
            'title' => $b->title,
            'subtitle' => $b->subtitle,
            'link' => [
                'url' => $b->link_url,
                'label' => $b->link_label,
            ],
            'image' => [
                'desktop' => $this->mediaPayload($b->media, $b->image_url),
                'mobile' => $this->mediaPayload($b->mediaMobile, null),
            ],
            'sort_order' => (int) $b->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mediaPayload($media, ?string $fallbackUrl): ?array
    {
        if (! $media) {
            return $fallbackUrl ? ['url' => $fallbackUrl, 'variants' => []] : null;
        }

        return [
            'url' => $media->url(),
            'alt' => $media->alt,
            'width' => $media->width,
            'height' => $media->height,
            'aspect_ratio' => $media->aspect_ratio,
            'variants' => $media->variants->mapWithKeys(fn ($v) => [
                $v->variant => [
                    'url' => \Illuminate\Support\Facades\Storage::disk('public')->url($v->path),
                    'width' => (int) $v->width,
                    'height' => (int) $v->height,
                ],
            ])->all(),
        ];
    }
}
