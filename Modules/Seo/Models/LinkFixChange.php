<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک تغییرِ اعمال‌شده روی یک فیلدِ یک رکورد — با نسخهٔ کاملِ قبلی برای بازگردانی.
 */
class LinkFixChange extends Model
{
    public $timestamps = false;

    protected $table = 'seo_link_fix_changes';

    protected $guarded = ['id'];

    protected $casts = [
        'occurrences' => 'integer',
        'reverted_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LinkFixRule::class, 'rule_id');
    }
}
