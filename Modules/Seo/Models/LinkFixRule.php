<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * قاعدهٔ اصلاحِ لینکِ داخلی: «URL خراب → مقصدِ درست» (replace) یا
 * «حذفِ لینک با حفظِ متن» (unlink).
 */
class LinkFixRule extends Model
{
    protected $table = 'seo_link_fix_rules';

    protected $guarded = ['id'];

    protected $casts = [
        'needs_review' => 'boolean',
        'matches_count' => 'integer',
        'applied_count' => 'integer',
        'scanned_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $rule) {
            $rule->old_url_hash = sha1((string) $rule->old_url);
        });
    }

    public function changes(): HasMany
    {
        return $this->hasMany(LinkFixChange::class, 'rule_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }
}
