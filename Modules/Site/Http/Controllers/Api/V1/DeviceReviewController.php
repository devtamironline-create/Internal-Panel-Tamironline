<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Device;
use Modules\Site\Http\Requests\Api\V1\StoreDeviceReviewRequest;
use Modules\Site\Models\Review;
use Modules\Site\Support\MediaUrl;

/**
 * Endpointهای نظرات متنی صفحه‌ی دستگاه — منبع داده site_reviews با type=text.
 *
 * - GET /v1/catalog/devices/{slug}/reviews  → لیست approved با pagination
 * - POST /v1/catalog/devices/{slug}/reviews → ثبت نظر جدید (status=pending)
 * - POST /v1/catalog/reviews/{id}/like      → افزایش لایک (IP-based unique)
 */
class DeviceReviewController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $exists = Device::query()->where('slug', $slug)->where('is_active', true)->exists();
        if (! $exists) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $page = max(1, (int) $request->query('page', 1));
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));
        $sort = $request->query('sort', 'newest');

        $query = Review::query()
            ->text()
            ->approved()
            ->forDevice($slug)
            ->with('reply');

        match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'top' => $query->orderByDesc('likes')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $total = (clone $query)->count();
        $averageRating = (float) Review::query()
            ->text()
            ->approved()
            ->forDevice($slug)
            ->avg('rating');

        $reviews = $query->forPage($page, $limit)->get();

        $data = $reviews->map(fn (Review $r) => $this->shapeReview($r))->values();

        $lastPage = $total > 0 ? (int) ceil($total / $limit) : 1;

        return response()
            ->json([
                'data' => $data,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'last_page' => $lastPage,
                    'average_rating' => round($averageRating, 1),
                ],
            ])
            ->header('Cache-Control', 'public, max-age=60, s-maxage=60');
    }

    public function store(StoreDeviceReviewRequest $request, string $slug): JsonResponse
    {
        $device = Device::query()->where('slug', $slug)->where('is_active', true)->first(['id']);
        if (! $device) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $data = $request->validated();

        $review = Review::create([
            'type' => Review::TYPE_TEXT,
            'device_id' => $device->id,
            'device_slug' => $slug,
            'author_name' => trim($data['author_name']),
            'email' => strtolower(trim($data['email'])),
            'rating' => (int) $data['rating'],
            'content' => trim($data['content']),
            'status' => Review::STATUS_PENDING,
            'is_published' => false,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'id' => $review->id,
            'status' => $review->status,
            'message' => 'نظر شما دریافت شد و پس از بررسی نمایش داده می‌شود.',
        ], 201);
    }

    /**
     * POST /v1/catalog/reviews/{id}/like
     *
     * هر IP فقط یک بار می‌تواند هر نظر را لایک کند (unique constraint).
     */
    public function like(Request $request, string $id): JsonResponse
    {
        $review = Review::query()
            ->approved()
            ->where('id', $id)
            ->first();

        if (! $review) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $ip = $request->ip();

        $inserted = false;
        try {
            DB::table('site_review_likes')->insert([
                'review_id' => $review->id,
                'ip' => $ip,
                'created_at' => now(),
            ]);
            $inserted = true;
        } catch (\Illuminate\Database\QueryException $e) {
            // duplicate — همین IP قبلاً لایک کرده
        }

        if ($inserted) {
            $review->increment('likes');
            $review->refresh();
        }

        return response()->json(['likes' => (int) $review->likes]);
    }

    private function shapeReview(Review $r): array
    {
        return [
            'id' => $r->id,
            'author_name' => $r->author_name,
            'author_avatar' => MediaUrl::resolve($r->author_avatar),
            'is_verified' => (bool) $r->is_verified,
            'is_expert' => (bool) $r->is_expert,
            'rating' => (int) $r->rating,
            'content' => $r->content,
            'created_at' => $r->created_at?->utc()->toIso8601ZuluString(),
            'likes' => (int) $r->likes,
            'reply' => $r->reply ? [
                'author_name' => $r->reply->author_name,
                'author_avatar' => MediaUrl::resolve($r->reply->author_avatar),
                'is_expert' => (bool) $r->reply->is_expert,
                'content' => $r->reply->content,
                'created_at' => $r->reply->created_at?->utc()->toIso8601ZuluString(),
            ] : null,
        ];
    }
}
