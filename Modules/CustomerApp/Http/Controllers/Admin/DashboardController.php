<?php

namespace Modules\CustomerApp\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;

/**
 * صفحه‌ی هاب «مدیریت اپلیکیشن (مشتریان)».
 *
 * نمایش متمرکز:
 *   - وضعیت اپ (maintenance / min_version / force_reauth)
 *   - آمار: مشتری‌های فعال، سفارش‌های اپ، نظرسنجی‌های معوق
 *   - لینک‌های سریع به بخش‌های مرتبط در CRM و Site
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('customerapp::admin.dashboard', [
            'stats' => $this->stats(),
            'appStatus' => $this->appStatus(),
        ]);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function stats(): array
    {
        // مشتری‌های با موبایل تأیید شده — یعنی از اپ/سایت لاگین کرده‌اند
        $verifiedCustomers = Customer::query()->whereNotNull('mobile_verified_at')->count();

        // مشتری‌های فعال هفته‌ی اخیر بر اساس last_login_at
        $activeWeek = Customer::query()
            ->where('last_login_at', '>=', now()->subDays(7))
            ->count();

        // توکن‌های فعلی فعال (تخمین تعداد session های زنده‌ی اپ)
        $activeTokens = PersonalAccessToken::query()
            ->where('tokenable_type', Customer::class)
            ->count();

        // سفارش‌هایی که از پنل لاراول ثبت شده‌اند (source_of_truth=panel) — معیار اپ
        $ordersFromApp = Order::query()
            ->where('source_of_truth', 'panel')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // نظرسنجی‌های معوق (سفارش completed بدون رکورد review)
        // در صورت نبود جدول هنوز، صفر برمی‌گردانیم
        $pendingReviews = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('crm_order_reviews')) {
            $pendingReviews = Order::query()
                ->where('status', \Modules\CRM\Enums\OrderStatus::Completed->value)
                ->whereDoesntHave('review')
                ->count();
        }

        return [
            'verified_customers' => $verifiedCustomers,
            'active_week' => $activeWeek,
            'active_tokens' => $activeTokens,
            'orders_from_app_30d' => $ordersFromApp,
            'pending_reviews' => $pendingReviews,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function appStatus(): array
    {
        return [
            'app_disabled' => (bool) Setting::get('app_disabled', config('app_status.app_disabled', false)),
            'maintenance_active' => (bool) Setting::get('app_maintenance_active', config('app_status.maintenance.active', false)),
            'min_version' => (string) Setting::get('app_min_version', config('app_status.min_version', '1.0.0')),
            'latest_version' => (string) Setting::get('app_latest_version', config('app_status.latest_version', '1.0.0')),
            'force_reauth_at' => (int) Setting::get('app_force_reauth_at', config('app_status.force_reauth_at', 0)),
        ];
    }
}
