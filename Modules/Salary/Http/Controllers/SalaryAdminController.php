<?php

namespace Modules\Salary\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Salary\Models\Salary;
use Modules\Salary\Services\SalaryCalculator;
use Morilog\Jalali\Jalalian;

class SalaryAdminController extends Controller
{
    /**
     * لیست حقوق همه کارمندان
     */
    public function index(Request $request)
    {
        $period = Salary::getCurrentPeriod();
        $year = $request->get('year', $period['year']);
        $month = $request->get('month', $period['month']);

        $salaries = Salary::with('user')
            ->forPeriod($year, $month)
            ->latest()
            ->paginate(20);

        // Get available periods for filter
        $periods = Salary::selectRaw('DISTINCT year, month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(24)
            ->get();

        return view('salary::admin.index', compact('salaries', 'year', 'month', 'periods'));
    }

    /**
     * مشاهده جزئیات حقوق یک کارمند
     */
    public function show(Salary $salary)
    {
        $salary->load('user', 'calculator', 'approver');

        return view('salary::admin.show', compact('salary'));
    }

    /**
     * محاسبه حقوق برای همه کارمندان یک ماه
     */
    public function calculateAll(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:1400|max:1450',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year = $request->year;
        $month = $request->month;

        // Get all active users
        $users = User::where('is_active', true)->get();
        $calculator = new SalaryCalculator();

        $calculated = 0;
        foreach ($users as $user) {
            try {
                $calculator->calculate($user->id, $year, $month);
                $calculated++;
            } catch (\Exception $e) {
                // Log error
                \Log::error("Salary calculation failed for user {$user->id}: " . $e->getMessage());
            }
        }

        return back()->with('success', "حقوق {$calculated} کارمند محاسبه شد");
    }

    /**
     * محاسبه حقوق یک کارمند
     */
    public function calculate(Request $request, User $user)
    {
        $request->validate([
            'year' => 'required|integer|min:1400|max:1450',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $calculator = new SalaryCalculator();
        $salary = $calculator->calculate($user->id, $request->year, $request->month);

        return redirect()->route('salary.admin.show', $salary)
            ->with('success', 'حقوق محاسبه شد');
    }

    /**
     * ویرایش حقوق (اضافه کردن پاداش، مساعده، کسورات)
     */
    public function edit(Salary $salary)
    {
        $salary->load('user');

        return view('salary::admin.edit', compact('salary'));
    }

    /**
     * به‌روزرسانی حقوق
     */
    public function update(Request $request, Salary $salary)
    {
        $validated = $request->validate([
            'bonus' => 'nullable|numeric|min:0',
            'salary_difference' => 'nullable|numeric',
            'excess_leave' => 'nullable|numeric|min:0',
            'advance_insurance' => 'nullable|numeric|min:0',
            'advance' => 'nullable|numeric|min:0',
            'other_deductions' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $salary->update($validated);

        // Recalculate totals
        $calculator = new SalaryCalculator();
        $calculator->calculate($salary->user_id, $salary->year, $salary->month);

        return redirect()->route('salary.admin.show', $salary)
            ->with('success', 'حقوق بروزرسانی شد');
    }

    /**
     * تایید حقوق
     */
    public function approve(Salary $salary)
    {
        $salary->approve(auth()->id());

        return back()->with('success', 'حقوق تایید شد');
    }

    /**
     * تایید همه حقوق‌های یک ماه
     */
    public function approveAll(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer',
        ]);

        $count = Salary::forPeriod($request->year, $request->month)
            ->byStatus(Salary::STATUS_CALCULATED)
            ->update([
                'status' => Salary::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

        return back()->with('success', "{$count} حقوق تایید شد");
    }

    /**
     * علامت‌گذاری به عنوان پرداخت شده
     */
    public function markPaid(Salary $salary)
    {
        $salary->markAsPaid();

        return back()->with('success', 'حقوق به عنوان پرداخت شده ثبت شد');
    }

    /**
     * خروجی PDF برای مدیریت
     */
    public function pdf(Salary $salary)
    {
        $salary->load('user');

        return view('salary::admin.pdf', compact('salary'));
    }

    /**
     * خروجی اکسل لیست حقوق
     */
    public function export(Request $request)
    {
        $year = $request->get('year', Jalalian::now()->getYear());
        $month = $request->get('month', Jalalian::now()->getMonth());

        $salaries = Salary::with('user')
            ->forPeriod($year, $month)
            ->whereIn('status', [Salary::STATUS_APPROVED, Salary::STATUS_PAID])
            ->get();

        // Return CSV format for now
        $filename = "salaries_{$year}_{$month}.csv";
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($salaries) {
            $file = fopen('php://output', 'w');
            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, [
                'نام کارمند',
                'روز کارکرد',
                'حقوق بیمه‌ای',
                'مزایا',
                'اضافه‌کاری',
                'کسورات',
                'خالص',
                'وضعیت',
            ]);

            foreach ($salaries as $salary) {
                fputcsv($file, [
                    $salary->user->full_name,
                    $salary->work_days,
                    number_format($salary->fixed_insurance_salary),
                    number_format($salary->total_benefits),
                    number_format($salary->total_overtime),
                    number_format($salary->total_deductions),
                    number_format($salary->total_net_salary),
                    $salary->status_label,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
