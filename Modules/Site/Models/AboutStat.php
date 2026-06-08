<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;

class AboutStat extends Model
{
    protected $table = 'site_about_stats';

    protected $fillable = [
        'key',
        'value',
        'label',
        'tone',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'sort_order'   => 'integer',
        'is_published' => 'boolean',
    ];

    public const TONES = ['blue', 'green', 'amber', 'rose', 'violet'];
}
