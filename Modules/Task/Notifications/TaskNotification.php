<?php

namespace Modules\Task\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Task\Models\Task;

class TaskNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $type,
        public string $message
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'team_id' => $this->task->team_id,
            'type' => $this->type,
            'message' => $this->message,
            'url' => route('tasks.show', $this->task->id),
        ];
    }
}
