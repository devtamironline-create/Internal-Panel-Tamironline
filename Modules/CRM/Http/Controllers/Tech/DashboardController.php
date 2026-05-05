<?php

namespace Modules\CRM\Http\Controllers\Tech;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Placeholder داشبورد پنل تکنسین — در فاز بعدی با ظاهر کامل و
 * منطق سفارش‌ها/کیف‌پول/فاکتور جایگزین می‌شود. الان فقط برای
 * اطمینان از کارکرد auth استفاده می‌شود.
 */
class DashboardController extends Controller
{
    public function index()
    {
        return view('crm::tech-panel.dashboard', [
            'technician' => Auth::guard('tech')->user(),
        ]);
    }
}
