<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

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
    }
}
