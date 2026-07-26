<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Seo\Models\Concerns\HasStatusWorkflow;
use Modules\Seo\Support\Arm\ArmEnums;

/** آیتم برنامهٔ ۹۰ روزه — روز/هفته/فاز + خروجی و معیار پذیرش. */
class SeoRoadmapItem extends Model
{
    use HasStatusWorkflow;

    public const STATUS_TRANSITIONS = ArmEnums::ROADMAP_TRANSITIONS;

    protected $table = 'seo_roadmap_items';

    protected $fillable = [
        'seo_project_id', 'day_number', 'week_number', 'phase', 'title',
        'description', 'task_type', 'priority', 'status', 'planned_date',
        'started_at', 'completed_at', 'owner_id', 'related_page_id',
        'deliverables', 'acceptance_criteria',
    ];

    protected $casts = [
        'day_number' => 'integer',
        'week_number' => 'integer',
        'phase' => 'integer',
        'planned_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'deliverables' => 'array',
        'acceptance_criteria' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function relatedPage(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'related_page_id');
    }

    public function statusLabel(): string
    {
        return ArmEnums::label(ArmEnums::ROADMAP_STATUSES, $this->status);
    }

    public function isOverdue(): bool
    {
        return $this->planned_date
            && $this->planned_date->isPast()
            && ! in_array($this->status, ['completed', 'skipped'], true);
    }
}
