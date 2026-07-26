<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Seo\Models\Concerns\HasStatusWorkflow;
use Modules\Seo\Support\Arm\ArmEnums;

/** آیتم تقویم محتوا — بریف، outline، لینک‌های داخلی، اسکیمای لازم و گردش‌کار. */
class SeoContentItem extends Model
{
    use HasStatusWorkflow;

    public const STATUS_TRANSITIONS = ArmEnums::CONTENT_TRANSITIONS;

    protected $table = 'seo_content_items';

    protected $fillable = [
        'seo_project_id', 'seo_page_id', 'roadmap_item_id', 'title',
        'content_type', 'primary_keyword', 'secondary_keywords', 'intent',
        'cluster', 'brief', 'outline', 'internal_link_targets',
        'required_schema', 'call_to_action', 'status', 'priority',
        'author_id', 'reviewer_id', 'planned_at', 'due_at', 'published_at',
        'next_review_at', 'published_url', 'notes',
    ];

    protected $casts = [
        'secondary_keywords' => 'array',
        'outline' => 'array',
        'internal_link_targets' => 'array',
        'required_schema' => 'array',
        'planned_at' => 'date',
        'due_at' => 'date',
        'published_at' => 'datetime',
        'next_review_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }

    public function roadmapItem(): BelongsTo
    {
        return $this->belongsTo(SeoRoadmapItem::class, 'roadmap_item_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewer_id');
    }

    public function statusLabel(): string
    {
        return ArmEnums::label(ArmEnums::CONTENT_STATUSES, $this->status);
    }

    public function typeLabel(): string
    {
        return ArmEnums::label(ArmEnums::CONTENT_TYPES, $this->content_type);
    }

    public function isDelayed(): bool
    {
        return $this->due_at
            && $this->due_at->isPast()
            && ! in_array($this->status, ['published', 'monitoring'], true);
    }
}
