<?php

namespace Modules\Salary\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Salary\Models\Salary;
use Modules\Salary\Services\SalaryCalculator;
use Morilog\Jalali\Jalalian;

class SalaryController extends Controller
{
    /**
     * داشبورد حقوق کارمند - نمایش حقوق تا لحظه فعلی
     */
    public function dashboard()
    {
        $calculator = new SalaryCalculator();
        $currentSalary = $calculator->calculateCurrent(auth()->id());

        return view('salary::employee.dashboard', compact('currentSalary'));
    }

    /**
     * تاریخچه فیش‌های حقوقی
     */
    public function history()
    {
        $salaries = Salary::forUser(auth()->id())
            ->whereIn('status', [Salary::STATUS_APPROVED, Salary::STATUS_PAID])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12);

        return view('salary::employee.history', compact('salaries'));
    }

    /**
     * مشاهده فیش حقوقی یک ماه خاص
     */
    public function show(Salary $salary)
    {
        // فقط فیش خودش رو ببینه
        if ($salary->user_id !== auth()->id()) {
            abort(403);
        }

        // فقط فیش‌های تایید شده یا پرداخت شده
        if (!in_array($salary->status, [Salary::STATUS_APPROVED, Salary::STATUS_PAID])) {
            abort(404);
        }

        return view('salary::employee.show', compact('salary'));
    }

    /**
     * خروجی PDF فیش حقوقی
     */
    public function pdf(Salary $salary)
    {
        if ($salary->user_id !== auth()->id()) {
            abort(403);
        }

        if (!in_array($salary->status, [Salary::STATUS_APPROVED, Salary::STATUS_PAID])) {
            abort(404);
        }

        // For now, just return the show view with print layout
        return view('salary::employee.pdf', compact('salary'));
    }
}
