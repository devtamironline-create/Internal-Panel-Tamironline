<?php

namespace Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingVideo extends Model
{
    protected $table = 'crm_training_videos';

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'is_local',
        'video_url',
        'thumbnail',
        'duration_seconds',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_local' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'duration_seconds' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TrainingCategory::class, 'category_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    /**
     * URL مناسب برای embed/پخش در view. اگر فایل لوکال باشد، از asset
     * می‌سازد؛ در غیر این صورت همان video_url را برمی‌گرداند.
     */
    public function playbackUrl(): ?string
    {
        if (! $this->video_url) {
            return null;
        }
        if ($this->is_local) {
            return asset('storage/' . ltrim($this->video_url, '/'));
        }

        return $this->video_url;
    }

    /**
     * تشخیص نوع منبع: aparat / youtube / vimeo / mp4 / unknown.
     * برای انتخاب نوع embed (iframe vs <video>).
     */
    public function sourceType(): string
    {
        if ($this->is_local) {
            return 'mp4';
        }
        $url = (string) $this->video_url;
        if (str_contains($url, 'aparat.com')) return 'aparat';
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) return 'youtube';
        if (str_contains($url, 'vimeo.com')) return 'vimeo';
        if (preg_match('/\.(mp4|webm|ogv|mov)(\?.*)?$/i', $url)) return 'mp4';
        return 'unknown';
    }

    /** برای آپارات: تبدیل URL مشاهده به URL embed. */
    public function aparatEmbedUrl(): ?string
    {
        if (! preg_match('#aparat\.com/v/([A-Za-z0-9]+)#', (string) $this->video_url, $m)) {
            return null;
        }

        return 'https://www.aparat.com/video/video/embed/videohash/' . $m[1] . '/vt/frame';
    }

    /** برای یوتیوب: تبدیل URL به URL embed. */
    public function youtubeEmbedUrl(): ?string
    {
        $url = (string) $this->video_url;
        if (preg_match('#youtu\.be/([A-Za-z0-9_-]+)#', $url, $m)
            || preg_match('#youtube\.com/watch\?v=([A-Za-z0-9_-]+)#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        return null;
    }
}
