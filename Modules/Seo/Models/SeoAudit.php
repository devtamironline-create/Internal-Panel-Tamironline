<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoAudit extends Model
{
    protected $table = 'seo_audits';

    protected $guarded = ['id'];

    protected $casts = [
        'is_noindex' => 'boolean',
        'og_present' => 'boolean',
        'twitter_present' => 'boolean',
        'jsonld_present' => 'boolean',
        'cwv' => 'array',
        'issues' => 'array',
        'crawled_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SeoAuditRun::class, 'run_id');
    }
}
