<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoNotFound extends Model
{
    protected $table = 'seo_not_found_logs';

    protected $fillable = ['uri', 'uri_hash', 'referrer', 'user_agent', 'hits', 'last_seen_at', 'first_seen_at', 'ignored_at', 'is_bot'];

    protected $casts = [
        'hits' => 'integer',
        'last_seen_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'ignored_at' => 'datetime',
        'is_bot' => 'boolean',
    ];

    /** فقط رکوردهای نادیده‌گرفته‌نشده. */
    public function scopeNotIgnored(Builder $query): Builder
    {
        return $query->whereNull('ignored_at');
    }

    /** فقط رکوردهای نادیده‌گرفته‌شده. */
    public function scopeIgnored(Builder $query): Builder
    {
        return $query->whereNotNull('ignored_at');
    }
}
