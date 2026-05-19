<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasUlids;

    protected $table = 'site_banners';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'title',
        'subtitle',
        'image_url',
        'link_url',
        'link_label',
        'position',
        'sort_order',
        'is_published',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
    ];
}
