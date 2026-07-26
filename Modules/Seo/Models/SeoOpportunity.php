<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Seo\Support\Arm\ArmEnums;

/** فرصت رشد — امتیازدهی از سرویس OpportunityScore می‌آید، نه UI. */
class SeoOpportunity extends Model
{
    protected $table = 'seo_opportunities';

    protected $fillable = [
        'seo_project_id', 'seo_page_id', 'seo_keyword_id', 'type', 'title',
        'description', 'impact_score', 'effort_score', 'confidence_score',
        'opportunity_score', 'status', 'recommended_action', 'due_at', 'metadata',
    ];

    protected $casts = [
        'impact_score' => 'float',
        'effort_score' => 'float',
        'confidence_score' => 'float',
        'opportunity_score' => 'float',
        'due_at' => 'date',
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

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'seo_keyword_id');
    }

    public function typeLabel(): string
    {
        return ArmEnums::label(ArmEnums::OPPORTUNITY_TYPES, $this->type);
    }

    public function statusLabel(): string
    {
        return ArmEnums::label(ArmEnums::OPPORTUNITY_STATUSES, $this->status);
    }

    /** ربع رادار فرصت: [impact بالا/پایین, effort بالا/پایین]. */
    public function quadrant(): string
    {
        $impactHigh = $this->impact_score >= 5;
        $effortHigh = $this->effort_score >= 5;

        return match (true) {
            $impactHigh && ! $effortHigh => 'quick_win',
            $impactHigh && $effortHigh => 'strategic',
            ! $impactHigh && ! $effortHigh => 'filler',
            default => 'avoid',
        };
    }
}
