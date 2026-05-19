<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasUlids;

    protected $table = 'faqs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'question',
        'answer',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
    ];
}
