<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoAuditRun extends Model
{
    protected $table = 'seo_audit_runs';

    protected $fillable = [
        'status', 'source', 'total_urls', 'ok_count', 'notice_count',
        'warning_count', 'critical_count', 'avg_score', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function audits(): HasMany
    {
        return $this->hasMany(SeoAudit::class, 'run_id');
    }
}
