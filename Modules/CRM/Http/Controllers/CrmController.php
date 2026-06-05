<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Morilog\Jalali\Jalalian;

class CrmController extends Controller
{
    public function dashboard(Request $request)
    {
        // ─── فیلتر بازه‌ی تاریخ شمسی ────────────────────────────────
        // پیش‌فرض: امروز برای «کاشی‌های روزانه»، ماه برای بقیه
        $fromJ = trim((string) $request->query('from_date', ''));
        $toJ = trim((string) $request->query('to_date', ''));
        $fromG = $this->jalaliToGregorian($fromJ);
        $toG = $this->jalaliToGregorian($toJ);
        if (! $fromG) {
            $fromG = now()->startOfMonth()->toDateString();
            $fromJ = Jalalian::fromCarbon(now()->startOfMonth())->format('Y/m/d');
        }
        if (! $toG) {
            $toG = now()->toDateString();
            $toJ = Jalalian::now()->format('Y/m/d');
        }
        $fromCarbon = Carbon::parse($fromG)->startOfDay();
        $toCarbon = Carbon::parse($toG)->endOfDay();

        $chartPeriod = $request->query('chart_period', 'week');
        if (! in_array($chartPeriod, ['week', 'month'], true)) {
            $chartPeriod = 'week';
        }

        // ─── 1) سفارش‌های روزانه به تفکیک نحوه آشنایی (کاشی) ─────
        // امروز را اولویت می‌دهیم، اگر بازه انتخاب شده، کل بازه را
        // (همهٔ آمار داشبورد لیدها را مستثنی می‌کند تا تا تبدیل به
        //  سفارش انجام نشده، آمار سفارش‌ها را گمراه نکنند.)
        $introBreakdown = Order::query()->realOrders()
            ->whereBetween('created_at', [$fromCarbon, $toCarbon])
            ->whereNotNull('introduction')
            ->where('introduction', '!=', '')
            ->selectRaw('introduction, COUNT(*) as cnt')
            ->groupBy('introduction')
            ->orderByDesc('cnt')
            ->get();

        $introTotal = $introBreakdown->sum('cnt');
        // سفارش‌های بدون introduction
        $introNullCount = Order::query()->realOrders()
            ->whereBetween('created_at', [$fromCarbon, $toCarbon])
            ->where(function ($q) { $q->whereNull('introduction')->orWhere('introduction', ''); })
            ->count();

        // ─── 2) پرتکرارترین خدمات (دستگاه‌ها) ─────────────────────
        $topDevices = Order::query()->realOrders()
            ->whereBetween('created_at', [$fromCarbon, $toCarbon])
            ->whereNotNull('device_id')
            ->with('device:id,name')
            ->selectRaw('device_id,
                COUNT(*) as total_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as cancelled_count
            ',
            [
                OrderStatus::Completed->value,
                OrderStatus::Cancelled->value,
                OrderStatus::Declined->value,
            ])
            ->groupBy('device_id')
            ->orderByDesc('total_count')
            ->limit(5)
            ->get();

        // ─── 3) کارت‌های خلاصه ────────────────────────────────────
        $ordersInRange = Order::realOrders()->whereBetween('created_at', [$fromCarbon, $toCarbon])->count();
        $ordersCompletedInRange = Order::realOrders()->whereBetween('created_at', [$fromCarbon, $toCarbon])
            ->where('status', OrderStatus::Completed->value)->count();
        $ordersCancelledInRange = Order::realOrders()->whereBetween('created_at', [$fromCarbon, $toCarbon])
            ->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Declined->value])->count();
        $ordersOpenAll = Order::realOrders()->whereIn('status', [
            OrderStatus::New->value,
            OrderStatus::Coordinated->value,
            OrderStatus::Open->value,
            OrderStatus::Suspended->value,
        ])->count();

        $customersTotal = Customer::count();
        $techsActive = Technician::where('status', 'active')->count();

        // ─── 5) نمودار روند سفارش‌ها (هفتگی یا ماهانه) ─────────────
        $chartData = $this->buildOrdersChartData($chartPeriod);

        // ─── 6) سفارش‌های باز با تأخیر بیشتر از ۳ روز ──────────────
        $threeDaysAgo = now()->subDays(3);
        $delayedOpenOrders = Order::query()->realOrders()
            ->with(['customer:id,first_name,mobile', 'technician:id,first_name,last_name,firstname_tech,mobile', 'device:id,name', 'brand:id,name'])
            ->whereIn('status', [
                OrderStatus::Coordinated->value,
                OrderStatus::Open->value,
                OrderStatus::Suspended->value,
            ])
            ->whereNotNull('technician_id')
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<', $threeDaysAgo)
            ->orderBy('assigned_at') // قدیمی‌ترین اول
            ->limit(50)
            ->get();
        $delayedOpenCount = Order::query()->realOrders()
            ->whereIn('status', [
                OrderStatus::Coordinated->value,
                OrderStatus::Open->value,
                OrderStatus::Suspended->value,
            ])
            ->whereNotNull('technician_id')
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<', $threeDaysAgo)
            ->count();

        return view('crm::dashboard', [
            'fromJ' => $fromJ,
            'toJ' => $toJ,
            'chartPeriod' => $chartPeriod,

            'introBreakdown' => $introBreakdown,
            'introTotal' => $introTotal,
            'introNullCount' => $introNullCount,

            'topDevices' => $topDevices,

            'ordersInRange' => $ordersInRange,
            'ordersCompletedInRange' => $ordersCompletedInRange,
            'ordersCancelledInRange' => $ordersCancelledInRange,
            'ordersOpenAll' => $ordersOpenAll,
            'customersTotal' => $customersTotal,
            'techsActive' => $techsActive,

            'chartData' => $chartData,

            'delayedOpenOrders' => $delayedOpenOrders,
            'delayedOpenCount' => $delayedOpenCount,
        ]);
    }

    /**
     * داده‌های نمودار روند سفارش‌ها — هفتگی (۱۲ هفته اخیر) یا
     * ماهانه (۶ ماه اخیر). هر point: total / completed / cancelled.
     */
    private function buildOrdersChartData(string $period): array
    {
        $points = [];
        $labels = [];
        $totals = [];
        $completed = [];
        $cancelled = [];

        if ($period === 'month') {
            // ۶ ماه اخیر (شامل ماه جاری)
            for ($i = 5; $i >= 0; $i--) {
                $start = now()->subMonths($i)->startOfMonth();
                $end = now()->subMonths($i)->endOfMonth();
                $labels[] = Jalalian::fromCarbon($start)->format('Y/m');
                $totals[] = Order::realOrders()->whereBetween('created_at', [$start, $end])->count();
                $completed[] = Order::realOrders()->whereBetween('created_at', [$start, $end])
                    ->where('status', OrderStatus::Completed->value)->count();
                $cancelled[] = Order::realOrders()->whereBetween('created_at', [$start, $end])
                    ->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Declined->value])->count();
            }
        } else {
            // ۱۲ هفته اخیر (هفته منتهی به امروز)
            for ($i = 11; $i >= 0; $i--) {
                $start = now()->subWeeks($i)->startOfWeek(Carbon::SATURDAY);
                $end = (clone $start)->endOfWeek(Carbon::FRIDAY);
                $labels[] = Jalalian::fromCarbon($start)->format('m/d');
                $totals[] = Order::realOrders()->whereBetween('created_at', [$start, $end])->count();
                $completed[] = Order::realOrders()->whereBetween('created_at', [$start, $end])
                    ->where('status', OrderStatus::Completed->value)->count();
                $cancelled[] = Order::realOrders()->whereBetween('created_at', [$start, $end])
                    ->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Declined->value])->count();
            }
        }

        return [
            'labels' => $labels,
            'totals' => $totals,
            'completed' => $completed,
            'cancelled' => $cancelled,
        ];
    }

    private function jalaliToGregorian(?string $jalaliDate): ?string
    {
        if (! $jalaliDate || trim($jalaliDate) === '') {
            return null;
        }
        $latin = strtr($jalaliDate, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $latin = str_replace('-', '/', trim($latin));
        try {
            return Jalalian::fromFormat('Y/m/d', $latin)->toCarbon()->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
