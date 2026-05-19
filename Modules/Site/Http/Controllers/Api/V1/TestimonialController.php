<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Site\Models\Testimonial;

class TestimonialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 12);
        $limit = max(1, min($limit, 50));

        $items = Testimonial::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'customer_name', 'topic', 'rating', 'audio_url', 'duration_seconds', 'published_at']);

        $data = $items->map(fn (Testimonial $t) => [
            'id'               => $t->id,
            'customer_name'    => $t->customer_name,
            'topic'            => $t->topic,
            'rating'           => $t->rating,
            'audio_url'        => $t->audio_url,
            'duration_seconds' => $t->duration_seconds,
            'published_at'     => $t->published_at?->utc()->toIso8601ZuluString(),
        ])->values();

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }
}
