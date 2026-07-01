<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Morilog\Jalali\Jalalian;

class DashboardController extends Controller
{
    /**
     * داشبوردِ اولِ پنل — «وضعیت کلیِ خدمات تعمیرات» به‌همراهِ نمودارها.
     *
     * همهٔ بازه‌های زمانی بر پایهٔ تقویمِ شمسی (Jalali) محاسبه می‌شوند:
     * «امروز» = روزِ جاری، «این ماه» = ماهِ شمسیِ جاری (نه میلادی). آماره‌ها
     * در try/catch‌اند تا صفحهٔ اولِ پنل هیچ‌وقت ۵۰۰ نشود.
     */
    public function admin()
    {
        $user = auth()->user();

        // ─── مرزهای زمانیِ شمسی ───────────────────────────────────
        $todayStart = now()->startOfDay();                          // روزِ جاری (تهران)
        $jMonthStart = Jalalian::now()->getFirstDayOfMonth()        // اولِ ماهِ شمسی
            ->toCarbon()->startOfDay();

        $stats = [
            'repair' => $this->repairStats($todayStart, $jMonthStart),
            'birthdays' => $this->getUpcomingBirthdays(),
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentOrders' => $this->recentOrders(),
            'hourly' => $this->hourlyToday($todayStart),
            'trend' => $this->trend14(),
            'statusBreakdown' => $this->statusBreakdownMonth($jMonthStart),
            'topDevices' => $this->topDevicesMonth($jMonthStart),
            'jMonthLabel' => Jalalian::now()->format('F'),
            'user' => $user,
        ]);
    }

    /**
     * آماره‌های کلیدیِ خدمات تعمیرات (تیله‌ها). بازهٔ ماه = ماهِ شمسیِ جاری.
     *
     * @return array<string, int|float>
     */
    protected function repairStats(\Carbon\Carbon $todayStart, \Carbon\Carbon $monthStart): array
    {
        try {
            $open = [
                OrderStatus::New->value,
                OrderStatus::Coordinated->value,
                OrderStatus::Open->value,
                OrderStatus::Suspended->value,
            ];
            $cancelled = [OrderStatus::Cancelled->value, OrderStatus::Declined->value];
            $threeDaysAgo = now()->subDays(3);

            $monthTotal = Order::realOrders()->where('created_at', '>=', $monthStart)->count();
            $monthCompleted = Order::realOrders()->where('created_at', '>=', $monthStart)
                ->where('status', OrderStatus::Completed->value)->count();

            return [
                'open' => Order::realOrders()->whereIn('status', $open)->count(),
                'today_new' => Order::realOrders()->where('created_at', '>=', $todayStart)->count(),
                'today_completed' => Order::realOrders()
                    ->where('status', OrderStatus::Completed->value)
                    ->where('completed_at', '>=', $todayStart)->count(),
                'month_total' => $monthTotal,
                'month_completed' => $monthCompleted,
                'month_cancelled' => Order::realOrders()->where('created_at', '>=', $monthStart)
                    ->whereIn('status', $cancelled)->count(),
                'completion_rate' => $monthTotal > 0 ? round($monthCompleted / $monthTotal * 100) : 0,
                'delayed_open' => Order::realOrders()
                    ->whereIn('status', [
                        OrderStatus::Coordinated->value,
                        OrderStatus::Open->value,
                        OrderStatus::Suspended->value,
                    ])
                    ->whereNotNull('technician_id')
                    ->whereNotNull('assigned_at')
                    ->where('assigned_at', '<', $threeDaysAgo)
                    ->count(),
                'techs_active' => Technician::where('status', 'active')->count(),
                'customers_total' => Customer::count(),
            ];
        } catch (\Throwable $e) {
            return array_fill_keys([
                'open', 'today_new', 'today_completed', 'month_total', 'month_completed',
                'month_cancelled', 'completion_rate', 'delayed_open', 'techs_active', 'customers_total',
            ], 0);
        }
    }

    /**
     * سفارش‌های امروز به تفکیکِ ساعت (۰ تا ۲۳) — ثبت‌شده و تکمیل‌شده.
     *
     * @return array{labels:array<int,string>, created:array<int,int>, completed:array<int,int>}
     */
    protected function hourlyToday(\Carbon\Carbon $todayStart): array
    {
        $labels = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02d', $h);
        }
        $created = array_fill(0, 24, 0);
        $completed = array_fill(0, 24, 0);

        try {
            Order::realOrders()->where('created_at', '>=', $todayStart)
                ->get(['created_at'])
                ->each(function ($o) use (&$created) {
                    if ($o->created_at) {
                        $created[(int) $o->created_at->format('G')]++;
                    }
                });

            Order::realOrders()->where('status', OrderStatus::Completed->value)
                ->where('completed_at', '>=', $todayStart)
                ->get(['completed_at'])
                ->each(function ($o) use (&$completed) {
                    if ($o->completed_at) {
                        $completed[(int) $o->completed_at->format('G')]++;
                    }
                });
        } catch (\Throwable $e) {
            // آرایه‌های صفر
        }

        return ['labels' => $labels, 'created' => array_values($created), 'completed' => array_values($completed)];
    }

    /**
     * روندِ ۱۴ روزِ اخیر (روزانه، برچسبِ شمسی): کل/تکمیل/لغو.
     *
     * @return array{labels:array<int,string>, totals:array<int,int>, completed:array<int,int>, cancelled:array<int,int>}
     */
    protected function trend14(): array
    {
        $labels = [];
        $totals = [];
        $completed = [];
        $cancelled = [];
        $cancelledStatuses = [OrderStatus::Cancelled->value, OrderStatus::Declined->value];

        try {
            for ($i = 13; $i >= 0; $i--) {
                $start = now()->subDays($i)->startOfDay();
                $end = now()->subDays($i)->endOfDay();
                $labels[] = Jalalian::fromCarbon($start)->format('m/d');
                $totals[] = Order::realOrders()->whereBetween('created_at', [$start, $end])->count();
                $completed[] = Order::realOrders()->whereBetween('created_at', [$start, $end])
                    ->where('status', OrderStatus::Completed->value)->count();
                $cancelled[] = Order::realOrders()->whereBetween('created_at', [$start, $end])
                    ->whereIn('status', $cancelledStatuses)->count();
            }
        } catch (\Throwable $e) {
            $labels = $totals = $completed = $cancelled = [];
        }

        return compact('labels', 'totals', 'completed', 'cancelled');
    }

    /**
     * توزیعِ وضعیتِ سفارش‌های ماهِ شمسیِ جاری (برای نمودارِ donut).
     *
     * @return array{labels:array<int,string>, data:array<int,int>, colors:array<int,string>}
     */
    protected function statusBreakdownMonth(\Carbon\Carbon $monthStart): array
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
            $rows = Order::realOrders()->where('created_at', '>=', $monthStart)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->get();

            foreach ($rows as $row) {
                $case = OrderStatus::tryFrom((string) $row->status);
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
     * پرتکرارترین دستگاه‌ها در ماهِ شمسیِ جاری (برای نمودارِ میله‌ای).
     *
     * @return array{labels:array<int,string>, data:array<int,int>}
     */
    protected function topDevicesMonth(\Carbon\Carbon $monthStart): array
    {
        $labels = [];
        $data = [];

        try {
            $rows = Order::realOrders()->where('created_at', '>=', $monthStart)
                ->whereNotNull('device_id')
                ->with('device:id,name')
                ->selectRaw('device_id, COUNT(*) as cnt')
                ->groupBy('device_id')
                ->orderByDesc('cnt')
                ->limit(7)
                ->get();

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

    /**
     * Get upcoming birthdays in the next 7 days
     */
    protected function getUpcomingBirthdays(): array
    {
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
    }
}
