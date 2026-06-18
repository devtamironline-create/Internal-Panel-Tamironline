<?php

namespace Modules\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoNotFound extends Model
{
    protected $table = 'seo_not_found_logs';

    protected $fillable = ['uri', 'uri_hash', 'referrer', 'user_agent', 'hits', 'last_seen_at'];

    protected $casts = [
        'hits' => 'integer',
        'last_seen_at' => 'datetime',
    ];
}
