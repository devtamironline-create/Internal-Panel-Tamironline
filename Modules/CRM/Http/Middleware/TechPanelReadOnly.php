<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\CRM\Models\CrmSetting;

/**
 * وقتی setting `tech_panel_readonly = '1'` فعال باشد، فقط عملیات
 * زیر روی پنل تکنسین بلاک می‌شوند:
 *
 *   ۱) شارژ کیف‌پول (tech.wallet.recharge.initiate)
 *   ۲) تغییر وضعیت سفارش به یک وضعیت نهایی (Cancelled/Completed/
 *      Transit/Declined) — یعنی «بستن» یا «نهایی‌سازی» سفارش
 *
 * بقیه عملیات (یاداشت، تنظیم زمان مراجعه، آپدیت پروفایل، تغییر
 * وضعیت غیرنهایی) همچنان مجاز است تا تکنسین بتواند کار روزانه را
 * ادامه دهد.
 */
class TechPanelReadOnly
{
    /** مسیرهایی که در حالت freeze کاملاً بلاک می‌شوند. */
    private const BLOCKED_DURING_FREEZE = [
        'tech.wallet.recharge.initiate',
    ];

    /** وضعیت‌های نهایی سفارش — تغییر به این‌ها در freeze بلاک است. */
    private const FINAL_ORDER_STATUSES = [
        'cancelled', 'completed', 'transit', 'declined',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (CrmSetting::get('tech_panel_readonly') !== '1') {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        // ۱) مسیرهای صراحتاً بلاک‌شده (شارژ کیف‌پول)
        if ($routeName && in_array($routeName, self::BLOCKED_DURING_FREEZE, true)) {
            return $this->reject($request, 'شارژ کیف‌پول');
        }

        // ۲) تغییر وضعیت سفارش به وضعیت نهایی
        if ($routeName === 'tech.orders.update-status' && $request->isMethod('POST')) {
            $newStatus = strtolower((string) $request->input('status'));
            if (in_array($newStatus, self::FINAL_ORDER_STATUSES, true)) {
                return $this->reject($request, 'نهایی‌سازی سفارش');
            }
        }

        return $next($request);
    }

    private function reject(Request $request, string $operation)
    {
        $msg = '⏳ پنل در حال به‌روزرسانی است — ' . $operation . ' موقتاً غیرفعال است. لطفاً چند دقیقه دیگر مراجعه کنید.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $msg], 423);
        }
        return back()->with('error', $msg);
    }
}
