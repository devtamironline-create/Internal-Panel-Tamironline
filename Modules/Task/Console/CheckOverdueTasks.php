<?php

namespace Modules\Task\Console;

use Illuminate\Console\Command;
use Modules\Task\Models\Task;
use Modules\Task\Notifications\TaskNotification;

class CheckOverdueTasks extends Command
{
    protected $signature = 'tasks:check-overdue';
    protected $description = 'ارسال یادآوری برای تسک‌هایی که ددلاینشان گذشته';

    public function handle(): int
    {
        $tasks = Task::with(['assignee', 'team'])
            ->needsReminder()
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('هیچ تسک overdue بدون یادآوری یافت نشد.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($tasks as $task) {
            $assignee = $task->assignee;
            if (!$assignee) {
                continue;
            }

            $jalaliDue = $task->jalali_due_date ?? 'نامشخص';
            $message = "ددلاین تسک «{$task->title}» ({$jalaliDue}) گذشته و هنوز تکمیل نشده است.";

            $assignee->notify(new TaskNotification($task, 'overdue_reminder', $message));

            $task->update(['reminder_sent_at' => now()]);

            $task->activities()->create([
                'user_id' => $assignee->id,
                'action' => 'reminder_sent',
                'description' => "یادآوری ددلاین ارسال شد به {$assignee->full_name}",
            ]);

            $count++;
            $this->line("  یادآوری: {$task->title} → {$assignee->full_name}");
        }

        $this->info("تعداد {$count} یادآوری ارسال شد.");
        return self::SUCCESS;
    }
}
