<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

class DashboardController extends Controller
{
    /**
     * داشبوردِ اولِ پنل — «وضعیت کلیِ خدمات تعمیرات».
     *
     * تمرکزِ داشبورد روی سفارش‌های تعمیر (CRM) است: باز/امروز/این‌ماه،
     * سفارش‌های معطل، تکنسین‌های فعال و مشتری‌ها. کارتابلِ پرسنلی/انبار/OKR از
     * داشبورد حذف شده‌اند. آماره‌ها در try/catch‌اند تا صفحهٔ اولِ پنل هیچ‌وقت
     * به‌خاطرِ یک خطای دیتا ۵۰۰ نشود.
     */
    public function admin()
    {
        $user = auth()->user();

        $stats = [
            'repair' => $this->repairStats(),
            'birthdays' => $this->getUpcomingBirthdays(),
        ];

        $recentOrders = $this->recentOrders();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'user'));
    }

    /**
     * آماره‌های خدمات تعمیرات. در صورتِ هر خطا آرایهٔ صفر برمی‌گرداند.
     *
     * @return array<string, int>
     */
    protected function repairStats(): array
    {
        try {
            $openStatuses = [
                OrderStatus::New->value,
                OrderStatus::Coordinated->value,
                OrderStatus::Open->value,
                OrderStatus::Suspended->value,
            ];
            $cancelledStatuses = [OrderStatus::Cancelled->value, OrderStatus::Declined->value];

            $todayStart = now()->startOfDay();
            $monthStart = now()->startOfMonth();
            $threeDaysAgo = now()->subDays(3);

            return [
                'open' => Order::realOrders()->whereIn('status', $openStatuses)->count(),
                'today_new' => Order::realOrders()->where('created_at', '>=', $todayStart)->count(),
                'today_completed' => Order::realOrders()
                    ->where('status', OrderStatus::Completed->value)
                    ->where('completed_at', '>=', $todayStart)->count(),
                'month_total' => Order::realOrders()->where('created_at', '>=', $monthStart)->count(),
                'month_completed' => Order::realOrders()
                    ->where('created_at', '>=', $monthStart)
                    ->where('status', OrderStatus::Completed->value)->count(),
                'month_cancelled' => Order::realOrders()
                    ->where('created_at', '>=', $monthStart)
                    ->whereIn('status', $cancelledStatuses)->count(),
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
            return [
                'open' => 0, 'today_new' => 0, 'today_completed' => 0,
                'month_total' => 0, 'month_completed' => 0, 'month_cancelled' => 0,
                'delayed_open' => 0, 'techs_active' => 0, 'customers_total' => 0,
            ];
        }
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
                    'jalali_date' => \Morilog\Jalali\Jalalian::fromCarbon($birthdayThisYear)->format('j F'),
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
