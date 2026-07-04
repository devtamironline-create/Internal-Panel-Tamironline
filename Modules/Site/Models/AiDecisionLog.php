<?php

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * لاگِ هر تصمیم/پیشنهادِ AI — برای حسابرسی و نمایشِ آخرین پیشنهاد روی هر آیتم.
 */
class AiDecisionLog extends Model
{
    protected $table = 'ai_decision_logs';

    protected $fillable = [
        'ai_model_id', 'task', 'subject_type', 'subject_id',
        'decision', 'confidence', 'reason', 'applied',
        'prompt_tokens', 'completion_tokens', 'raw_response', 'created_by_user_id',
    ];

    protected $casts = [
        'confidence' => 'decimal:3',
        'applied' => 'boolean',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'raw_response' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function aiModel()
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    /** برچسبِ فارسیِ تصمیم. */
    public function decisionLabel(): string
    {
        return match ($this->decision) {
            'approve' => 'تأیید',
            'reject' => 'رد',
            'spam' => 'اسپم',
            default => 'نامشخص',
        };
    }

    public function decisionBadgeClass(): string
    {
        return match ($this->decision) {
            'approve' => 'bg-emerald-100 text-emerald-800',
            'reject' => 'bg-amber-100 text-amber-800',
            'spam' => 'bg-rose-100 text-rose-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }
}
