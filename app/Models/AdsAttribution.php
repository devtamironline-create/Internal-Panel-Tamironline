<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * یک کلیکِ تبلیغاتیِ Google Ads — شناسهٔ عمومی attribution_id (ULID) را
 * فرانت نگه می‌دارد؛ id افزایشی هرگز بیرون نمی‌رود.
 */
class AdsAttribution extends Model
{
    public const SOURCES = ['website', 'pwa', 'unknown'];

    protected $fillable = [
        'attribution_id', 'client_source',
        'gclid', 'wbraid', 'gbraid',
        'campaign_id', 'adgroup_id', 'keyword', 'match_type', 'device', 'network', 'creative_id',
        'landing_url', 'landing_path', 'referrer',
        'first_seen_at', 'last_seen_at', 'expires_at',
        'ip_hash', 'user_agent_hash', 'metadata',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $attribution) {
            if (empty($attribution->attribution_id)) {
                $attribution->attribution_id = strtolower((string) Str::ulid());
            }
        });
    }

    public function callClicks(): HasMany
    {
        return $this->hasMany(AdsCallClickEvent::class, 'ads_attribution_id');
    }

    /** آیا حداقل یکی از شناسه‌های Google را دارد؟ */
    public function hasGoogleId(): bool
    {
        return filled($this->gclid) || filled($this->wbraid) || filled($this->gbraid);
    }

    /** شناسهٔ Google برای نمایش (کوتاه‌شده در لیست‌ها). */
    public function googleIdLabel(): ?string
    {
        return $this->gclid ?: $this->wbraid ?: $this->gbraid;
    }
}
