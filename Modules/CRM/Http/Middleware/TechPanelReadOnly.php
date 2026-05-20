<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\CRM\Models\CrmSetting;

/**
 * وقتی setting `tech_panel_readonly = '1'` فعال باشد، همه درخواست‌های
 * write (POST/PUT/PATCH/DELETE) روی پنل تکنسین برگردانده می‌شوند با
 * پیام «پنل در حال سینک است — موقتاً فقط-خواندنی است».
 *
 * GET ها (مشاهده) همچنان کار می‌کنند تا تکنسین بتواند سفارش‌هایش را
 * ببیند ولی نمی‌تواند تغییری ایجاد کند.
 *
 * استثناها: logout (تا بتوانند خارج شوند) و training routes (تا گیت
 * آموزش بسته نشود).
 */
class TechPanelReadOnly
{
    private const ALLOWED_ROUTES_DURING_FREEZE = [
        'tech.logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        // فقط write requests را بلاک می‌کنیم
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        if (CrmSetting::get('tech_panel_readonly') !== '1') {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, self::ALLOWED_ROUTES_DURING_FREEZE, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'پنل در حال به‌روزرسانی است — موقتاً فقط-خواندنی است. لطفاً چند دقیقه دیگر مراجعه کنید.',
            ], 423);
        }

        return back()->with('error', '⏳ پنل در حال به‌روزرسانی است — موقتاً نمی‌توان تغییری ایجاد کرد. چند دقیقه دیگر مراجعه کنید.');
    }
}
