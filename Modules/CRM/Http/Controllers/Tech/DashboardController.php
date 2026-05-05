<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;

/**
 * کنترلر داشبورد + صفحات اصلی پنل تکنسین.
 *
 * فاز ۳: سفارش‌ها از placeholder خارج شد. کیف‌پول/فاکتور/پروفایل
 * هنوز placeholder هستند و در فازهای بعدی فعال می‌شوند.
 */
class DashboardController extends Controller
{
    public function index()
    {
        return view('crm::tech-panel.dashboard', [
            'technician' => Auth::guard('tech')->user(),
        ]);
    }

    public function orders(Request $request)
    {
        $tech = Auth::guard('tech')->user();

        $statusFilter = $request->query('status');
        $search = $request->query('q');

        $base = Order::query()->forTechnician($tech->id);

        // آمار وضعیت‌های مهم برای تکنسین (همیشه روی کل سفارش‌های تکنسین).
        $stats = [
            'total' => (clone $base)->count(),
            'coordinated' => (clone $base)->ofStatus(OrderStatus::Coordinated)->count(),
            'open' => (clone $base)->ofStatus(OrderStatus::Open)->count(),
            'completed' => (clone $base)->ofStatus(OrderStatus::Completed)->count(),
        ];

        $query = (clone $base)
            ->with(['customer', 'brand', 'device'])
            ->search($search);

        if ($statusFilter && OrderStatus::tryFrom($statusFilter)) {
            $query->ofStatus($statusFilter);
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        return view('crm::tech-panel.orders', [
            'technician' => $tech,
            'orders' => $orders,
            'stats' => $stats,
            'statusFilter' => $statusFilter,
            'search' => $search,
        ]);
    }

    public function wallet()
    {
        return view('crm::tech-panel._partials.placeholder', [
            'pageTitle' => 'کیف‌پول',
            'pageDescription' => 'تراکنش‌ها، شارژ، و مانده در فاز ۵ اضافه می‌شود.',
            'currentNav' => 'tech.wallet',
        ]);
    }

    public function invoices()
    {
        return view('crm::tech-panel._partials.placeholder', [
            'pageTitle' => 'فاکتورها',
            'pageDescription' => 'لیست فاکتورهای تکنسین در فاز ۶ اضافه می‌شود.',
            'currentNav' => 'tech.invoices',
        ]);
    }

    public function profile()
    {
        return view('crm::tech-panel._partials.placeholder', [
            'pageTitle' => 'پروفایل',
            'pageDescription' => 'مشاهده و ویرایش پروفایل + تنظیم رمز عبور در فاز ۷ اضافه می‌شود.',
            'currentNav' => 'tech.profile',
        ]);
    }
}
