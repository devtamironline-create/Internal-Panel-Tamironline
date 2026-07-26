<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** snapshot دوره‌ایِ عملکرد سئو (فاز ۱: seed؛ بعداً Search Console/GA4). */
class SeoSnapshot extends Model
{
    protected $table = 'seo_snapshots';

    protected $fillable = [
        'seo_project_id', 'snapshot_date', 'period_start', 'period_end',
        'clicks', 'impressions', 'ctr', 'average_position',
        'keywords_top_3', 'keywords_top_10', 'keywords_top_20',
        'organic_leads', 'phone_call_leads', 'app_login_leads',
        'indexed_pages', 'technical_health_score', 'source', 'metadata',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'average_position' => 'float',
        'keywords_top_3' => 'integer',
        'keywords_top_10' => 'integer',
        'keywords_top_20' => 'integer',
        'organic_leads' => 'integer',
        'phone_call_leads' => 'integer',
        'app_login_leads' => 'integer',
        'indexed_pages' => 'integer',
        'technical_health_score' => 'integer',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }
}
