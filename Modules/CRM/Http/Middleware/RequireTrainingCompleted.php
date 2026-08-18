<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Training gate تکنسین — تا وقتی همهٔ ویدیوهای فعال دیده نشده‌اند، تکنسین
 * فقط به آموزش، پروفایل (پشتیبانی/خروج) و همگام‌سازیِ هویت دسترسی دارد.
 *
 * روی هر دو سطح سوار است:
 *   - پنل وبِ تکنسین (guard tech، سشن)     → redirect به /tech/training
 *   - API اپِ تکنسین (Sanctum، /v1/…)      → 403 با redirect + training_required
 *
 * چرا هر دو: بدونِ نسخهٔ API، تکنسینِ آموزش‌ندیده می‌توانست با اپ (که فقط
 * توکن دارد و اصلاً از مسیرهای وب رد نمی‌شود) مستقیم به سفارش/کیف‌پول
 * برسد — گیتِ سمتِ کلاینت به‌تنهایی enforcement نیست.
 *
 * تکنسین‌های موجود قبل از این تغییر، training_completed_at = NOW()
 * دریافت کرده‌اند (در migration). فقط تکنسین‌های جدید قفل می‌شوند.
 */
class RequireTrainingCompleted
{
    /**
     * مسیرهایی که حتی پیش از تکمیل آموزش هم در دسترس‌اند.
     *
     * قاعده: خودِ آموزش، هویت/خروج، و پروفایل (برای تماس با پشتیبانی و
     * تغییر رمز). هر مسیرِ «کاری» (سفارش، کیف‌پول، پیش‌فاکتور، داشبورد،
     * تیکت، چت، اعلان) عمداً بیرون است.
     */
    private const ALLOWED_ROUTE_NAMES = [
        // ── پنل وب
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

        // ── API اپ
        'api.tech.training.index',
        'api.tech.training.category',
        'api.tech.training.uncategorized',
        'api.tech.training.video',
        'api.tech.training.watched',
        'api.tech.me',
        'api.tech.sync',
        'api.tech.app-config',
        'api.tech.auth.logout',
        'api.tech.profile.password',
        'api.tech.profile.avatar',
        // ثبتِ توکنِ پوش هم بی‌ضرر است و نبودش فقط اعلان را می‌شکند.
        'api.tech.push-token.store',
        'api.tech.push-token.destroy',
    ];

    public function handle(Request $request, Closure $next)
    {
        // API با guard sanctum می‌آید و پنل وب با guard tech — هر دو به یک
        // مدلِ Technician می‌رسند.
        $tech = Auth::guard('tech')->user() ?: $request->user();
        if (! $tech || ! method_exists($tech, 'isTrainingCompleted')) {
            return $next($request);
        }

        if ($tech->isTrainingCompleted()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        // اگر JSON می‌خواهد، 403 برگردان (اپ و fetch/XHR پنل).
        // اپ مسیرِ داخلیِ خودش را می‌خواهد و پنلِ وب URL کاملِ صفحه را —
        // پس redirect بر اساسِ نوعِ درخواست ساخته می‌شود.
        if ($request->expectsJson()) {
            $isApp = $request->is('v1/*');

            return response()->json([
                'success' => false,
                'message' => 'برای دسترسی به این بخش، ابتدا تمام ویدیوهای آموزشی را مشاهده کنید.',
                'redirect' => $isApp ? '/training' : route('tech.training'),
                'training_required' => true,
            ], 403);
        }

        return redirect()->route('tech.training')
            ->with('warning', 'برای فعال‌سازی پنل، تمام ویدیوهای آموزشی را مشاهده و دکمهٔ «دیدم» را بزنید.');
    }
}
