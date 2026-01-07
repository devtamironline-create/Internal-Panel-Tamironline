<?php

namespace Modules\Attendance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSetting extends Model
{
    protected $fillable = [
        'user_id',
        'work_start_time',
        'work_end_time',
        'base_salary',
        'hourly_rate',
        'annual_leave_balance',
        'sick_leave_balance',
        'supervisor_id',
        // Salary fields
        'daily_agreed_wage',
        'daily_insurance_wage',
        'daily_declared_wage',
        'is_married',
        'children_count',
        'seniority_years',
        'bank_name',
        'bank_account',
        'sheba_number',
    ];

    protected $casts = [
        'base_salary' => 'decimal:0',
        'hourly_rate' => 'decimal:0',
        'daily_agreed_wage' => 'decimal:0',
        'daily_insurance_wage' => 'decimal:0',
        'daily_declared_wage' => 'decimal:0',
        'is_married' => 'boolean',
        'children_count' => 'integer',
        'seniority_years' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public static function getOrCreate(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'annual_leave_balance' => 26,
                'sick_leave_balance' => 12,
            ]
        );
    }

    public function getWorkStartTime(): string
    {
        return $this->work_start_time ?? AttendanceSetting::get()->work_start_time;
    }

    public function getWorkEndTime(): string
    {
        return $this->work_end_time ?? AttendanceSetting::get()->work_end_time;
    }
}
