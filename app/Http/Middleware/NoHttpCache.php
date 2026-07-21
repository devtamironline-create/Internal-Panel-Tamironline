<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * جلوگیریِ کامل از کشِ HTTP برای endpointهای بلادرنگ (چت/اعلان/poll).
 *
 * ریشهٔ باگِ «پیام فقط بعد از پاک‌کردنِ کش می‌آید»: پاسخ‌های GETِ JSON بدونِ
 * هدرِ Cache-Control توسطِ کشِ مرورگر و کشِ سرور/پروکسی (LiteSpeed روی هاستِ
 * cPanel) ذخیره می‌شدند و poll همان پاسخِ کهنه را می‌گرفت. این middleware
 * هدرهای ضدِ‌کش را برای هر سه لایه ست می‌کند:
 *   - مرورگر/پروکسیِ استاندارد: Cache-Control + Pragma + Expires
 *   - LiteSpeed:               X-LiteSpeed-Cache-Control
 *   - nginx proxy_cache:       X-Accel-Expires
 *
 * (همان درمانی که قبلاً به‌صورت دستی روی Admin/ChatController::recentUnread
 * اعمال شده بود — این‌جا به‌صورتِ ریشه‌ای و قابلِ‌استفادهٔ مجدد.)
 */
class NoHttpCache
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
        $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Expires', '0');

        return $response;
    }
}
