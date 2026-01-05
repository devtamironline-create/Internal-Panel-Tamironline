<?php

namespace Modules\Attendance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class LeaveRequest extends Model
{
    protected $fillable = [
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'days_count',
        'hours_count',
        'reason',
        'document_path',
        'status',
        'approved_by',
        'approved_at',
        'approval_note',
        'substitute_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'days_count' => 'decimal:2',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_id');
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'در انتظار تایید',
            self::STATUS_APPROVED => 'تایید شده',
            self::STATUS_REJECTED => 'رد شده',
            self::STATUS_CANCELLED => 'لغو شده',
            default => 'نامشخص',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_APPROVED => 'green',
            self::STATUS_REJECTED => 'red',
            self::STATUS_CANCELLED => 'gray',
            default => 'gray',
        };
    }

    public function getJalaliStartDateAttribute(): string
    {
        return Jalalian::fromDateTime($this->start_date)->format('Y/m/d');
    }

    public function getJalaliEndDateAttribute(): string
    {
        return Jalalian::fromDateTime($this->end_date)->format('Y/m/d');
    }

    public function getDurationTextAttribute(): string
    {
        if ($this->leaveType && $this->leaveType->is_hourly) {
            return $this->hours_count . ' ساعت';
        }

        if ($this->days_count == 0.5) {
            return 'نیم روز';
        }

        return (int)$this->days_count . ' روز';
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForApproval($query, int $supervisorId)
    {
        // Get users who have this supervisor
        $userIds = EmployeeSetting::where('supervisor_id', $supervisorId)
            ->pluck('user_id')
            ->toArray();

        return $query->whereIn('user_id', $userIds)->pending();
    }

    // Methods
    public static function createRequest(array $data): self
    {
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        // Calculate days count
        $startDate = \Carbon\Carbon::parse($data['start_date']);
        $endDate = \Carbon\Carbon::parse($data['end_date']);

        $daysCount = $data['days_count'] ?? $startDate->diffInDays($endDate) + 1;

        // For hourly leave
        $hoursCount = null;
        if ($leaveType->is_hourly && isset($data['start_time']) && isset($data['end_time'])) {
            $startTime = \Carbon\Carbon::createFromTimeString($data['start_time']);
            $endTime = \Carbon\Carbon::createFromTimeString($data['end_time']);
            $hoursCount = $endTime->diffInHours($startTime);
        }

        return self::create([
            'user_id' => $data['user_id'],
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'days_count' => $daysCount,
            'hours_count' => $hoursCount,
            'reason' => $data['reason'] ?? null,
            'document_path' => $data['document_path'] ?? null,
            'substitute_id' => $data['substitute_id'] ?? null,
            'status' => self::STATUS_PENDING,
        ]);
    }

    public function approve(int $approverId, ?string $note = null): self
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
            'approval_note' => $note,
        ]);

        // Update leave balance
        $this->deductFromBalance();

        // Update attendance records for these days
        $this->markAttendanceAsLeave();

        return $this;
    }

    public function reject(int $approverId, ?string $note = null): self
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approverId,
            'approved_at' => now(),
            'approval_note' => $note,
        ]);

        return $this;
    }

    public function cancel(): self
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \Exception('فقط درخواست‌های در انتظار قابل لغو هستند');
        }

        $this->update(['status' => self::STATUS_CANCELLED]);

        return $this;
    }

    protected function deductFromBalance(): void
    {
        $employeeSettings = EmployeeSetting::getOrCreate($this->user_id);

        if ($this->leaveType->slug === 'annual' || $this->leaveType->slug === 'hourly') {
            // Convert hours to days if hourly (8 hours = 1 day)
            $daysToDeduct = $this->leaveType->is_hourly
                ? $this->hours_count / 8
                : $this->days_count;

            $employeeSettings->decrement('annual_leave_balance', $daysToDeduct);
        } elseif ($this->leaveType->slug === 'sick') {
            $employeeSettings->decrement('sick_leave_balance', $this->days_count);
        }
    }

    protected function markAttendanceAsLeave(): void
    {
        $currentDate = $this->start_date->copy();

        while ($currentDate <= $this->end_date) {
            Attendance::updateOrCreate(
                [
                    'user_id' => $this->user_id,
                    'date' => $currentDate->toDateString(),
                ],
                [
                    'status' => Attendance::STATUS_LEAVE,
                    'notes' => $this->leaveType->name,
                ]
            );

            $currentDate->addDay();
        }
    }

    // Get pending requests count for a supervisor
    public static function getPendingCountForSupervisor(int $supervisorId): int
    {
        $userIds = EmployeeSetting::where('supervisor_id', $supervisorId)
            ->pluck('user_id')
            ->toArray();

        return self::whereIn('user_id', $userIds)
            ->where('status', self::STATUS_PENDING)
            ->count();
    }

    // Check if user has enough balance
    public static function checkBalance(int $userId, int $leaveTypeId, float $days): bool
    {
        $leaveType = LeaveType::find($leaveTypeId);
        $employeeSettings = EmployeeSetting::getOrCreate($userId);

        if ($leaveType->slug === 'annual' || $leaveType->slug === 'hourly') {
            return $employeeSettings->annual_leave_balance >= $days;
        } elseif ($leaveType->slug === 'sick') {
            return $employeeSettings->sick_leave_balance >= $days;
        }

        // Unpaid leave - no balance check
        return true;
    }
}
