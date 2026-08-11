<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * دروازهٔ صندوق سرمایه — فقط فلگ users.investment_access (که تنها با
 * کامند investment:grant روشن می‌شود). عمداً از Spatie استفاده نشده تا
 * در صفحهٔ مدیریت دسترسی‌ها دیده نشود و نقش admin هم دورش نزند.
 *
 * 404 و نه 403: برای کسی که دسترسی ندارد، این بخش اصلاً «وجود ندارد».
 */
class EnsureInvestmentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) $request->user()?->investment_access, 404);

        return $next($request);
    }
}
