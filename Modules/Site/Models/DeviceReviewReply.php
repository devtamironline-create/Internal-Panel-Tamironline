<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceReviewReply extends Model
{
    use HasUlids;

    protected $table = 'site_device_review_replies';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'review_id',
        'author_name',
        'author_avatar',
        'is_expert',
        'content',
    ];

    protected $casts = [
        'is_expert'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(DeviceReview::class, 'review_id');
    }
}
