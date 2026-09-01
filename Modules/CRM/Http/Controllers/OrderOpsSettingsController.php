<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;

/**
 * تنظیماتِ عملیاتیِ سفارش — مواردی که ادمین به‌راحتی مدیریت می‌کند:
 *   • لیستِ دلایلِ کنسل/رد سفارش (order_cancel_reasons)
 */
class OrderOpsSettingsController extends Controller
{
    public function index(): View
    {
        return view('crm::order-settings.index', [
            'cancelReasons' => Order::cancelReasons(),
            'defaultReasons' => Order::CANCEL_REASONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $reasons = collect($request->input('cancel_reasons', []))
            ->map(fn ($r) => trim((string) $r))
            ->filter(fn ($r) => $r !== '')
            ->map(fn ($r) => mb_substr($r, 0, 200))
            ->unique()
            ->values()
            ->all();

        if ($reasons === []) {
            return back()->with('error', 'حداقل یک دلیلِ کنسل/رد لازم است.');
        }

        CrmSetting::setJson('order_cancel_reasons', $reasons);

        return back()->with('success', 'دلایلِ کنسل/رد سفارش ذخیره شد.');
    }
}
