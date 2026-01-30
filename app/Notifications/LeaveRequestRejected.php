<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\Attendance\Models\LeaveRequest;

class LeaveRequestRejected extends Notification
{
    use Queueable;

    protected LeaveRequest $leaveRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(LeaveRequest $leaveRequest)
    {
        $this->leaveRequest = $leaveRequest;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $approverName = $this->leaveRequest->approver?->full_name ?? 'مدیریت';

        return [
            'title' => 'درخواست مرخصی رد شد',
            'body' => "درخواست مرخصی شما برای تاریخ {$this->leaveRequest->jalali_start_date} توسط {$approverName} رد شد.",
            'type' => 'leave_rejected',
            'icon' => 'calendar-x',
            'color' => 'red',
            'leave_request_id' => $this->leaveRequest->id,
            'leave_type' => $this->leaveRequest->leaveType?->name,
            'start_date' => $this->leaveRequest->jalali_start_date,
            'end_date' => $this->leaveRequest->jalali_end_date,
            'rejection_note' => $this->leaveRequest->approval_note,
            'url' => route('leave.show', $this->leaveRequest->id),
        ];
    }
}
