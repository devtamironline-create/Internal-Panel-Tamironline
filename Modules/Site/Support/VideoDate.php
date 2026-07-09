<?php

namespace Modules\Site\Support;

use Illuminate\Support\Carbon;

/**
 * تاریخِ VideoObject.uploadDate برای آیتم‌های ویدئوی صفحاتِ خدمات.
 *
 * فرانت برای اسکیمای VideoObject به uploadDate نیاز دارد. آیتمِ ویدئو معمولاً
 * تاریخِ اختصاصی ندارد؛ پس اگر خودِ آیتم یکی از upload_date/published_at/
 * created_at را داشت همان (نرمال‌شده به ISO 8601)، وگرنه تاریخِ fallbackِ
 * موجودیتِ والد (updated_at دستگاه/برند) برگردانده می‌شود.
 */
final class VideoDate
{
    /**
     * @param  array<string, mixed>  $item
     */
    public static function iso(array $item, ?string $fallback = null): ?string
    {
        foreach (['upload_date', 'published_at', 'created_at'] as $key) {
            $value = $item[$key] ?? null;
            if ($value !== null && $value !== '') {
                try {
                    return Carbon::parse($value)->toIso8601String();
                } catch (\Throwable $e) {
                    // مقدارِ نامعتبر → سراغِ فیلدِ بعدی/fallback.
                }
            }
        }

        return $fallback;
    }

    /** تاریخِ fallback از یک موجودیت (updated_at → created_at) به‌صورتِ ISO 8601. */
    public static function modelFallback($model): ?string
    {
        $date = $model?->updated_at ?? $model?->created_at ?? null;

        if ($date === null) {
            return null;
        }

        try {
            return Carbon::parse($date)->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
