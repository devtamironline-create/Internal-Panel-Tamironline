<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Site\Models\Concerns\HasMedia;
use Modules\Site\Models\Media\Media;

class Banner extends Model
{
    use HasMedia;
    use HasUlids;

    protected $table = 'site_banners';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'zone_id', 'title', 'subtitle',
        'image_url', 'media_id', 'media_id_mobile',
        'link_url', 'link_label',
        'position', 'sort_order',
        'is_published', 'starts_at', 'ends_at',
        'impressions', 'clicks',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'impressions' => 'integer',
        'clicks' => 'integer',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(BannerZone::class, 'zone_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function mediaMobile(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id_mobile');
    }

    /**
     * Scope: فعال در حال حاضر (منتشر + در بازه‌ی زمان‌بندی).
     */
    public function scopeLive($query)
    {
        $now = now();

        return $query->where('is_published', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }
}
