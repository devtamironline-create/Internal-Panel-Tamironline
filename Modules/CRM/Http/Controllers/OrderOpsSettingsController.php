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
 *   • دلایلِ «کنسلِ» سفارش توسطِ ادمین (order_cancel_reasons) — سفارش مرده.
 *   • دلایلِ «ردِ» سفارش توسطِ تکنسین در اپ (technician_decline_reasons) —
 *     هر علت می‌تواند «بازگشت به تخصیص خودکار» داشته باشد.
 */
class OrderOpsSettingsController extends Controller
{
    public function index(): View
    {
        return view('crm::order-settings.index', [
            'cancelReasons' => Order::cancelReasons(),
            'defaultReasons' => Order::CANCEL_REASONS,
            'declineReasons' => Order::declineReasons(),
            'defaultDeclineReasons' => Order::DECLINE_REASONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // هر بخش مستقل پردازش می‌شود؛ فقط بخشی که در فرم آمده به‌روزرسانی
        // می‌شود (تا ارسالِ یک بخش، بخشِ دیگر را پاک نکند).

        // ─── دلایلِ کنسلِ ادمین ───
        if ($request->has('cancel_reasons')) {
            $reasons = collect($request->input('cancel_reasons', []))
                ->map(fn ($r) => trim((string) $r))
                ->filter(fn ($r) => $r !== '')
                ->map(fn ($r) => mb_substr($r, 0, 200))
                ->unique()
                ->values()
                ->all();

            if ($reasons === []) {
                return back()->with('error', 'حداقل یک دلیلِ کنسل لازم است.');
            }

            CrmSetting::setJson('order_cancel_reasons', $reasons);
        }

        // ─── دلایلِ ردِ تکنسین (label + reopen) ───
        if ($request->has('decline_reasons')) {
            $seen = [];
            $decline = [];
            foreach ((array) $request->input('decline_reasons', []) as $row) {
                $label = mb_substr(trim((string) ($row['label'] ?? '')), 0, 200);
                if ($label === '' || isset($seen[$label])) {
                    continue;
                }
                $seen[$label] = true;
                $decline[] = [
                    'label' => $label,
                    'reopen' => filter_var($row['reopen'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            }

            if ($decline === []) {
                return back()->with('error', 'حداقل یک دلیلِ ردِ تکنسین لازم است.');
            }

            CrmSetting::setJson('technician_decline_reasons', $decline);
        }

        return back()->with('success', 'تنظیماتِ دلایلِ سفارش ذخیره شد.');
    }
}
