<?php

namespace Modules\Site\Models\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * فایل media — یک رکورد به ازای هر فایل فیزیکی منحصربه‌فرد (با hash).
 * URLها از مسیر public/storage سرو می‌شوند.
 */
class Media extends Model
{
    protected $table = 'site_media';

    protected $fillable = [
        'hash', 'path', 'filename', 'mime', 'extension', 'size_bytes',
        'width', 'height', 'aspect_ratio',
        'title', 'alt', 'caption', 'description',
        'kind', 'uploaded_by_user_id',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class, 'media_id');
    }

    public function uses(): HasMany
    {
        return $this->hasMany(MediaUse::class, 'media_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class, 'site_media_tag_assignments', 'media_id', 'tag_id');
    }

    /**
     * URL کامل فایل اصلی روی public disk.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * URL یک variant خاص با fallback به اصلی.
     */
    public function variantUrl(string $key): string
    {
        $v = $this->variants->firstWhere('variant', $key);

        return $v ? Storage::disk('public')->url($v->path) : $this->url();
    }

    /**
     * شکل کامل برای API.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => (int) $this->id,
            'url' => $this->url(),
            'kind' => $this->kind,
            'mime' => $this->mime,
            'extension' => $this->extension,
            'size_bytes' => (int) $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'aspect_ratio' => $this->aspect_ratio,
            'title' => $this->title,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'variants' => $this->variants->mapWithKeys(fn (MediaVariant $v) => [
                $v->variant => [
                    'url' => Storage::disk('public')->url($v->path),
                    'width' => (int) $v->width,
                    'height' => (int) $v->height,
                ],
            ])->all(),
        ];
    }

    public function scopeImages($query)
    {
        return $query->where('kind', 'image');
    }
}
