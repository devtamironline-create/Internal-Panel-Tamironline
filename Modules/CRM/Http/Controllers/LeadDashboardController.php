<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Order;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * داشبورد گزارش لیدها — تحلیل تماس‌هایی که سفارش نشده‌اند.
 *
 * هم‌ساختار با CrmController::dashboard ولی روی Order::leads()
 * فیلتر می‌کند تا با آمار سفارش‌های واقعی قاطی نشود. متریک‌ها
 * مخصوص لید: دلیل عدم سفارش، شهر/منطقه، دستگاه، ایراد، روند
 * زمانی.
 */
class LeadDashboardController extends Controller
{
    public function index(Request $request)
    {
        // بازهٔ تاریخ — مثل داشبورد سفارش‌ها قابل تنظیم
        $fromJ = $request->string('from')->toString() ?: Jalalian::now()->subDays(30)->format('Y/m/d');
        $toJ   = $request->string('to')->toString()   ?: Jalalian::now()->format('Y/m/d');
        $fromG = $this->jalaliToGregorian($fromJ);
        $toG   = $this->jalaliToGregorian($toJ);
        if (! $fromG || ! $toG) {
            $fromCarbon = now()->subDays(30)->startOfDay();
            $toCarbon   = now()->endOfDay();
        } else {
            $fromCarbon = Carbon::parse($fromG)->startOfDay();
            $toCarbon   = Carbon::parse($toG)->endOfDay();
        }

        $chartPeriod = $request->query('chart_period', 'week');
        if (! in_array($chartPeriod, ['day', 'week', 'month'], true)) {
            $chartPeriod = 'week';
        }

        $baseQuery = fn () => Order::query()->leads()->whereBetween('created_at', [$fromCarbon, $toCarbon]);

        // کاشی‌های خلاصه
        $totalLeads        = (clone $baseQuery())->count();
        $convertedLeads    = (clone $baseQuery())->where('is_lead', false)->count();
        // ↑ leads() فقط is_lead=true را برمی‌گرداند، پس این 0 می‌شود.
        // برای شمارش تبدیل‌شده‌ها نیاز به کوئری جداگانه:
        $convertedInRange  = Order::query()->realOrders()
            ->whereBetween('updated_at', [$fromCarbon, $toCarbon])
            ->whereHas('statusLogs', function ($q) {
                $q->where('note', 'like', 'تبدیل لید به سفارش%');
            })
            ->count();

        // ─── دلایل عدم سفارش — مهم‌ترین گزارش ─────────────────
        $reasonBreakdown = (clone $baseQuery())
            ->whereNotNull('lead_reason_id')
            ->selectRaw('lead_reason_id, COUNT(*) as cnt')
            ->groupBy('lead_reason_id')
            ->orderByDesc('cnt')
            ->with('leadReason:id,name')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->leadReason?->name ?? '—',
                'count' => (int) $r->cnt,
            ]);

        $reasonNullCount = (clone $baseQuery())->whereNull('lead_reason_id')->count();

        // ─── پرتکرارترین دستگاه‌ها ────────────────────────────
        $topDevices = (clone $baseQuery())
            ->whereNotNull('device_id')
            ->with('device:id,name')
            ->selectRaw('device_id, COUNT(*) as cnt')
            ->groupBy('device_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // ─── پرتکرارترین برندها ───────────────────────────────
        $topBrands = (clone $baseQuery())
            ->whereNotNull('brand_id')
            ->with('brand:id,name')
            ->selectRaw('brand_id, COUNT(*) as cnt')
            ->groupBy('brand_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // ─── پرتکرارترین شهرها ────────────────────────────────
        $topCities = (clone $baseQuery())
            ->whereNotNull('city_id')
            ->with('city:id,name')
            ->selectRaw('city_id, COUNT(*) as cnt')
            ->groupBy('city_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // ─── پرتکرارترین مناطق ────────────────────────────────
        $topRegions = (clone $baseQuery())
            ->whereNotNull('region_id')
            ->with('region.city:id,name')
            ->selectRaw('region_id, COUNT(*) as cnt')
            ->groupBy('region_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // ─── پرتکرارترین ایرادها ──────────────────────────────
        $topProblems = (clone $baseQuery())
            ->whereNotNull('problem_title')
            ->where('problem_title', '!=', '')
            ->selectRaw('problem_title, COUNT(*) as cnt')
            ->groupBy('problem_title')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // ─── نمودار روند زمانی ────────────────────────────────
        $chartData = $this->buildChartData($chartPeriod);

        // ─── معرف‌ها ──────────────────────────────────────────
        $introBreakdown = (clone $baseQuery())
            ->whereNotNull('introduction')
            ->where('introduction', '!=', '')
            ->selectRaw('introduction, COUNT(*) as cnt')
            ->groupBy('introduction')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        return view('crm::leads.dashboard', compact(
            'fromJ', 'toJ', 'chartPeriod',
            'totalLeads', 'convertedInRange',
            'reasonBreakdown', 'reasonNullCount',
            'topDevices', 'topBrands', 'topCities', 'topRegions', 'topProblems',
            'introBreakdown',
            'chartData',
        ));
    }

    /**
     * داده‌های نمودار روند لیدها — روزانه (۳۰ روز)، هفتگی (۱۲ هفته)،
     * ماهانه (۶ ماه). هر point: تعداد لید ثبت‌شده در آن بازه.
     */
    protected function buildChartData(string $period): array
    {
        $labels = [];
        $counts = [];

        $latinDigits   = ['0','1','2','3','4','5','6','7','8','9'];
        $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

        if ($period === 'day') {
            for ($i = 29; $i >= 0; $i--) {
                $start = now()->subDays($i)->startOfDay();
                $end   = (clone $start)->endOfDay();
                $labels[] = str_replace(
                    $latinDigits, $persianDigits,
                    Jalalian::fromCarbon($start)->format('m/d')
                );
                $counts[] = Order::leads()->whereBetween('created_at', [$start, $end])->count();
            }
        } elseif ($period === 'month') {
            for ($i = 5; $i >= 0; $i--) {
                $start = now()->subMonths($i)->startOfMonth();
                $end   = now()->subMonths($i)->endOfMonth();
                $labels[] = str_replace(
                    $latinDigits, $persianDigits,
                    Jalalian::fromCarbon($start)->format('Y/m')
                );
                $counts[] = Order::leads()->whereBetween('created_at', [$start, $end])->count();
            }
        } else { // week (default)
            for ($i = 11; $i >= 0; $i--) {
                $start = now()->subWeeks($i)->startOfWeek(Carbon::SATURDAY);
                $end   = (clone $start)->endOfWeek(Carbon::FRIDAY);
                $labels[] = str_replace(
                    $latinDigits, $persianDigits,
                    Jalalian::fromCarbon($start)->format('m/d')
                );
                $counts[] = Order::leads()->whereBetween('created_at', [$start, $end])->count();
            }
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    protected function jalaliToGregorian(?string $jalaliDate): ?string
    {
        if (! $jalaliDate || trim($jalaliDate) === '') {
            return null;
        }
        $parts = preg_split('/[\/\-]/', $jalaliDate);
        if (count($parts) !== 3) {
            return null;
        }
        try {
            [$y, $m, $d] = array_map('intval', $parts);
            [$gy, $gm, $gd] = CalendarUtils::toGregorian($y, $m, $d);
            return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
