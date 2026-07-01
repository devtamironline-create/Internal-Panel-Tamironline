<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Morilog\Jalali\Jalalian;

class DashboardController extends Controller
{
    /**
     * داشبوردِ اولِ پنل — «وضعیت کلیِ خدمات تعمیرات» با فیلترِ بازهٔ شمسی و نمودار.
     *
     * منطقِ آمار: «کل = ثبتِ سفارش + لید». بازه‌ها بر پایهٔ تقویمِ شمسی‌اند.
     * آماره‌ها try/catch‌اند تا صفحهٔ اولِ پنل هیچ‌وقت ۵۰۰ نشود.
     */
    public function admin(Request $request)
    {
        $user = auth()->user();

        // کلِ ساختِ داشبورد در try/catch است تا صفحهٔ اولِ پنل تحتِ هیچ شرایطی
        // ۵۰۰ نشود؛ در صورتِ خطا، داده‌های امنِ خالی نمایش داده می‌شوند.
        try {
            // ─── بازهٔ شمسی (پیش‌فرض: از اولِ ماهِ شمسی تا امروز) ───────
            [$fromCarbon, $toCarbon, $fromJ, $toJ] = $this->resolveRange($request);

            return view('admin.dashboard', [
                'stats' => [
                    'snapshot' => $this->snapshotStats(),
                    'range' => $this->rangeStats($fromCarbon, $toCarbon),
                    'birthdays' => $this->getUpcomingBirthdays(),
                ],
                'recentOrders' => $this->recentOrders(),
                'hourly' => $this->hourlyToday(),
                'trend' => $this->trendRange($fromCarbon, $toCarbon),
                'statusBreakdown' => $this->statusBreakdownRange($fromCarbon, $toCarbon),
                'topDevices' => $this->topDevicesRange($fromCarbon, $toCarbon),
                'presets' => $this->presets(),
                'activePreset' => $this->activePreset($fromJ, $toJ),
                'fromJ' => $fromJ,
                'toJ' => $toJ,
                'user' => $user,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return view('admin.dashboard', $this->safeFallback($user));
        }
    }

    /**
     * داده‌های امنِ خالی — وقتی ساختِ داشبورد با خطا مواجه شود، پنل به‌جای ۵۰۰
     * با مقادیرِ صفر رندر می‌شود.
     *
     * @return array<string, mixed>
     */
    protected function safeFallback($user): array
    {
        $todayJ = Jalalian::now()->format('Y/m/d');

        return [
            'stats' => [
                'snapshot' => ['open' => 0, 'delayed_open' => 0, 'techs_active' => 0, 'customers_total' => 0],
                'range' => ['orders' => 0, 'leads' => 0, 'total' => 0, 'cancelled' => 0],
                'birthdays' => ['today' => [], 'upcoming' => [], 'total_upcoming' => 0],
            ],
            'recentOrders' => collect(),
            'hourly' => ['labels' => [], 'total' => [], 'leads' => []],
            'trend' => ['labels' => [], 'total' => [], 'leads' => []],
            'statusBreakdown' => ['labels' => [], 'data' => [], 'colors' => []],
            'topDevices' => ['labels' => [], 'data' => []],
            'presets' => [],
            'activePreset' => 'custom',
            'fromJ' => $todayJ,
            'toJ' => $todayJ,
            'user' => $user,
        ];
    }

    /**
     * بازهٔ زمانی از روی درخواست (from_date/to_date شمسی). پیش‌فرض: اولِ ماهِ
     * شمسیِ جاری تا امروز.
     *
     * @return array{0:Carbon,1:Carbon,2:string,3:string}
     */
    protected function resolveRange(Request $request): array
    {
        $fromJ = trim((string) $request->query('from_date', ''));
        $toJ = trim((string) $request->query('to_date', ''));
        $fromG = $this->jalaliToGregorian($fromJ);
        $toG = $this->jalaliToGregorian($toJ);

        if (! $fromG) {
            // پیش‌فرض: امروز (هم‌خوان با پیش‌فرضِ «امروز» در دکمه‌ها).
            $fromCarbon = now()->startOfDay();
            $fromJ = Jalalian::now()->format('Y/m/d');
        } else {
            $fromCarbon = Carbon::parse($fromG)->startOfDay();
        }

        if (! $toG) {
            $toCarbon = now()->endOfDay();
            $toJ = Jalalian::now()->format('Y/m/d');
        } else {
            $toCarbon = Carbon::parse($toG)->endOfDay();
        }

        // اگر بازه برعکس وارد شد، جابه‌جا کن.
        if ($fromCarbon->gt($toCarbon)) {
            [$fromCarbon, $toCarbon] = [$toCarbon->copy()->startOfDay(), $fromCarbon->copy()->endOfDay()];
            [$fromJ, $toJ] = [$toJ, $fromJ];
        }

        return [$fromCarbon, $toCarbon, $fromJ, $toJ];
    }

    /**
     * آماره‌های لحظه‌ای (مستقل از بازه): باز، معطل، تکنسینِ فعال، مشتری‌ها.
     *
     * @return array<string, int>
     */
    protected function snapshotStats(): array
    {
        try {
            $threeDaysAgo = now()->subDays(3);

            return [
                'open' => Order::realOrders()->whereIn('status', [
                    OrderStatus::New->value, OrderStatus::Coordinated->value,
                    OrderStatus::Open->value, OrderStatus::Suspended->value,
                ])->count(),
                'delayed_open' => Order::realOrders()
                    ->whereIn('status', [
                        OrderStatus::Coordinated->value, OrderStatus::Open->value, OrderStatus::Suspended->value,
                    ])
                    ->whereNotNull('technician_id')
                    ->whereNotNull('assigned_at')
                    ->where('assigned_at', '<', $threeDaysAgo)
                    ->count(),
                'techs_active' => Technician::where('status', 'active')->count(),
                'customers_total' => Customer::count(),
            ];
        } catch (\Throwable $e) {
            return ['open' => 0, 'delayed_open' => 0, 'techs_active' => 0, 'customers_total' => 0];
        }
    }

    /**
     * آماره‌های بازه: کل = سفارش + لید. (بدونِ آمارِ «تکمیل‌شده».)
     *
     * @return array<string, int>
     */
    protected function rangeStats(Carbon $from, Carbon $to): array
    {
        try {
            $orders = Order::realOrders()->whereBetween('created_at', [$from, $to])->count();
            $leads = Order::leads()->whereBetween('created_at', [$from, $to])->count();

            return [
                'orders' => $orders,
                'leads' => $leads,
                'total' => $orders + $leads,
                'cancelled' => Order::realOrders()->whereBetween('created_at', [$from, $to])
                    ->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Declined->value])
                    ->count(),
            ];
        } catch (\Throwable $e) {
            return ['orders' => 0, 'leads' => 0, 'total' => 0, 'cancelled' => 0];
        }
    }

    /**
     * سفارش‌های امروز به تفکیکِ ساعت — «کل» (سفارش+لید) و «لید».
     *
     * @return array{labels:array<int,string>, total:array<int,int>, leads:array<int,int>}
     */
    protected function hourlyToday(): array
    {
        $labels = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02d', $h);
        }
        $total = array_fill(0, 24, 0);
        $leads = array_fill(0, 24, 0);

        try {
            $todayStart = now()->startOfDay();
            Order::query()->whereBetween('created_at', [$todayStart, now()->endOfDay()])
                ->get(['created_at', 'is_lead'])
                ->each(function ($o) use (&$total, &$leads) {
                    if (! $o->created_at) {
                        return;
                    }
                    $h = (int) $o->created_at->format('G');
                    $total[$h]++;
                    if ($o->is_lead) {
                        $leads[$h]++;
                    }
                });
        } catch (\Throwable $e) {
            // آرایه‌های صفر
        }

        return ['labels' => $labels, 'total' => array_values($total), 'leads' => array_values($leads)];
    }

    /**
     * روندِ بازه — «کل» (سفارش+لید) و «لید»؛ روزانه (≤۴۵ روز) وگرنه هفتگی.
     * برچسب‌ها شمسی.
     *
     * @return array{labels:array<int,string>, total:array<int,int>, leads:array<int,int>}
     */
    protected function trendRange(Carbon $from, Carbon $to): array
    {
        $labels = [];
        $total = [];
        $leads = [];

        try {
            $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
            $step = $days <= 45 ? 1 : 7;

            $cursor = $from->copy()->startOfDay();
            $limit = $to->copy()->endOfDay();
            while ($cursor->lte($limit)) {
                $s = $cursor->copy()->startOfDay();
                $e = $step === 1 ? $cursor->copy()->endOfDay() : $cursor->copy()->addDays(6)->endOfDay();
                if ($e->gt($limit)) {
                    $e = $limit->copy();
                }
                $labels[] = Jalalian::fromCarbon($s)->format('m/d');
                $total[] = Order::query()->whereBetween('created_at', [$s, $e])->count();
                $leads[] = Order::leads()->whereBetween('created_at', [$s, $e])->count();
                $cursor->addDays($step);
            }
        } catch (\Throwable $e) {
            $labels = $total = $leads = [];
        }

        return compact('labels', 'total', 'leads');
    }

    /**
     * توزیعِ وضعیتِ سفارش‌های بازه (donut). فقط سفارش‌های واقعی.
     *
     * @return array{labels:array<int,string>, data:array<int,int>, colors:array<int,string>}
     */
    protected function statusBreakdownRange(Carbon $from, Carbon $to): array
    {
        $hex = [
            'new' => '#9ca3af', 'coordinated' => '#3b82f6', 'open' => '#6366f1',
            'suspended' => '#f59e0b', 'completed' => '#10b981', 'transit' => '#f97316',
            'returned' => '#fb923c', 'cancelled' => '#ef4444', 'declined' => '#b91c1c',
        ];
        $labels = [];
        $data = [];
        $colors = [];

        try {
            // status را با alias می‌خوانیم تا cast enumِ مدل روی مقدارِ نامعتبر
            // (مثلِ کدهای عددیِ legacy) ValueError پرتاب نکند.
            $rows = Order::realOrders()->whereBetween('created_at', [$from, $to])
                ->selectRaw('status as status_val, COUNT(*) as cnt')->groupBy('status')->get();

            foreach ($rows as $row) {
                $case = OrderStatus::tryFrom((string) $row->status_val);
                if (! $case || (int) $row->cnt === 0) {
                    continue;
                }
                $labels[] = $case->label();
                $data[] = (int) $row->cnt;
                $colors[] = $hex[$case->value] ?? '#94a3b8';
            }
        } catch (\Throwable $e) {
            $labels = $data = $colors = [];
        }

        return compact('labels', 'data', 'colors');
    }

    /**
     * پرتکرارترین دستگاه‌ها در بازه (میله‌ای).
     *
     * @return array{labels:array<int,string>, data:array<int,int>}
     */
    protected function topDevicesRange(Carbon $from, Carbon $to): array
    {
        $labels = [];
        $data = [];

        try {
            $rows = Order::realOrders()->whereBetween('created_at', [$from, $to])
                ->whereNotNull('device_id')
                ->with('device:id,name')
                ->selectRaw('device_id, COUNT(*) as cnt')
                ->groupBy('device_id')->orderByDesc('cnt')->limit(7)->get();

            foreach ($rows as $row) {
                $labels[] = $row->device->name ?? '—';
                $data[] = (int) $row->cnt;
            }
        } catch (\Throwable $e) {
            $labels = $data = [];
        }

        return compact('labels', 'data');
    }

    /**
     * پیش‌فرض‌های بازهٔ آماده (شمسی): امروز، دیروز، هفتهٔ گذشته، ماهِ گذشته.
     *
     * @return array<string, array{label:string, from:string, to:string}>
     */
    protected function presets(): array
    {
        $todayJ = Jalalian::now()->format('Y/m/d');
        $weekStart = Jalalian::now()->getFirstDayOfWeek(); // شنبهٔ همین هفته

        return [
            'today' => ['label' => 'امروز', 'from' => $todayJ, 'to' => $todayJ],
            'yesterday' => [
                'label' => 'دیروز',
                'from' => Jalalian::now()->subDays(1)->format('Y/m/d'),
                'to' => Jalalian::now()->subDays(1)->format('Y/m/d'),
            ],
            'last_week' => [
                'label' => 'هفتهٔ گذشته',
                'from' => $weekStart->subDays(7)->format('Y/m/d'),
                'to' => $weekStart->subDays(1)->format('Y/m/d'),
            ],
            'last_month' => [
                'label' => 'ماهِ گذشته',
                'from' => Jalalian::now()->subMonths(1)->getFirstDayOfMonth()->format('Y/m/d'),
                'to' => Jalalian::now()->getFirstDayOfMonth()->subDays(1)->format('Y/m/d'),
            ],
        ];
    }

    protected function activePreset(string $fromJ, string $toJ): string
    {
        foreach ($this->presets() as $key => $p) {
            if ($p['from'] === $fromJ && $p['to'] === $toJ) {
                return $key;
            }
        }

        return 'custom';
    }

    /**
     * آخرین سفارش‌های تعمیر برای نمایشِ سریع در داشبورد.
     *
     * @return \Illuminate\Support\Collection<int, \Modules\CRM\Models\Order>
     */
    protected function recentOrders()
    {
        try {
            return Order::realOrders()
                ->with([
                    'customer:id,first_name,mobile',
                    'device:id,name',
                    'brand:id,name',
                    'technician:id,first_name,last_name,firstname_tech',
                ])
                ->latest('created_at')
                ->limit(8)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
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

    /**
     * Get upcoming birthdays in the next 7 days
     */
    protected function getUpcomingBirthdays(): array
    {
        try {
            $today = now()->startOfDay();
            $endDate = now()->addDays(7)->endOfDay();

            // Get all staff with birth dates
            $users = User::staff()
                ->where('is_active', true)
                ->whereNotNull('birth_date')
                ->get();

            $upcomingBirthdays = [];
            $todayBirthdays = [];

            foreach ($users as $user) {
                $birthDate = $user->birth_date;

                // Create a date for this year's birthday
                $birthdayThisYear = $birthDate->copy()->year(now()->year)->startOfDay();

                // If birthday has passed this year, check next year
                if ($birthdayThisYear->lt($today)) {
                    $birthdayThisYear->addYear();
                }

                // Check if birthday is within the next 7 days
                if ($birthdayThisYear->between($today, $endDate)) {
                    $isToday = $birthdayThisYear->isSameDay($today);
                    $daysUntil = (int) $today->diffInDays($birthdayThisYear);
                    $age = $birthdayThisYear->year - $birthDate->year;

                    $birthdayData = [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'date' => $birthdayThisYear->format('Y-m-d'),
                        'jalali_date' => Jalalian::fromCarbon($birthdayThisYear)->format('j F'),
                        'days_until' => $daysUntil,
                        'age' => $age,
                        'is_today' => $isToday,
                    ];

                    if ($isToday) {
                        $todayBirthdays[] = $birthdayData;
                    } else {
                        $upcomingBirthdays[] = $birthdayData;
                    }
                }
            }

            // Sort upcoming birthdays by days until
            usort($upcomingBirthdays, fn ($a, $b) => $a['days_until'] <=> $b['days_until']);

            return [
                'today' => $todayBirthdays,
                'upcoming' => array_slice($upcomingBirthdays, 0, 5), // Only show next 5
                'total_upcoming' => count($upcomingBirthdays),
            ];
        } catch (\Throwable $e) {
            return ['today' => [], 'upcoming' => [], 'total_upcoming' => 0];
        }
    }
}
