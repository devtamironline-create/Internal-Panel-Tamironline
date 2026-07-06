<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * کامنت polymorphic — می‌تواند به هر مدلی متصل شود (Article, Device, Brand, ...)
 * که trait `HasComments` (یا مستقیماً morphMany) را داشته باشد.
 *
 * threading: parent_id → کامنت والد، root_id → ریشه‌ی thread (برای flat-fetch).
 */
class Comment extends Model
{
    protected $table = 'site_comments';

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'parent_id',
        'root_id',
        'user_id',
        'customer_id',
        'author_name',
        'author_email',
        'author_avatar',
        'is_admin_reply',
        'content',
        'status',
        'rejection_reason',
        'likes_count',
        'dislikes_count',
        'ip',
        'user_agent',
        'approved_at',
        'approved_by_user_id',
        'reply_sms_sent_at',
    ];

    protected $casts = [
        'is_admin_reply' => 'boolean',
        'likes_count' => 'integer',
        'dislikes_count' => 'integer',
        'approved_at' => 'datetime',
        'reply_sms_sent_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SPAM = 'spam';

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\Modules\CRM\Models\Customer::class, 'customer_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * تمام کامنت‌های یک thread (شامل ریشه و همه‌ی replies) — مفید برای admin show.
     */
    public function thread(): HasMany
    {
        return $this->hasMany(self::class, 'root_id', 'id')->orderBy('created_at');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}
