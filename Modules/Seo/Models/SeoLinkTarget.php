<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * وضعیتِ یک مقصدِ یکتا در یک کرال (status/redirect/broken).
 */
class SeoLinkTarget extends Model
{
    public $timestamps = false; // زمان‌بندی با checked_at مدیریت می‌شود

    protected $table = 'seo_link_targets';

    protected $guarded = ['id'];

    protected $casts = [
        'is_internal' => 'boolean',
        'status_code' => 'integer',
        'redirect_count' => 'integer',
        'redirect_chain' => 'array',
        'is_broken' => 'boolean',
        'is_redirect' => 'boolean',
        'ignored_at' => 'datetime',
        'checked_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SeoAuditRun::class, 'run_id');
    }

    public function scopeBroken($q)
    {
        return $q->where('is_broken', true);
    }

    public function scopeActive($q)
    {
        return $q->whereNull('ignored_at');
    }
}
