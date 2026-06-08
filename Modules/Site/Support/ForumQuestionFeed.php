<?php

namespace Modules\Site\Support;

use Modules\Site\Models\Forum\Question;

/**
 * Helper مشترک برای بازگرداندن آخرین سوالات انجمن مرتبط با brand / device / ترکیبی.
 *
 *   - Question::approved() = منتشرشده + status=approved + published_at NOT NULL
 *   - مرتب بر اساس published_at نزولی
 *   - شکل سبک برای نمایش در صفحات brand/device
 *
 * استفاده در CatalogDeviceController / CatalogBrandController / CatalogDeviceBrandController.
 */
final class ForumQuestionFeed
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forDevice(int $deviceId, int $limit = 5): array
    {
        return self::shape(
            Question::query()
                ->approved()
                ->where('device_id', $deviceId)
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get([
                    'id', 'slug', 'title', 'device_id', 'brand_id',
                    'view_count', 'upvotes_count', 'answers_count',
                    'resolution_status', 'published_at',
                ])
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forBrand(int $brandId, int $limit = 5): array
    {
        return self::shape(
            Question::query()
                ->approved()
                ->where('brand_id', $brandId)
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get([
                    'id', 'slug', 'title', 'device_id', 'brand_id',
                    'view_count', 'upvotes_count', 'answers_count',
                    'resolution_status', 'published_at',
                ])
        );
    }

    /**
     * ترکیبی: اول دقیقاً همان (device_id, brand_id) — اگر کافی نبود،
     * با سوالاتی که فقط device_id مطابقت دارد تکمیل می‌کنیم (تا limit پر شود).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forDeviceBrand(int $deviceId, int $brandId, int $limit = 5): array
    {
        $exact = Question::query()
            ->approved()
            ->where('device_id', $deviceId)
            ->where('brand_id', $brandId)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get([
                'id', 'slug', 'title', 'device_id', 'brand_id',
                'view_count', 'upvotes_count', 'answers_count',
                'resolution_status', 'published_at',
            ]);

        if ($exact->count() >= $limit) {
            return self::shape($exact);
        }

        $remaining = $limit - $exact->count();
        $exactIds = $exact->pluck('id')->all();

        $fill = Question::query()
            ->approved()
            ->where('device_id', $deviceId)
            ->where(function ($q) use ($brandId) {
                $q->whereNull('brand_id')->orWhere('brand_id', '!=', $brandId);
            })
            ->whereNotIn('id', $exactIds)
            ->orderByDesc('published_at')
            ->limit($remaining)
            ->get([
                'id', 'slug', 'title', 'device_id', 'brand_id',
                'view_count', 'upvotes_count', 'answers_count',
                'resolution_status', 'published_at',
            ]);

        return self::shape($exact->concat($fill));
    }

    /**
     * @param  iterable<Question>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function shape(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $q) {
            $out[] = [
                'id' => (int) $q->id,
                'slug' => $q->slug,
                'title' => $q->title,
                'url' => '/forum/'.$q->id.'-'.$q->slug,
                'device_id' => $q->device_id ? (int) $q->device_id : null,
                'brand_id' => $q->brand_id ? (int) $q->brand_id : null,
                'view_count' => (int) $q->view_count,
                'upvotes_count' => (int) $q->upvotes_count,
                'answers_count' => (int) $q->answers_count,
                'resolution_status' => $q->resolution_status,
                'published_at' => optional($q->published_at)->toIso8601String(),
            ];
        }

        return $out;
    }
}
