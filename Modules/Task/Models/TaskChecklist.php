<?php

namespace Modules\Task\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskChecklist extends Model
{
    protected $fillable = [
        'task_id',
        'title',
        'is_completed',
        'completed_by',
        'completed_at',
        'sort_order',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function toggle(int $userId): self
    {
        $this->is_completed = !$this->is_completed;

        if ($this->is_completed) {
            $this->completed_by = $userId;
            $this->completed_at = now();
        } else {
            $this->completed_by = null;
            $this->completed_at = null;
        }

        $this->save();

        // Log activity
        $this->task->logActivity(
            $this->is_completed ? 'checklist_completed' : 'checklist_uncompleted',
            null,
            null,
            null,
            $this->is_completed ? "چک‌لیست «{$this->title}» تکمیل شد" : "چک‌لیست «{$this->title}» برداشته شد"
        );

        return $this;
    }
}
