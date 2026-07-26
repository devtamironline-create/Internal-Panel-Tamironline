<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Seo\Support\Arm\ArmEnums;

/** صفحهٔ هدف در فضای کاری بازوی سئو (نگاشت صفحه↔کلمه، اولویت، گردش‌کار). */
class SeoPage extends Model
{
    protected $table = 'seo_pages';

    protected $fillable = [
        'seo_project_id', 'url', 'path', 'title', 'page_type', 'cluster',
        'search_intent', 'primary_keyword', 'priority', 'workflow_status',
        'index_status', 'target_position', 'current_position',
        'clicks', 'impressions', 'ctr', 'conversions', 'business_value',
        'last_content_update_at', 'next_review_at', 'metadata',
    ];

    protected $casts = [
        'target_position' => 'float',
        'current_position' => 'float',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'conversions' => 'integer',
        'business_value' => 'integer',
        'last_content_update_at' => 'datetime',
        'next_review_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(SeoKeyword::class, 'seo_page_id');
    }

    public function typeLabel(): string
    {
        return ArmEnums::label(ArmEnums::PAGE_TYPES, $this->page_type);
    }

    public function statusLabel(): string
    {
        return ArmEnums::label(ArmEnums::PAGE_WORKFLOW, $this->workflow_status);
    }

    /** آیا نیازمند نگاشت URL است؟ (نامه: URL قطعی نامعلوم → nullable + flag) */
    public function needsUrlMapping(): bool
    {
        return blank($this->url) && blank($this->path);
    }
}
