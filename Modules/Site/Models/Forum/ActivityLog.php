<?php

namespace Modules\Site\Models\Forum;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'site_forum_activity_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'question_id', 'answer_id', 'meta', 'ip', 'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }
}
