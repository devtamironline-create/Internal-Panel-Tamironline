<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Device;
use Modules\Site\Models\Testimonial;

class TestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 12);
        $limit = max(1, min($limit, 50));

        $query = Testimonial::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderByDesc('published_at');

        // فیلتر per-device: اگر testimonialهایی به این device لینک شده‌اند،
        // اولویت با آن‌ها — در غیر این صورت testimonialهای generic (بدون pivot)
        $deviceSlug = $request->query('device') ?? $request->query('device_slug');
        if ($deviceSlug) {
            $device = Device::query()
                ->where('slug', $deviceSlug)
                ->where('is_active', true)
                ->first(['id']);

            if ($device) {
                $query->where(function ($q) use ($device) {
                    // testimonialهایی که به این device لینک شده‌اند
                    $q->whereHas('devices', fn ($qq) => $qq->where('crm_devices.id', $device->id))
                      // یا generic (هیچ device link شده‌ای ندارند)
                        ->orWhereDoesntHave('devices');
                });
            }
        }

        $items = $query->limit($limit)->get();

        $data = $items->map(fn (Testimonial $t) => [
            'id' => $t->id,
            'customer_name' => $t->customer_name,
            'author_name' => $t->customer_name, // alias مطابق spec
            'topic' => $t->topic,
            'rating' => (int) $t->rating,
            'audio_url' => $t->audio_url,
            'duration_seconds' => $t->duration_seconds,
            'audio_duration' => $this->formatDuration($t->duration_seconds),
            'initials' => $this->initials($t->customer_name),
            'published_at' => $t->published_at?->utc()->toIso8601ZuluString(),
        ])->values();

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }

    /**
     * تبدیل ثانیه به فرمت MM:SS — برای فیلد audio_duration.
     */
    private function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null || $seconds < 0) {
            return null;
        }
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * استخراج اولین کاراکتر معنی‌دار نام برای آواتار متنی.
     */
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
