<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * کنترلر داشبورد + صفحات اصلی پنل تکنسین.
 *
 * در فاز ۲ همهٔ زیرصفحه‌ها (سفارش‌ها/کیف‌پول/فاکتور/پروفایل) هنوز
 * placeholder هستند تا layout موبایلی + bottom nav قابل تست باشد.
 * منطق واقعی هر صفحه در فازهای ۳ تا ۷ از TechDashboardController قدیمی
 * مهاجرت می‌کند.
 */
class DashboardController extends Controller
{
    public function index()
    {
        return view('crm::tech-panel.dashboard', [
            'technician' => Auth::guard('tech')->user(),
        ]);
    }

    public function orders()
    {
        return view('crm::tech-panel._partials.placeholder', [
            'pageTitle' => 'سفارش‌ها',
            'pageDescription' => 'لیست سفارش‌های تخصیص داده شده در فاز ۳ اضافه می‌شود.',
            'currentNav' => 'tech.orders',
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
