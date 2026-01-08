<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $fillable = [
        'work_start_time',
        'work_end_time',
        'late_tolerance_minutes',
        'lunch_duration_minutes',
        'verification_methods',
        'allowed_ips',
        'allowed_location_lat',
        'allowed_location_lng',
        'allowed_location_radius',
        'salary_type',
        'overtime_rate',
        'late_deduction_per_minute',
        'absence_deduction_per_day',
        'working_days',
    ];

    protected $casts = [
        'verification_methods' => 'array',
        'allowed_ips' => 'array',
        'working_days' => 'array',
        'allowed_location_lat' => 'decimal:8',
        'allowed_location_lng' => 'decimal:8',
        'overtime_rate' => 'decimal:2',
    ];

    public static function get(): self
    {
        return self::first() ?? self::create([
            'work_start_time' => '08:00:00',
            'work_end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'lunch_duration_minutes' => 30,
            'verification_methods' => ['trust'],
            'working_days' => [0, 1, 2, 3, 4],
        ]);
    }

    public function isVerificationRequired(string $method): bool
    {
        return in_array($method, $this->verification_methods ?? []);
    }

    public function isWorkingDay(int $dayOfWeek): bool
    {
        // 0 = Saturday (شنبه), 6 = Friday (جمعه)
        return in_array($dayOfWeek, $this->working_days ?? []);
    }
}
