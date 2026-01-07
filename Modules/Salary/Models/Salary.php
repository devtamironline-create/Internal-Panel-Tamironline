<?php

namespace Modules\Salary\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Morilog\Jalali\Jalalian;

class Salary extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'month',
        'work_days',
        'work_minutes',
        'late_minutes',
        'early_leave_minutes',
        'overtime_regular_minutes',
        'overtime_holiday_minutes',
        'leave_minutes',
        'absent_days',
        'daily_agreed_wage',
        'daily_insurance_wage',
        'daily_declared_wage',
        'fixed_insurance_salary',
        'housing_allowance',
        'food_allowance',
        'marriage_allowance',
        'seniority_daily',
        'seniority_monthly',
        'child_allowance',
        'total_benefits',
        'total_insurance_base',
        'daily_difference_declared',
        'daily_difference_agreed',
        'monthly_non_insurance',
        'overtime_regular',
        'overtime_holiday',
        'total_overtime',
        'bonus',
        'salary_difference',
        'employee_insurance',
        'employer_insurance',
        'late_penalty',
        'excess_leave',
        'used_leave',
        'advance_insurance',
        'advance',
        'other_deductions',
        'total_deductions',
        'net_insurance_payment',
        'net_agreed_payment',
        'total_net_salary',
        'status',
        'notes',
        'calculated_by',
        'calculated_at',
        'approved_by',
        'approved_at',
        'paid_at',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_CALCULATED = 'calculated';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Accessors
    public function getPeriodAttribute(): string
    {
        return sprintf('%04d/%02d', $this->year, $this->month);
    }

    public function getPeriodLabelAttribute(): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        return $months[$this->month] . ' ' . $this->year;
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'پیش‌نویس',
            self::STATUS_CALCULATED => 'محاسبه شده',
            self::STATUS_APPROVED => 'تایید شده',
            self::STATUS_PAID => 'پرداخت شده',
            default => 'نامشخص',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_CALCULATED => 'blue',
            self::STATUS_APPROVED => 'green',
            self::STATUS_PAID => 'purple',
            default => 'gray',
        };
    }

    public function getWorkHoursAttribute(): string
    {
        $hours = intdiv($this->work_minutes, 60);
        $minutes = $this->work_minutes % 60;
        return sprintf('%d:%02d', $hours, $minutes);
    }

    public function getOvertimeHoursAttribute(): string
    {
        $totalMinutes = $this->overtime_regular_minutes + $this->overtime_holiday_minutes;
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        return sprintf('%d:%02d', $hours, $minutes);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // Methods
    public function approve(int $approverId): self
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return $this;
    }

    public function markAsPaid(): self
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return $this;
    }

    /**
     * Get current period (current Jalali month)
     */
    public static function getCurrentPeriod(): array
    {
        $jalali = Jalalian::now();
        return [
            'year' => $jalali->getYear(),
            'month' => $jalali->getMonth(),
        ];
    }

    /**
     * Get or create salary for user and period
     */
    public static function getOrCreateForPeriod(int $userId, int $year, int $month): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'year' => $year, 'month' => $month],
            ['status' => self::STATUS_DRAFT]
        );
    }
}
