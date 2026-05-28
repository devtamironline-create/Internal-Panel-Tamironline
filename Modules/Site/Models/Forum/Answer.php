<?php

namespace Modules\Site\Models\Forum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $table = 'site_forum_answers';

    protected $fillable = [
        'question_id', 'body',
        'author_name', 'author_email',
        'expert_id', 'is_expert_reply',
        'status', 'approved_at', 'approved_by_user_id',
        'is_accepted', 'accepted_at',
        'upvotes_count',
        'ip', 'user_agent',
    ];

    protected $casts = [
        'is_expert_reply' => 'boolean',
        'is_accepted' => 'boolean',
        'upvotes_count' => 'integer',
        'approved_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SPAM = 'spam';

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(Expert::class, 'expert_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
