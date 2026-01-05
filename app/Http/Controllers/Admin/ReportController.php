<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Service\Models\Service;
use Morilog\Jalali\Jalalian;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.index');
    }

    /**
     * Monthly renewals report
     */
    public function monthlyRenewals(Request $request)
    {
        // Get services with next_due_date within the requested year
        $year = $request->input('year', Jalalian::now()->getYear());

        $services = Service::whereNotNull('next_due_date')
            ->where('status', 'active')
            ->get();

        $monthlyData = [];
        $persianMonths = [
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند',
        ];

        // Initialize all months
        foreach ($persianMonths as $num => $name) {
            $monthlyData[$num] = [
                'month' => $num,
                'month_name' => $name,
                'count' => 0,
                'amount' => 0,
            ];
        }

        // Group services by Jalali month
        foreach ($services as $service) {
            try {
                $jalali = Jalalian::fromDateTime($service->next_due_date);
                $serviceYear = $jalali->getYear();
                $serviceMonth = $jalali->getMonth();

                if ($serviceYear == $year) {
                    $monthlyData[$serviceMonth]['count']++;
                    $monthlyData[$serviceMonth]['amount'] += $service->price ?? 0;
                }
            } catch (\Exception $e) {
                // Skip invalid dates
                continue;
            }
        }

        // Calculate totals
        $totalCount = array_sum(array_column($monthlyData, 'count'));
        $totalAmount = array_sum(array_column($monthlyData, 'amount'));

        return view('admin.reports.monthly-renewals', compact('monthlyData', 'year', 'totalCount', 'totalAmount'));
    }
}
