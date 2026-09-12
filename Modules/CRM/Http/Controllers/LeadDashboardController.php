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
        // پیش‌تنظیم‌های سریع — وقتی preset ست شده، تاریخ‌های from/to
        // را override می‌کند تا اپراتور تاریخ دستی وارد نکند.
        $preset = $request->string('preset')->toString();
        [$fromCarbon, $toCarbon] = $this->resolveDateRange($preset, $request);

        $fromJ = Jalalian::fromCarbon($fromCarbon)->format('Y/m/d');
        $toJ = Jalalian::fromCarbon($toCarbon)->format('Y/m/d');

        $chartPeriod = $request->query('chart_period', 'week');
        if (! in_array($chartPeriod, ['day', 'week', 'month'], true)) {
            $chartPeriod = 'week';
        }

        $baseQuery = fn () => Order::query()->leads()->whereBetween('created_at', [$fromCarbon, $toCarbon]);

        // کاشی‌های خلاصه
        $totalLeads = (clone $baseQuery())->count();
        $convertedLeads = (clone $baseQuery())->where('is_lead', false)->count();
        // ↑ leads() فقط is_lead=true را برمی‌گرداند، پس این 0 می‌شود.
        // برای شمارش تبدیل‌شده‌ها نیاز به کوئری جداگانه:
        $convertedInRange = Order::query()->realOrders()
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
                'id' => (int) $r->lead_reason_id,
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
            ->whereNotNull('district_id')
            ->with('district.parent:id,name')
            ->selectRaw('district_id, COUNT(*) as cnt')
            ->groupBy('district_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // ─── نمودار روند زمانی ────────────────────────────────
        $chartData = $this->buildChartData($chartPeriod);

        // ─── نمودار ساعتی — چه ساعت‌هایی لید بیشتر می‌آید ──────
        // بر اساسِ همان بازهٔ انتخاب‌شده (from/to)، نه یک دورهٔ ثابت.
        $hourlyData = $this->buildHourlyData($fromCarbon, $toCarbon);

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
            'topDevices', 'topBrands', 'topCities', 'topRegions',
            'introBreakdown',
            'chartData', 'hourlyData',
        ));
    }

    /**
     * توزیعِ ساعتیِ لیدها در بازهٔ انتخاب‌شده — ۲۴ سطل (۰ تا ۲۳) بر اساسِ
     * ساعتِ ثبت (created_at). ساعتِ اوج هم برگردانده می‌شود.
     * SQL به تفکیکِ درایور (MySQL: HOUR، SQLite: strftime) تا تست هم کار کند.
     *
     * @return array{labels: array<int,string>, counts: array<int,int>, peak_hour: ?int, peak_count: int}
     */
    protected function buildHourlyData(Carbon $from, Carbon $to): array
    {
        $hourExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';

        $rows = Order::query()->leads()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw($hourExpr.' as h, COUNT(*) as cnt')
            ->groupBy('h')
            ->pluck('cnt', 'h');

        $map = [];
        foreach ($rows as $h => $c) {
            $map[(int) $h] = (int) $c;
        }

        $latinDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

        $labels = [];
        $counts = [];
        $peakHour = null;
        $peakCount = 0;
        for ($h = 0; $h < 24; $h++) {
            $labels[] = str_replace($latinDigits, $persianDigits, sprintf('%02d', $h));
            $c = $map[$h] ?? 0;
            $counts[] = $c;
            if ($c > $peakCount) {
                $peakCount = $c;
                $peakHour = $h;
            }
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
            'peak_hour' => $peakHour,
            'peak_count' => $peakCount,
        ];
    }

    /**
     * بازهٔ تاریخ از روی preset سریع (today/yesterday/last_week/last_month)
     * یا تاریخ‌های دستی from/to. اگر preset غیرمجاز/خالی بود و از/تا هم
     * نبود، ۳۰ روز اخیر را برمی‌گرداند.
     */
    protected function resolveDateRange(string $preset, Request $request): array
    {
        switch ($preset) {
            case 'today':
                return [now()->startOfDay(), now()->endOfDay()];
            case 'yesterday':
                return [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()];
            case 'last_week':
                return [now()->subDays(7)->startOfDay(), now()->endOfDay()];
            case 'last_month':
                return [now()->subMonth()->startOfDay(), now()->endOfDay()];
        }

        // تاریخ دستی شمسی از فرم
        $fromJ = $request->string('from')->toString() ?: Jalalian::now()->subDays(30)->format('Y/m/d');
        $toJ = $request->string('to')->toString() ?: Jalalian::now()->format('Y/m/d');
        $fromG = $this->jalaliToGregorian($fromJ);
        $toG = $this->jalaliToGregorian($toJ);
        if (! $fromG || ! $toG) {
            return [now()->subDays(30)->startOfDay(), now()->endOfDay()];
        }

        return [Carbon::parse($fromG)->startOfDay(), Carbon::parse($toG)->endOfDay()];
    }

    /**
     * داده‌های نمودار روند لیدها — روزانه (۳۰ روز)، هفتگی (۱۲ هفته)،
     * ماهانه (۶ ماه). هر point: تعداد لید ثبت‌شده در آن بازه.
     */
    protected function buildChartData(string $period): array
    {
        $labels = [];
        $counts = [];

        $latinDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

        if ($period === 'day') {
            for ($i = 29; $i >= 0; $i--) {
                $start = now()->subDays($i)->startOfDay();
                $end = (clone $start)->endOfDay();
                $labels[] = str_replace(
                    $latinDigits, $persianDigits,
                    Jalalian::fromCarbon($start)->format('m/d')
                );
                $counts[] = Order::leads()->whereBetween('created_at', [$start, $end])->count();
            }
        } elseif ($period === 'month') {
            for ($i = 5; $i >= 0; $i--) {
                $start = now()->subMonths($i)->startOfMonth();
                $end = now()->subMonths($i)->endOfMonth();
                $labels[] = str_replace(
                    $latinDigits, $persianDigits,
                    Jalalian::fromCarbon($start)->format('Y/m')
                );
                $counts[] = Order::leads()->whereBetween('created_at', [$start, $end])->count();
            }
        } else { // week (default)
            for ($i = 11; $i >= 0; $i--) {
                $start = now()->subWeeks($i)->startOfWeek(Carbon::SATURDAY);
                $end = (clone $start)->endOfWeek(Carbon::FRIDAY);
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
