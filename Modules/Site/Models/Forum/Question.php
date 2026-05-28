<?php

namespace Modules\Site\Models\Forum;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;

class Question extends Model
{
    protected $table = 'site_forum_questions';

    protected $fillable = [
        'slug', 'title', 'body', 'model', 'tags',
        'device_id', 'brand_id',
        'author_name', 'author_email', 'author_token',
        'status', 'approved_at', 'approved_by_user_id',
        'resolution_status',
        'view_count', 'upvotes_count', 'answers_count',
        'is_hot', 'is_featured',
        'ip', 'user_agent',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_hot' => 'boolean',
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'upvotes_count' => 'integer',
        'answers_count' => 'integer',
        'published_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SPAM = 'spam';

    public const RES_UNANSWERED = 'unanswered';

    public const RES_ANSWERED = 'answered';

    public const RES_EXPERT_ANSWERED = 'expert-answered';

    public const RES_SOLVED = 'solved';

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'question_id');
    }

    public function approvedAnswers(): HasMany
    {
        return $this->answers()->where('status', Answer::STATUS_APPROVED);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED)->whereNotNull('published_at');
    }

    /**
     * بازمحاسبه‌ی resolution_status بر اساس پاسخ‌های موجود.
     */
    public function recomputeResolution(): void
    {
        $approved = $this->approvedAnswers()->get();
        $accepted = $approved->firstWhere('is_accepted', true);
        $hasExpert = $approved->where('is_expert_reply', true)->isNotEmpty();

        $this->resolution_status = match (true) {
            $accepted !== null => self::RES_SOLVED,
            $hasExpert => self::RES_EXPERT_ANSWERED,
            $approved->isNotEmpty() => self::RES_ANSWERED,
            default => self::RES_UNANSWERED,
        };
        $this->answers_count = $approved->count();
        $this->save();
    }
}
