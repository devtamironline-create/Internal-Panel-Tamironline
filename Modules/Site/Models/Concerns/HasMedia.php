<?php

namespace Modules\Site\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Site\Models\Media\Media;
use Modules\Site\Models\Media\MediaUse;

/**
 * trait برای هر مدلی که از Media Library فایل استفاده می‌کند.
 *
 * استفاده:
 *   class Banner extends Model {
 *     use HasMedia;
 *     // ...
 *     // برای رجیستر کردن استفاده، در observer یا saved hook:
 *     // $banner->attachMedia($mediaId, 'image');
 *   }
 *
 * این trait reverse-lookup را امکان می‌دهد: «این فایل کجاها استفاده شده».
 */
trait HasMedia
{
    public function mediaUses(): MorphMany
    {
        return $this->morphMany(MediaUse::class, 'useable');
    }

    /**
     * ثبت استفاده‌ی این entity از یک Media. اگر قبلاً برای همان field
     * ثبت شده باشد، صرف‌نظر می‌شود (unique index).
     */
    public function attachMedia(int $mediaId, ?string $field = null): void
    {
        MediaUse::firstOrCreate([
            'media_id' => $mediaId,
            'useable_type' => static::class,
            'useable_id' => $this->getKey(),
            'field' => $field,
        ], [
            'used_at' => now(),
        ]);
    }

    public function detachMedia(int $mediaId, ?string $field = null): void
    {
        $q = MediaUse::where('media_id', $mediaId)
            ->where('useable_type', static::class)
            ->where('useable_id', $this->getKey());
        if ($field !== null) {
            $q->where('field', $field);
        }
        $q->delete();
    }

    public function detachAllMedia(): void
    {
        MediaUse::where('useable_type', static::class)
            ->where('useable_id', $this->getKey())
            ->delete();
    }

    /**
     * Helper: سینک کامل استفاده‌های این entity (پاک‌کردن قبلی + ثبت جدید).
     */
    public function syncMediaForField(?int $mediaId, string $field): void
    {
        $this->detachMedia(0, $field); // پاک‌کردن همه‌ی mediaهای این field
        MediaUse::where('useable_type', static::class)
            ->where('useable_id', $this->getKey())
            ->where('field', $field)
            ->delete();
        if ($mediaId) {
            $this->attachMedia($mediaId, $field);
        }
    }
}
