<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super-admin bypass: admin role has access to everything
        Gate::before(function ($user, $ability) {
            // استثنا: حسابداری (هزینه‌ها) از bypass سوپر-ادمین خارج است —
            // دسترسی باید صریحاً (per-user از مدیریت دسترسی‌ها) داده شود،
            // حتی برای نقش admin. null یعنی بررسی عادی permission ادامه یابد.
            if (in_array($ability, ['view-crm-costs', 'manage-crm-costs'], true)) {
                return null;
            }
            if ($user->hasRole('admin')) {
                return true;
            }
        });

        // Force HTTPS in production
        if ($this->app->environment('production') || isset($_SERVER['HTTPS'])) {
            URL::forceScheme('https');
        }

        // Set locale to Persian
        app()->setLocale('fa');

        // Add Modules views
        $this->loadViewsFrom(base_path('Modules/Core/Resources/views'), 'core');
        $this->loadViewsFrom(base_path('Modules/Staff/Resources/views'), 'staff');
        $this->loadViewsFrom(base_path('Modules/SMS/Resources/views'), 'sms');

        // ─── Blade directives برای تاریخ شمسی ───────────────────────
        // @jdate($order->created_at)         → 1404/02/12
        // @jdatetime($order->created_at)     → 1404/02/12 15:43
        // @jdatefull($order->created_at)     → 1404/02/12 15:43:21
        Blade::directive('jdate', function ($expr) {
            return "<?php echo (\$__d = $expr) ? \\Morilog\\Jalali\\Jalalian::fromCarbon(\\Illuminate\\Support\\Carbon::parse(\$__d))->format('Y/m/d') : '—'; ?>";
        });
        Blade::directive('jdatetime', function ($expr) {
            return "<?php echo (\$__d = $expr) ? \\Morilog\\Jalali\\Jalalian::fromCarbon(\\Illuminate\\Support\\Carbon::parse(\$__d))->format('Y/m/d H:i') : '—'; ?>";
        });
        Blade::directive('jdatefull', function ($expr) {
            return "<?php echo (\$__d = $expr) ? \\Morilog\\Jalali\\Jalalian::fromCarbon(\\Illuminate\\Support\\Carbon::parse(\$__d))->format('Y/m/d H:i:s') : '—'; ?>";
        });

        // Click-to-Call: شماره را به <a href="tel:..."> با آیکون تلفن تبدیل می‌کند.
        // ارقام فارسی → لاتین، چندشماره با خط‌تیره/کاما به صورت جداگانه لینک می‌شوند.
        // Usage: @tel($customer->mobile)
        Blade::directive('tel', function ($expr) {
            return "<?php echo \\App\\Helpers\\TelHelper::render($expr); ?>";
        });

        // ─── Rate limiter «catalog» برای روت‌های خواندنیِ کاتالوگ/عمومی ───
        // درخواست‌های BFF (سرور-به-سرور با INTERNAL_API_TOKEN) از throttleِ
        // مبتنی بر IP معاف‌اند؛ چون همه از یک IP (کانتینر فرانت) می‌آیند و
        // در غیر این صورت سقفِ مشترک خیلی زود پر می‌شود و 429 می‌دهد.
        // کاربرانِ عادیِ مرورگر (بدون توکن) همچنان IP-based محدود می‌مانند.
        RateLimiter::for('catalog', function (Request $request) {
            if (self::isInternalBff($request)) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->ip());
        });
    }

    /**
     * آیا این درخواست از BFFِ داخلی با توکنِ معتبرِ سرور-به-سرور است؟
     * هم‌منطق با App\Http\Middleware\VerifyInternalToken (شامل توکن قدیمی).
     */
    private static function isInternalBff(Request $request): bool
    {
        $provided = (string) $request->bearerToken();
        if ($provided === '') {
            return false;
        }

        foreach ([config('services.internal.token'), config('services.internal.token_old')] as $expected) {
            $expected = (string) $expected;
            if ($expected !== '' && hash_equals($expected, $provided)) {
                return true;
            }
        }

        return false;
    }
}
