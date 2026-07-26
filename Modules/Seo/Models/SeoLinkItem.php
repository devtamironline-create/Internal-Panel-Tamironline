<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Seo\Support\Arm\ArmEnums;

/**
 * آیتم فضای کاری لینک‌سازی (داخلی/خارجی/PR/سایتیشن).
 * جدول عمداً «seo_link_items» است — «seo_links» متعلق به گراف لینک خزش است.
 */
class SeoLinkItem extends Model
{
    protected $table = 'seo_link_items';

    protected $fillable = [
        'seo_project_id', 'link_type', 'source_url', 'target_url',
        'anchor_text', 'destination_page_id', 'domain', 'status',
        'follow_type', 'authority_score', 'trust_score', 'cost', 'currency',
        'contact_status', 'planned_at', 'published_at', 'last_checked_at', 'metadata',
    ];

    protected $casts = [
        'authority_score' => 'integer',
        'trust_score' => 'integer',
        'cost' => 'float',
        'planned_at' => 'date',
        'published_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function destinationPage(): BelongsTo
    {
        return $this->belongsTo(SeoPage::class, 'destination_page_id');
    }

    public function typeLabel(): string
    {
        return ArmEnums::label(ArmEnums::LINK_TYPES, $this->link_type);
    }

    public function statusLabel(): string
    {
        return ArmEnums::label(ArmEnums::LINK_STATUSES, $this->status);
    }
}
