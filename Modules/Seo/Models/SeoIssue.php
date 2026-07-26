<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Seo\Support\Arm\ArmEnums;

/** مشکل فنی سئو — تا کرال واقعی تأیید نکند، needs_verification می‌ماند. */
class SeoIssue extends Model
{
    protected $table = 'seo_issues';

    protected $fillable = [
        'seo_project_id', 'seo_page_id', 'category', 'severity', 'title',
        'description', 'detected_at', 'status', 'recommended_fix',
        'assigned_to', 'resolved_at', 'metadata',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'seo_page_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function categoryLabel(): string
    {
        return ArmEnums::label(ArmEnums::ISSUE_CATEGORIES, $this->category);
    }

    public function severityLabel(): string
    {
        return ArmEnums::label(ArmEnums::ISSUE_SEVERITIES, $this->severity);
    }

    public function statusLabel(): string
    {
        return ArmEnums::label(ArmEnums::ISSUE_STATUSES, $this->status);
    }
}
