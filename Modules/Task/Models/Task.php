<?php

namespace Modules\Task\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Morilog\Jalali\Jalalian;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'team_id',
        'created_by',
        'assigned_to',
        'parent_id',
        'status',
        'priority',
        'due_date',
        'started_at',
        'completed_at',
        'estimated_hours',
        'actual_hours',
        'progress',
        'sort_order',
    ];

    protected $casts = [
        'due_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    const STATUS_BACKLOG = 'backlog';
    const STATUS_TODO = 'todo';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_REVIEW = 'review';
    const STATUS_DONE = 'done';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Relationships
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('sort_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at', 'desc');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->orderBy('created_at', 'desc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_label');
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_BACKLOG => 'بک‌لاگ',
            self::STATUS_TODO => 'در انتظار',
            self::STATUS_IN_PROGRESS => 'در حال انجام',
            self::STATUS_REVIEW => 'بررسی',
            self::STATUS_DONE => 'تکمیل شده',
            default => 'نامشخص',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_BACKLOG => 'gray',
            self::STATUS_TODO => 'blue',
            self::STATUS_IN_PROGRESS => 'yellow',
            self::STATUS_REVIEW => 'purple',
            self::STATUS_DONE => 'green',
            default => 'gray',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'کم',
            self::PRIORITY_MEDIUM => 'متوسط',
            self::PRIORITY_HIGH => 'بالا',
            self::PRIORITY_URGENT => 'فوری',
            default => 'نامشخص',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'gray',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_URGENT => 'red',
            default => 'gray',
        };
    }

    public function getJalaliDueDateAttribute(): ?string
    {
        return $this->due_date ? Jalalian::fromDateTime($this->due_date)->format('Y/m/d') : null;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== self::STATUS_DONE;
    }

    public function getChecklistProgressAttribute(): array
    {
        $total = $this->checklists()->count();
        $completed = $this->checklists()->where('is_completed', true)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    // Scopes
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', self::STATUS_DONE);
    }

    public function scopeMainTasks($query)
    {
        return $query->whereNull('parent_id');
    }

    // Methods
    public static function createTask(array $data, int $creatorId): self
    {
        $task = self::create([
            ...$data,
            'created_by' => $creatorId,
        ]);

        // Log activity
        $task->logActivity('created', null, null, null, 'تسک ایجاد شد');

        return $task;
    }

    public function updateStatus(string $newStatus, int $userId): self
    {
        $oldStatus = $this->status;

        $this->status = $newStatus;

        if ($newStatus === self::STATUS_IN_PROGRESS && !$this->started_at) {
            $this->started_at = now();
        }

        if ($newStatus === self::STATUS_DONE) {
            $this->completed_at = now();
            $this->progress = 100;
        }

        $this->save();

        $this->logActivity('status_changed', 'status', $oldStatus, $newStatus);

        // Send notification to assignee if not the same user
        if ($this->assigned_to && $this->assigned_to !== $userId) {
            $this->notifyUser($this->assigned_to, 'status_changed', "وضعیت تسک به {$this->status_label} تغییر کرد");
        }

        return $this;
    }

    public function assignTo(?int $userId, int $assignerId): self
    {
        $oldAssignee = $this->assigned_to;
        $this->assigned_to = $userId;
        $this->save();

        $this->logActivity('assigned', 'assigned_to', $oldAssignee, $userId);

        // Notify new assignee
        if ($userId && $userId !== $assignerId) {
            $this->notifyUser($userId, 'assigned', "تسک «{$this->title}» به شما واگذار شد");
        }

        return $this;
    }

    public function logActivity(string $action, ?string $field = null, $oldValue = null, $newValue = null, ?string $description = null): TaskActivity
    {
        return $this->activities()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
        ]);
    }

    public function notifyUser(int $userId, string $type, string $message): void
    {
        $user = User::find($userId);
        if ($user) {
            $user->notify(new \Modules\Task\Notifications\TaskNotification($this, $type, $message));
        }
    }

    // Static methods for reports
    public static function getUserStats(int $userId): array
    {
        return [
            'total' => self::forUser($userId)->count(),
            'completed' => self::forUser($userId)->byStatus(self::STATUS_DONE)->count(),
            'in_progress' => self::forUser($userId)->byStatus(self::STATUS_IN_PROGRESS)->count(),
            'overdue' => self::forUser($userId)->overdue()->count(),
        ];
    }
}
