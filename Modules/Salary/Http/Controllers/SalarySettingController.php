<?php

namespace Modules\Salary\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Salary\Models\SalarySetting;
use Modules\Attendance\Models\EmployeeSetting;
use App\Models\User;

class SalarySettingController extends Controller
{
    /**
     * نمایش تنظیمات حقوق
     */
    public function index()
    {
        $settings = SalarySetting::get();

        return view('salary::settings.index', compact('settings'));
    }

    /**
     * به‌روزرسانی تنظیمات
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'housing_allowance' => 'required|numeric|min:0',
            'food_allowance' => 'required|numeric|min:0',
            'marriage_allowance' => 'required|numeric|min:0',
            'child_allowance' => 'required|numeric|min:0',
            'seniority_daily_rate' => 'required|numeric|min:0',
            'employee_insurance_rate' => 'required|numeric|min:0|max:100',
            'employer_insurance_rate' => 'required|numeric|min:0|max:100',
            'overtime_regular_rate' => 'required|numeric|min:0',
            'overtime_holiday_rate' => 'required|numeric|min:0',
            'daily_work_hours' => 'required|integer|min:1|max:24',
            'monthly_work_days' => 'required|integer|min:1|max:31',
        ]);

        $settings = SalarySetting::get();
        $settings->update($validated);

        return back()->with('success', 'تنظیمات ذخیره شد');
    }

    /**
     * لیست تنظیمات حقوقی کارمندان
     */
    public function employees()
    {
        $employees = User::where('is_active', true)
            ->with('employeeSetting')
            ->orderBy('first_name')
            ->paginate(20);

        return view('salary::settings.employees', compact('employees'));
    }

    /**
     * ویرایش تنظیمات حقوقی یک کارمند
     */
    public function editEmployee(User $user)
    {
        $employeeSetting = EmployeeSetting::getOrCreate($user->id);

        return view('salary::settings.edit-employee', compact('user', 'employeeSetting'));
    }

    /**
     * به‌روزرسانی تنظیمات حقوقی کارمند
     */
    public function updateEmployee(Request $request, User $user)
    {
        $validated = $request->validate([
            'daily_agreed_wage' => 'required|numeric|min:0',
            'daily_insurance_wage' => 'required|numeric|min:0',
            'daily_declared_wage' => 'required|numeric|min:0',
            'is_married' => 'boolean',
            'children_count' => 'required|integer|min:0|max:10',
            'seniority_years' => 'required|integer|min:0|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:50',
            'sheba_number' => 'nullable|string|max:30',
        ]);

        $employeeSetting = EmployeeSetting::getOrCreate($user->id);
        $employeeSetting->update($validated);

        return redirect()->route('salary.settings.employees')
            ->with('success', "تنظیمات حقوقی {$user->full_name} ذخیره شد");
    }
}
