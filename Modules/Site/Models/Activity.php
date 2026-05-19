<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasUlids;

    protected $table = 'activities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'device_slug',
        'device_label',
        'area',
        'status',
        'happened_at',
    ];

    protected $casts = [
        'happened_at' => 'datetime',
    ];

    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_IN_PROGRESS = 'in_progress';
}
