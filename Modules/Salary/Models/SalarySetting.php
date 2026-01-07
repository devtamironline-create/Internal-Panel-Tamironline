<?php

namespace Modules\Salary\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySetting extends Model
{
    protected $fillable = [
        'housing_allowance',
        'food_allowance',
        'marriage_allowance',
        'child_allowance',
        'seniority_daily_rate',
        'employee_insurance_rate',
        'employer_insurance_rate',
        'overtime_regular_rate',
        'overtime_holiday_rate',
        'daily_work_hours',
        'monthly_work_days',
        'auto_calculate',
        'calculation_day',
    ];

    protected $casts = [
        'housing_allowance' => 'decimal:0',
        'food_allowance' => 'decimal:0',
        'marriage_allowance' => 'decimal:0',
        'child_allowance' => 'decimal:0',
        'seniority_daily_rate' => 'decimal:0',
        'employee_insurance_rate' => 'decimal:2',
        'employer_insurance_rate' => 'decimal:2',
        'overtime_regular_rate' => 'decimal:2',
        'overtime_holiday_rate' => 'decimal:2',
        'auto_calculate' => 'boolean',
    ];

    /**
     * Get the settings (singleton pattern)
     */
    public static function get(): self
    {
        return self::first() ?? self::create([]);
    }

    /**
     * Get minute rate for a daily wage
     * نرخ دقیقه‌ای = حقوق روزانه ÷ ساعت کاری ÷ 60
     */
    public function getMinuteRate(float $dailyWage): float
    {
        return $dailyWage / $this->daily_work_hours / 60;
    }

    /**
     * Get daily housing allowance
     */
    public function getDailyHousingAllowance(): float
    {
        return $this->housing_allowance / $this->monthly_work_days;
    }

    /**
     * Get daily food allowance
     */
    public function getDailyFoodAllowance(): float
    {
        return $this->food_allowance / $this->monthly_work_days;
    }

    /**
     * Get daily marriage allowance
     */
    public function getDailyMarriageAllowance(): float
    {
        return $this->marriage_allowance / $this->monthly_work_days;
    }

    /**
     * Get daily child allowance (per child)
     */
    public function getDailyChildAllowance(): float
    {
        return $this->child_allowance / $this->monthly_work_days;
    }
}
