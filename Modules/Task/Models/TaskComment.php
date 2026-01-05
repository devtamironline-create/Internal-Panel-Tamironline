<?php

namespace Modules\Task\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskComment extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'body',
        'parent_id',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'parent_id');
    }

    public static function addComment(Task $task, int $userId, string $body, ?int $parentId = null): self
    {
        $comment = self::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'body' => $body,
            'parent_id' => $parentId,
        ]);

        // Log activity
        $task->logActivity('commented', null, null, null, 'نظر جدید اضافه شد');

        // Notify task assignee and creator
        $notifyUsers = collect([$task->assigned_to, $task->created_by])
            ->filter()
            ->unique()
            ->reject(fn($id) => $id === $userId);

        foreach ($notifyUsers as $notifyUserId) {
            $task->notifyUser($notifyUserId, 'commented', "نظر جدید روی تسک «{$task->title}»");
        }

        return $comment;
    }
}
