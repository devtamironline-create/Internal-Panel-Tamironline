<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Training gate برای پنل تکنسین — تا وقتی تکنسین همه ویدیوهای فعال
 * را ندیده، فقط می‌تواند به مسیرهای آموزش، logout و profile (برای
 * تغییر رمز) دسترسی داشته باشد. سایر مسیرها به /tech/training
 * redirect می‌شوند.
 *
 * تکنسین‌های موجود قبل از این تغییر، training_completed_at = NOW()
 * دریافت کرده‌اند (در migration). فقط تکنسین‌های جدید قفل می‌شوند.
 */
class RequireTrainingCompleted
{
    /**
     * مسیرهایی که حتی پیش از تکمیل آموزش هم در دسترس‌اند.
     */
    private const ALLOWED_ROUTE_NAMES = [
        'tech.training',
        'tech.training.uncategorized',
        'tech.training.category',
        'tech.training.show',
        'tech.training.video-watched',
        'tech.logout',
        'tech.profile',
        'tech.profile.update',
        'tech.profile.password',
        'tech.profile.avatar',
    ];

    public function handle(Request $request, Closure $next)
    {
        $tech = Auth::guard('tech')->user();
        if (! $tech) {
            return $next($request);
        }

        if ($tech->isTrainingCompleted()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        // اگر JSON می‌خواهد، 403 برگردان (برای fetch/XHR)
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'برای دسترسی به این بخش، ابتدا تمام ویدیوهای آموزشی را مشاهده کنید.',
                'redirect' => route('tech.training'),
            ], 403);
        }

        return redirect()->route('tech.training')
            ->with('warning', 'برای فعال‌سازی پنل، تمام ویدیوهای آموزشی را مشاهده و دکمهٔ «دیدم» را بزنید.');
    }
}
