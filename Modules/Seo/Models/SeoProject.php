<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** پروژهٔ بازوی سئو — ظرف همهٔ داده‌های فضای کاری. */
class SeoProject extends Model
{
    protected $table = 'seo_projects';

    protected $fillable = [
        'name', 'domain', 'timezone', 'status', 'start_date', 'target_date',
        'primary_market', 'description', 'settings',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_date' => 'date',
        'settings' => 'array',
    ];

    public function snapshots(): HasMany
    {
        return $this->hasMany(SeoSnapshot::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SeoPage::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(SeoKeyword::class);
    }

    public function roadmapItems(): HasMany
    {
        return $this->hasMany(SeoRoadmapItem::class);
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(SeoContentItem::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(SeoOpportunity::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(SeoIssue::class);
    }

    public function linkItems(): HasMany
    {
        return $this->hasMany(SeoLinkItem::class);
    }

    /** پروژهٔ فعال پیش‌فرض (فاز ۱: تک‌پروژه). */
    public static function current(): ?self
    {
        return static::query()->where('status', 'active')->orderBy('id')->first();
    }

    /** شمارهٔ روزِ جاری برنامه (۱-مبنا) بر اساس start_date؛ خارج از بازه clamp می‌شود. */
    public function currentDayNumber(): int
    {
        if (! $this->start_date) {
            return 1;
        }
        $day = (int) $this->start_date->startOfDay()->diffInDays(now()->startOfDay()) + 1;

        return max(1, min(90, $day));
    }

    public function currentWeekNumber(): int
    {
        return (int) ceil($this->currentDayNumber() / 7);
    }
}
