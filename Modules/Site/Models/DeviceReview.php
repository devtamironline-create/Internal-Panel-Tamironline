<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeviceReview extends Model
{
    use HasUlids;

    protected $table = 'site_device_reviews';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'device_slug',
        'author_name',
        'email',
        'author_avatar',
        'rating',
        'content',
        'status',
        'is_verified',
        'is_expert',
        'likes',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'rating'      => 'integer',
        'likes'       => 'integer',
        'is_verified' => 'boolean',
        'is_expert'   => 'boolean',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function reply(): HasOne
    {
        return $this->hasOne(DeviceReviewReply::class, 'review_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForDevice($query, string $slug)
    {
        return $query->where('device_slug', $slug);
    }
}
