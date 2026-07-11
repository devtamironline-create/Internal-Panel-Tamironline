<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک لینکِ کشف‌شده در یک صفحهٔ خزیده‌شده (گرافِ لینک).
 */
class SeoLink extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'seo_links';

    protected $guarded = ['id'];

    protected $casts = [
        'is_internal' => 'boolean',
        'position' => 'integer',
        'created_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SeoAuditRun::class, 'run_id');
    }
}
