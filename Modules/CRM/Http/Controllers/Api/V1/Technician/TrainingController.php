<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\TrainingCategory;
use Modules\CRM\Models\TrainingVideo;

/**
 * آموزشِ اپِ تکنسین — دسته‌ها، ویدیوها، علامتِ «دیده‌شد» و گِیتِ فعال‌سازی.
 * هم‌ترازِ Tech\DashboardController::training*.
 */
class TrainingController extends Controller
{
    /** GET /v1/technician/training — دسته‌ها + پیشرفت + شمارِ بدون‌دسته. */
    public function index(Request $request): JsonResponse
    {
        $tech = $request->user();

        $categories = TrainingCategory::active()->ordered()
            ->withCount(['videos as videos_count' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn ($c) => $c->videos_count > 0)
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'videos_count' => (int) $c->videos_count,
            ])->values();

        $uncategorized = TrainingVideo::active()->whereNull('category_id')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'uncategorized_count' => (int) $uncategorized,
                'progress' => $tech->trainingProgress(),
                'training_completed' => $tech->isTrainingCompleted(),
            ],
        ]);
    }

    /** GET /v1/technician/training/categories/{id} */
    public function category(Request $request, int $id): JsonResponse
    {
        $category = TrainingCategory::query()->whereKey($id)->where('is_active', true)->firstOrFail();
        $videos = $category->videos()->active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'category' => ['id' => (int) $category->id, 'name' => $category->name, 'description' => $category->description],
                'videos' => $this->mapVideos($videos, $request->user()),
            ],
        ]);
    }

    /** GET /v1/technician/training/uncategorized */
    public function uncategorized(Request $request): JsonResponse
    {
        $videos = TrainingVideo::active()->whereNull('category_id')->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => [
                'category' => ['id' => null, 'name' => 'سایر', 'description' => null],
                'videos' => $this->mapVideos($videos, $request->user()),
            ],
        ]);
    }

    /** GET /v1/technician/training/videos/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $video = TrainingVideo::query()->whereKey($id)->where('is_active', true)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->shape($video, $request->user(), withDescription: true),
        ]);
    }

    /** POST /v1/technician/training/videos/{id}/watched */
    public function markWatched(Request $request, int $id): JsonResponse
    {
        $video = TrainingVideo::query()->whereKey($id)->where('is_active', true)->firstOrFail();
        $tech = $request->user();
        $tech->markVideoWatched($video);

        $progress = $tech->refresh()->trainingProgress();

        return response()->json([
            'success' => true,
            'message' => $progress['remaining'] === 0
                ? '🎉 تمام ویدیوهای آموزشی را دیدید. پنل فعال شد.'
                : 'ویدیو ثبت شد.',
            'data' => ['progress' => $progress, 'training_completed' => $tech->isTrainingCompleted()],
        ]);
    }

    private function mapVideos($videos, $tech): array
    {
        return $videos->map(fn (TrainingVideo $v) => $this->shape($v, $tech))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(TrainingVideo $video, $tech, bool $withDescription = false): array
    {
        $watched = $tech->watchedVideos()->where('video_id', $video->id)->exists();

        $out = [
            'id' => (int) $video->id,
            'title' => $video->title,
            'provider' => method_exists($video, 'sourceType') ? $video->sourceType() : null,
            'video_url' => $video->video_url,
            'playback_url' => method_exists($video, 'playbackUrl') ? $video->playbackUrl() : null,
            'thumbnail_url' => method_exists($video, 'thumbnailUrl') ? $video->thumbnailUrl() : null,
            'duration_seconds' => (int) ($video->duration_seconds ?? 0) ?: null,
            'watched' => $watched,
        ];
        if ($withDescription) {
            $out['description'] = $video->description;
        }

        return $out;
    }
}
