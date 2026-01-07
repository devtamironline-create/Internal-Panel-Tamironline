<?php

namespace Modules\Salary\Services;

use App\Models\User;
use Modules\Attendance\Models\Attendance;
use Modules\Attendance\Models\EmployeeSetting;
use Modules\Attendance\Models\LeaveRequest;
use Modules\Salary\Models\Salary;
use Modules\Salary\Models\SalarySetting;
use Morilog\Jalali\Jalalian;
use Carbon\Carbon;

class SalaryCalculator
{
    protected SalarySetting $settings;
    protected EmployeeSetting $employeeSettings;
    protected User $user;
    protected int $year;
    protected int $month;

    public function __construct()
    {
        $this->settings = SalarySetting::get();
    }

    /**
     * محاسبه حقوق برای یک کارمند در یک ماه مشخص
     */
    public function calculate(int $userId, int $year, int $month): Salary
    {
        $this->user = User::findOrFail($userId);
        $this->employeeSettings = EmployeeSetting::getOrCreate($userId);
        $this->year = $year;
        $this->month = $month;

        // Get or create salary record
        $salary = Salary::getOrCreateForPeriod($userId, $year, $month);

        // گرفتن اطلاعات حضور و غیاب
        $attendanceData = $this->getAttendanceData();

        // گرفتن اطلاعات مرخصی
        $leaveData = $this->getLeaveData();

        // حقوق‌های پایه از تنظیمات کارمند
        $dailyAgreedWage = $this->employeeSettings->daily_agreed_wage ?? 0;
        $dailyInsuranceWage = $this->employeeSettings->daily_insurance_wage ?? 0;
        $dailyDeclaredWage = $this->employeeSettings->daily_declared_wage ?? 0;
        $isMarried = $this->employeeSettings->is_married ?? false;
        $childrenCount = $this->employeeSettings->children_count ?? 0;
        $seniorityYears = $this->employeeSettings->seniority_years ?? 0;

        // روزهای کارکرد
        $workDays = $attendanceData['work_days'];

        // ۱. محاسبه تفاوت‌ها (K, L, M)
        $dailyDifferenceDeclared = $dailyDeclaredWage - $dailyInsuranceWage; // K
        $dailyDifferenceAgreed = $dailyAgreedWage - $dailyInsuranceWage; // L
        $monthlyNonInsurance = $dailyDifferenceAgreed * $workDays; // M

        // ۲. محاسبه مزایای مشمول بیمه (S-Y)
        $fixedInsuranceSalary = $dailyDeclaredWage * $workDays; // S
        $housingAllowance = $this->settings->getDailyHousingAllowance() * $workDays; // T
        $foodAllowance = $this->settings->getDailyFoodAllowance() * $workDays; // U
        $marriageAllowance = $isMarried ? ($this->settings->getDailyMarriageAllowance() * $workDays) : 0; // V
        $seniorityDaily = $this->settings->seniority_daily_rate * $seniorityYears; // W
        $seniorityMonthly = $seniorityDaily * $workDays; // X
        $childAllowance = $this->settings->getDailyChildAllowance() * $workDays * $childrenCount; // Y

        // ۳. جمع حقوق و مزایا (Z, AA)
        $totalBenefits = $fixedInsuranceSalary + $housingAllowance + $foodAllowance +
            $marriageAllowance + $seniorityDaily + $seniorityMonthly + $childAllowance; // Z
        $totalInsuranceBase = $totalBenefits - $seniorityMonthly - $childAllowance; // AA

        // ۴. محاسبه اضافه‌کاری
        // نرخ دقیقه‌ای = حقوق توافقی ÷ 30 ÷ 9 ÷ 60
        $minuteRate = $dailyAgreedWage / $this->settings->monthly_work_days /
            $this->settings->daily_work_hours / 60;

        $overtimeRegular = $minuteRate * $attendanceData['overtime_regular_minutes']; // اضافه‌کاری عادی
        $overtimeHoliday = $minuteRate * ($this->settings->overtime_holiday_rate / 100) *
            $attendanceData['overtime_holiday_minutes']; // اضافه‌کاری تعطیل
        $totalOvertime = $overtimeRegular + $overtimeHoliday; // N

        // ۵. کسورات
        $employeeInsurance = $totalInsuranceBase * ($this->settings->employee_insurance_rate / 100); // AD
        $employerInsurance = $totalInsuranceBase * ($this->settings->employer_insurance_rate / 100); // AJ
        $latePenalty = $attendanceData['late_minutes'] * $minuteRate; // AE
        $usedLeave = $leaveData['leave_minutes'] * $minuteRate; // AI

        // ۶. خالص پرداختی
        $netInsurancePayment = $totalBenefits - $employeeInsurance - $salary->advance_insurance; // AK
        $netAgreedPayment = $monthlyNonInsurance + $totalOvertime + $salary->bonus +
            $salary->salary_difference - $salary->excess_leave - $latePenalty -
            $salary->advance - $usedLeave + $employeeInsurance - $salary->other_deductions; // AL
        $totalNetSalary = $netInsurancePayment + $netAgreedPayment; // AM

        // جمع کسورات
        $totalDeductions = $employeeInsurance + $latePenalty + $usedLeave +
            $salary->advance_insurance + $salary->advance + $salary->other_deductions + $salary->excess_leave;

        // Update salary record
        $salary->update([
            'work_days' => $workDays,
            'work_minutes' => $attendanceData['work_minutes'],
            'late_minutes' => $attendanceData['late_minutes'],
            'early_leave_minutes' => $attendanceData['early_leave_minutes'],
            'overtime_regular_minutes' => $attendanceData['overtime_regular_minutes'],
            'overtime_holiday_minutes' => $attendanceData['overtime_holiday_minutes'],
            'leave_minutes' => $leaveData['leave_minutes'],
            'absent_days' => $attendanceData['absent_days'],

            'daily_agreed_wage' => $dailyAgreedWage,
            'daily_insurance_wage' => $dailyInsuranceWage,
            'daily_declared_wage' => $dailyDeclaredWage,

            'fixed_insurance_salary' => round($fixedInsuranceSalary),
            'housing_allowance' => round($housingAllowance),
            'food_allowance' => round($foodAllowance),
            'marriage_allowance' => round($marriageAllowance),
            'seniority_daily' => round($seniorityDaily),
            'seniority_monthly' => round($seniorityMonthly),
            'child_allowance' => round($childAllowance),

            'total_benefits' => round($totalBenefits),
            'total_insurance_base' => round($totalInsuranceBase),

            'daily_difference_declared' => round($dailyDifferenceDeclared),
            'daily_difference_agreed' => round($dailyDifferenceAgreed),
            'monthly_non_insurance' => round($monthlyNonInsurance),

            'overtime_regular' => round($overtimeRegular),
            'overtime_holiday' => round($overtimeHoliday),
            'total_overtime' => round($totalOvertime),

            'employee_insurance' => round($employeeInsurance),
            'employer_insurance' => round($employerInsurance),
            'late_penalty' => round($latePenalty),
            'used_leave' => round($usedLeave),
            'total_deductions' => round($totalDeductions),

            'net_insurance_payment' => round($netInsurancePayment),
            'net_agreed_payment' => round($netAgreedPayment),
            'total_net_salary' => round($totalNetSalary),

            'status' => Salary::STATUS_CALCULATED,
            'calculated_by' => auth()->id(),
            'calculated_at' => now(),
        ]);

        return $salary->fresh();
    }

    /**
     * محاسبه حقوق تا لحظه فعلی (برای نمایش به کارمند)
     */
    public function calculateCurrent(int $userId): array
    {
        $this->user = User::findOrFail($userId);
        $this->employeeSettings = EmployeeSetting::getOrCreate($userId);

        $jalali = Jalalian::now();
        $this->year = $jalali->getYear();
        $this->month = $jalali->getMonth();

        // اطلاعات حضور و غیاب تا امروز
        $attendanceData = $this->getAttendanceData();
        $leaveData = $this->getLeaveData();

        // حقوق‌های پایه
        $dailyAgreedWage = $this->employeeSettings->daily_agreed_wage ?? 0;
        $dailyInsuranceWage = $this->employeeSettings->daily_insurance_wage ?? 0;
        $dailyDeclaredWage = $this->employeeSettings->daily_declared_wage ?? 0;
        $isMarried = $this->employeeSettings->is_married ?? false;
        $childrenCount = $this->employeeSettings->children_count ?? 0;
        $seniorityYears = $this->employeeSettings->seniority_years ?? 0;

        $workDays = $attendanceData['work_days'];

        // محاسبه سریع
        $minuteRate = $dailyAgreedWage > 0 ? ($dailyAgreedWage / $this->settings->monthly_work_days /
            $this->settings->daily_work_hours / 60) : 0;

        // مزایا
        $fixedInsuranceSalary = $dailyDeclaredWage * $workDays;
        $housingAllowance = $this->settings->getDailyHousingAllowance() * $workDays;
        $foodAllowance = $this->settings->getDailyFoodAllowance() * $workDays;
        $marriageAllowance = $isMarried ? ($this->settings->getDailyMarriageAllowance() * $workDays) : 0;
        $childAllowance = $this->settings->getDailyChildAllowance() * $workDays * $childrenCount;

        $totalBenefits = $fixedInsuranceSalary + $housingAllowance + $foodAllowance +
            $marriageAllowance + $childAllowance;
        $totalInsuranceBase = $totalBenefits - $childAllowance;

        // مابه‌التفاوت
        $monthlyNonInsurance = ($dailyAgreedWage - $dailyInsuranceWage) * $workDays;

        // اضافه‌کاری
        $overtimeRegular = $minuteRate * $attendanceData['overtime_regular_minutes'];
        $overtimeHoliday = $minuteRate * ($this->settings->overtime_holiday_rate / 100) *
            $attendanceData['overtime_holiday_minutes'];
        $totalOvertime = $overtimeRegular + $overtimeHoliday;

        // کسورات
        $employeeInsurance = $totalInsuranceBase * ($this->settings->employee_insurance_rate / 100);
        $latePenalty = $attendanceData['late_minutes'] * $minuteRate;
        $usedLeave = $leaveData['leave_minutes'] * $minuteRate;

        // خالص تقریبی
        $estimatedNet = ($totalBenefits - $employeeInsurance) + ($monthlyNonInsurance + $totalOvertime - $latePenalty - $usedLeave);

        return [
            'period' => $this->year . '/' . sprintf('%02d', $this->month),
            'period_label' => $this->getMonthName($this->month) . ' ' . $this->year,
            'current_day' => $jalali->getDay(),
            'days_in_month' => $jalali->getMonthDays(),

            'work_days' => $workDays,
            'work_hours' => $this->formatMinutes($attendanceData['work_minutes']),
            'late_minutes' => $attendanceData['late_minutes'],
            'late_time' => $this->formatMinutes($attendanceData['late_minutes']),
            'overtime_minutes' => $attendanceData['overtime_regular_minutes'] + $attendanceData['overtime_holiday_minutes'],
            'overtime_hours' => $this->formatMinutes($attendanceData['overtime_regular_minutes'] + $attendanceData['overtime_holiday_minutes']),
            'absent_days' => $attendanceData['absent_days'],

            'daily_agreed_wage' => $dailyAgreedWage,

            'fixed_insurance_salary' => round($fixedInsuranceSalary),
            'housing_allowance' => round($housingAllowance),
            'food_allowance' => round($foodAllowance),
            'marriage_allowance' => round($marriageAllowance),
            'child_allowance' => round($childAllowance),
            'total_benefits' => round($totalBenefits),

            'monthly_non_insurance' => round($monthlyNonInsurance),
            'total_overtime' => round($totalOvertime),

            'employee_insurance' => round($employeeInsurance),
            'late_penalty' => round($latePenalty),
            'used_leave' => round($usedLeave),

            'estimated_net' => round($estimatedNet),
        ];
    }

    /**
     * گرفتن اطلاعات حضور و غیاب از ماژول Attendance
     */
    protected function getAttendanceData(): array
    {
        // Convert Jalali to Gregorian date range
        $startDate = Jalalian::fromFormat('Y/m/d', $this->year . '/' . $this->month . '/01')->toCarbon();
        $endDate = $startDate->copy()->endOfMonth();

        // اگر ماه جاری است، فقط تا امروز محاسبه کن
        if ($this->year == Jalalian::now()->getYear() && $this->month == Jalalian::now()->getMonth()) {
            $endDate = Carbon::today();
        }

        $attendances = Attendance::where('user_id', $this->user->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $workDays = $attendances->where('status', Attendance::STATUS_PRESENT)->count();
        $workMinutes = $attendances->sum('work_minutes');
        $lateMinutes = $attendances->sum('late_minutes');
        $earlyLeaveMinutes = $attendances->sum('early_leave_minutes');
        $overtimeMinutes = $attendances->sum('overtime_minutes');
        $absentDays = $attendances->where('status', Attendance::STATUS_ABSENT)->count();

        // TODO: جداسازی اضافه‌کاری عادی و تعطیل
        // فعلا همه را عادی در نظر می‌گیریم
        $overtimeRegularMinutes = $overtimeMinutes;
        $overtimeHolidayMinutes = 0;

        return [
            'work_days' => $workDays,
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'overtime_regular_minutes' => $overtimeRegularMinutes,
            'overtime_holiday_minutes' => $overtimeHolidayMinutes,
            'absent_days' => $absentDays,
        ];
    }

    /**
     * گرفتن اطلاعات مرخصی از ماژول Leave
     */
    protected function getLeaveData(): array
    {
        $startDate = Jalalian::fromFormat('Y/m/d', $this->year . '/' . $this->month . '/01')->toCarbon();
        $endDate = $startDate->copy()->endOfMonth();

        $leaves = LeaveRequest::where('user_id', $this->user->id)
            ->where('status', LeaveRequest::STATUS_APPROVED)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            })
            ->get();

        $leaveDays = $leaves->sum('days_count');
        $leaveHours = $leaves->sum('hours_count');

        // Convert to minutes
        $leaveMinutes = ($leaveDays * $this->settings->daily_work_hours * 60) + ($leaveHours * 60);

        return [
            'leave_days' => $leaveDays,
            'leave_hours' => $leaveHours,
            'leave_minutes' => $leaveMinutes,
        ];
    }

    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
            4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
            7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
            10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
        ];

        return $months[$month] ?? '';
    }

    protected function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%d:%02d', $hours, $mins);
    }
}
