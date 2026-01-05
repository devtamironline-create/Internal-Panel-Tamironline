<?php

namespace Modules\Attendance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'check_in',
        'check_in_ip',
        'check_in_location',
        'check_in_selfie',
        'check_out',
        'check_out_ip',
        'check_out_location',
        'check_out_selfie',
        'late_minutes',
        'early_leave_minutes',
        'overtime_minutes',
        'work_minutes',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_location' => 'array',
        'check_out_location' => 'array',
    ];

    const STATUS_PRESENT = 'present';
    const STATUS_ABSENT = 'absent';
    const STATUS_LEAVE = 'leave';
    const STATUS_HOLIDAY = 'holiday';
    const STATUS_INCOMPLETE = 'incomplete';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PRESENT => 'حاضر',
            self::STATUS_ABSENT => 'غایب',
            self::STATUS_LEAVE => 'مرخصی',
            self::STATUS_HOLIDAY => 'تعطیل',
            self::STATUS_INCOMPLETE => 'ناقص',
            default => 'نامشخص'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PRESENT => 'green',
            self::STATUS_ABSENT => 'red',
            self::STATUS_LEAVE => 'blue',
            self::STATUS_HOLIDAY => 'gray',
            self::STATUS_INCOMPLETE => 'yellow',
            default => 'gray'
        };
    }

    public function getJalaliDateAttribute(): string
    {
        return Jalalian::fromDateTime($this->date)->format('Y/m/d');
    }

    public function getWorkHoursAttribute(): string
    {
        $hours = intdiv($this->work_minutes, 60);
        $minutes = $this->work_minutes % 60;
        return sprintf('%d:%02d', $hours, $minutes);
    }

    public function getLateTimeAttribute(): string
    {
        if ($this->late_minutes <= 0) return '-';
        $hours = intdiv($this->late_minutes, 60);
        $minutes = $this->late_minutes % 60;
        return $hours > 0 ? sprintf('%d:%02d', $hours, $minutes) : "{$minutes} دقیقه";
    }

    public static function getTodayForUser(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('date', today())
            ->first();
    }

    public static function checkIn(int $userId, array $data = []): self
    {
        $attendance = self::firstOrCreate(
            ['user_id' => $userId, 'date' => today()],
            ['status' => self::STATUS_INCOMPLETE]
        );

        if ($attendance->check_in) {
            throw new \Exception('قبلا ورود ثبت شده است');
        }

        $now = now();
        $settings = AttendanceSetting::get();
        $employeeSettings = EmployeeSetting::getOrCreate($userId);

        $workStart = $employeeSettings->getWorkStartTime();
        $workStartTime = \Carbon\Carbon::createFromTimeString($workStart);
        $toleranceTime = $workStartTime->copy()->addMinutes($settings->late_tolerance_minutes);

        $lateMinutes = 0;
        if ($now->format('H:i:s') > $toleranceTime->format('H:i:s')) {
            $lateMinutes = $now->diffInMinutes($workStartTime);
        }

        $attendance->update([
            'check_in' => $now->format('H:i:s'),
            'check_in_ip' => $data['ip'] ?? request()->ip(),
            'check_in_location' => $data['location'] ?? null,
            'check_in_selfie' => $data['selfie'] ?? null,
            'late_minutes' => $lateMinutes,
        ]);

        return $attendance;
    }

    public static function checkOut(int $userId, array $data = []): self
    {
        $attendance = self::where('user_id', $userId)
            ->where('date', today())
            ->first();

        if (!$attendance || !$attendance->check_in) {
            throw new \Exception('ابتدا باید ورود ثبت شود');
        }

        if ($attendance->check_out) {
            throw new \Exception('قبلا خروج ثبت شده است');
        }

        $now = now();
        $settings = AttendanceSetting::get();
        $employeeSettings = EmployeeSetting::getOrCreate($userId);

        $workEnd = $employeeSettings->getWorkEndTime();
        $workEndTime = \Carbon\Carbon::createFromTimeString($workEnd);

        $earlyLeaveMinutes = 0;
        $overtimeMinutes = 0;

        if ($now->format('H:i:s') < $workEndTime->format('H:i:s')) {
            $earlyLeaveMinutes = $workEndTime->diffInMinutes($now);
        } elseif ($now->format('H:i:s') > $workEndTime->format('H:i:s')) {
            $overtimeMinutes = $now->diffInMinutes($workEndTime);
        }

        // Calculate total work minutes
        $checkInTime = \Carbon\Carbon::createFromTimeString($attendance->check_in);
        $workMinutes = $now->diffInMinutes($checkInTime);

        $attendance->update([
            'check_out' => $now->format('H:i:s'),
            'check_out_ip' => $data['ip'] ?? request()->ip(),
            'check_out_location' => $data['location'] ?? null,
            'check_out_selfie' => $data['selfie'] ?? null,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'work_minutes' => $workMinutes,
            'status' => self::STATUS_PRESENT,
        ]);

        return $attendance;
    }
}
