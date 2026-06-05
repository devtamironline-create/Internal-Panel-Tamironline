<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Device;
use Modules\Site\Models\Review;

/**
 * Alias backward-compat برای /v1/testimonials.
 * منبع داده پس از یکپارچه‌سازی: site_reviews با type=audio.
 *
 * فیلتر per-device منطق اولویت‌بندی دارد: ابتدا testimonialهای لینک‌شده
 * به این دستگاه، سپس testimonialهای generic (بدون لینک به هیچ دستگاهی).
 */
class TestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 12);
        $limit = max(1, min($limit, 50));

        $query = Review::query()
            ->audio()
            ->published()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('published_at');

        $deviceSlug = $request->query('device') ?? $request->query('device_slug');
        if ($deviceSlug) {
            $device = Device::query()
                ->where('slug', $deviceSlug)
                ->where('is_active', true)
                ->first(['id']);

            if ($device) {
                $query->where(function ($q) use ($device) {
                    $q->whereHas('devices', fn ($qq) => $qq->where('crm_devices.id', $device->id))
                        ->orWhereDoesntHave('devices');
                });
            }
        }

        $items = $query->limit($limit)->get();

        $data = $items->map(fn (Review $t) => [
            'id' => $t->id,
            'customer_name' => $t->author_name,
            'author_name' => $t->author_name,
            'topic' => $t->topic,
            'rating' => (int) $t->rating,
            'audio_url' => $t->audio_url,
            'duration_seconds' => $t->duration_seconds,
            'audio_duration' => $this->formatDuration($t->duration_seconds),
            'initials' => $this->initials($t->author_name),
            'published_at' => $t->published_at?->utc()->toIso8601ZuluString(),
        ])->values();

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }

    private function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null || $seconds < 0) {
            return null;
        }
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    private function initials(?string $name): ?string
    {
        if (! $name) {
            return null;
        }
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return mb_substr($name, 0, 1, 'UTF-8');
    }
}
