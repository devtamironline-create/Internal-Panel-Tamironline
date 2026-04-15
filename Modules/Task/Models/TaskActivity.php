<?php

namespace Modules\Task\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskActivity extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'action',
        'field',
        'old_value',
        'new_value',
        'description',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'created' => 'تسک را ایجاد کرد',
            'updated' => $this->getUpdatedLabel(),
            'status_changed' => $this->getStatusChangedLabel(),
            'assigned' => $this->getAssignedLabel(),
            'commented' => 'نظر ثبت کرد',
            'checklist_added' => 'چک‌لیست اضافه کرد',
            'checklist_completed' => 'چک‌لیست را تکمیل کرد',
            'checklist_uncompleted' => 'چک‌لیست را برداشت',
            'attachment_added' => 'فایل پیوست کرد',
            'deleted' => 'تسک را حذف کرد',
            default => $this->action,
        };
    }

    private function getStatusChangedLabel(): string
    {
        $from = self::translateStatus($this->old_value);
        $to = self::translateStatus($this->new_value);
        return "وضعیت را از «{$from}» به «{$to}» تغییر داد";
    }

    private function getUpdatedLabel(): string
    {
        $fieldLabel = self::translateField($this->field);
        if ($this->field === 'priority') {
            $from = self::translatePriority($this->old_value);
            $to = self::translatePriority($this->new_value);
            return "اولویت را از «{$from}» به «{$to}» تغییر داد";
        }
        if ($this->field === 'title') {
            return "عنوان را از «{$this->old_value}» به «{$this->new_value}» تغییر داد";
        }
        return "{$fieldLabel} را ویرایش کرد";
    }

    private function getAssignedLabel(): string
    {
        if ($this->new_value) {
            $user = \App\Models\User::find($this->new_value);
            $name = $user ? $user->full_name : 'نامشخص';
            return "تسک را به «{$name}» واگذار کرد";
        }
        return 'مسئول تسک را برداشت';
    }

    public static function translateStatus(?string $status): string
    {
        return match($status) {
            'backlog' => 'بک‌لاگ',
            'todo' => 'در انتظار',
            'in_progress' => 'در حال انجام',
            'review' => 'بررسی',
            'done' => 'تکمیل شده',
            default => $status ?? 'نامشخص',
        };
    }

    public static function translatePriority(?string $priority): string
    {
        return match($priority) {
            'low' => 'کم',
            'medium' => 'متوسط',
            'high' => 'بالا',
            'urgent' => 'فوری',
            default => $priority ?? 'نامشخص',
        };
    }

    public static function translateField(?string $field): string
    {
        return match($field) {
            'title' => 'عنوان',
            'description' => 'توضیحات',
            'priority' => 'اولویت',
            'status' => 'وضعیت',
            'assigned_to' => 'مسئول',
            'due_date' => 'ددلاین',
            default => $field ?? '',
        };
    }

    public function getActionIconAttribute(): string
    {
        return match($this->action) {
            'created' => 'M12 6v6m0 0v6m0-6h6m-6 0H6',
            'updated' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            'status_changed' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'assigned' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            'commented' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
            'checklist_completed' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
            'reminder_sent' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
            default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        };
    }
}
