<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * مسدودسازیِ دسترسی به بخش‌هایی که از ساختارِ پنل بازنشسته شده‌اند
 * (کارتابل پرسنلی، OKR، انبار) و بخشِ موقتاً غیرفعال (حقوق و دستمزد).
 *
 * حذف از نوارِ کناری فقط «نمایش» را می‌بندد؛ این middleware «دسترسیِ مستقیم»
 * به URLها را هم می‌بندد (۴۰۴). لیستِ الگوها در config('panel_sections') است
 * تا بازگرداندنِ هر بخش با یک تغییرِ ساده ممکن باشد.
 */
class BlockRetiredSections
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($routeName) {
            foreach ((array) config('panel_sections.disabled_route_patterns', []) as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    abort(404);
                }
            }
        }

        return $next($request);
    }
}
