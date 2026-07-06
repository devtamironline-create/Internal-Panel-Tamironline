<?php

namespace Modules\Site\Models\Forum;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $table = 'site_forum_reports';

    protected $fillable = [
        'reportable_type', 'reportable_id', 'reason', 'notes',
        'reporter_name', 'reporter_email', 'reporter_ip', 'user_agent',
        'status', 'reviewed_by_user_id', 'reviewed_at', 'admin_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_ACTIONED = 'actioned';

    public const REASONS = [
        'spam' => 'اسپم/تبلیغ',
        'inappropriate' => 'محتوای نامناسب',
        'wrong-info' => 'اطلاعات نادرست',
        'duplicate' => 'سوال تکراری',
        'other' => 'دیگر',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * بازگرداندن سوال یا پاسخ گزارش‌شده. ساده‌تر از polymorphic چون فقط دو نوع داریم.
     */
    public function reportable()
    {
        return match ($this->reportable_type) {
            'question' => Question::find($this->reportable_id),
            'answer' => Answer::find($this->reportable_id),
            'comment' => \Modules\Site\Models\Comment::find($this->reportable_id),
            default => null,
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
