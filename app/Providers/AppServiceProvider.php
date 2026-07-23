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
            if (self::isTrustedFrontend($request)) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($request->ip());
        });

        // ─── Rate limiter «seo-read» برای endpointهای فقط‌خواندنیِ سئو ───
        // (meta/settings/redirects). فرانتِ ISR هنگامِ بازتولیدِ انبوهِ صفحات
        // (بعد از دیپلوی/انقضای کش) از یک IP صدها بار /v1/seo/meta را می‌زند؛
        // با سقفِ per-IPِ معمولی 429 می‌گرفت و متایِ «پیش‌فرض» در نسخهٔ
        // استاتیکِ صفحه بیک می‌شد. سرورِ فرانت (توکنِ BFF یا IPِ معتمد) معاف
        // است؛ بقیه همان سقفِ قبلی را دارند.
        RateLimiter::for('seo-read', function (Request $request) {
            if (self::isTrustedFrontend($request)) {
                return Limit::none();
            }

            return Limit::perMinute(120)->by($request->ip());
        });

        // ─── نوشتنی‌های عمومیِ پشتِ BFF — کلید بر اساسِ کاربر یا IPِ واقعی ───
        // request->ip() برای همهٔ کاربرانِ سایت یکی است (IPِ سرورِ فرانت)؛ throttleِ
        // per-IP یعنی سقفِ مشترک برای کلِ سایت. کلید: کاربرِ واردشده → user id؛
        // درخواستِ BFF → IPِ واقعی از X-Forwarded-For؛ وگرنه IP.
        RateLimiter::for('forum-report', function (Request $request) {
            $user = $request->user() ?? $request->user('sanctum');

            return $user
                ? Limit::perMinute(5)->by('report:u'.$user->getAuthIdentifier())
                : Limit::perMinute(5)->by('report:ip'.\App\Support\ClientIp::resolve($request));
        });

        RateLimiter::for('public-write', function (Request $request) {
            $user = $request->user() ?? $request->user('sanctum');

            return $user
                ? Limit::perMinute(30)->by('pw:u'.$user->getAuthIdentifier())
                : Limit::perMinute(30)->by('pw:ip'.\App\Support\ClientIp::resolve($request));
        });

        // ─── Rate limiter های OTP — بر اساسِ «شمارهٔ موبایل» نه IP ───────
        // این مسیرها عمومی‌اند و از BFF (سرور Next.js) پراکسی می‌شوند؛ پس IPِ
        // همهٔ کاربران یکسان است (کانتینر فرانت) و throttleِ per-IP خیلی زود پر
        // می‌شد و «Too Many Attempts.» می‌داد. کلیدِ per-mobile مستقل از IP است
        // و هم اسپمِ یک شماره را می‌گیرد هم مدلِ BFF را نمی‌شکند. شماره نرمال‌سازی
        // می‌شود تا تغییرِ فرمت (۰۹.../+۹۸۹...) دورزدنِ محدودیت نباشد.
        RateLimiter::for('otp-send', function (Request $request) {
            $mobile = self::otpMobileKey($request);

            return $mobile !== null
                ? Limit::perMinute(5)->by('otp-send:'.$mobile)
                : Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $mobile = self::otpMobileKey($request);

            // برای امکانِ چند بار تلاشِ کدِ اشتباه، سقفِ بالاتر.
            return $mobile !== null
                ? Limit::perMinute(15)->by('otp-verify:'.$mobile)
                : Limit::perMinute(30)->by($request->ip());
        });
    }

    /**
     * کلیدِ نرمال‌شدهٔ موبایل برای throttle (یا null اگر شماره‌ای نبود).
     */
    private static function otpMobileKey(Request $request): ?string
    {
        $raw = (string) $request->input('mobile');

        return \Modules\Identity\Support\PhoneNormalizer::normalize($raw)
            ?? (preg_replace('/\D/', '', $raw) ?: null);
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

    /**
     * سرورِ فرانتِ معتمد: یا توکنِ BFF دارد، یا IPاش در فهرستِ
     * services.internal.trusted_ips است (fetchهای ISR/generateMetadata که
     * بدونِ توکن مستقیم به بک‌اند می‌زنند).
     *
     * نکتهٔ امنیتی: چون trustProxies(at:'*') فعال است، request->ip() با هدرِ
     * X-Forwarded-For قابلِ جعل است؛ برای این معافیت REMOTE_ADDR (طرفِ واقعیِ
     * TCP — غیرقابلِ جعل) مقایسه می‌شود.
     */
    private static function isTrustedFrontend(Request $request): bool
    {
        if (self::isInternalBff($request)) {
            return true;
        }

        $trusted = array_filter(array_map('trim', explode(',', (string) config('services.internal.trusted_ips'))));

        return $trusted !== [] && in_array((string) $request->server('REMOTE_ADDR', ''), $trusted, true);
    }
}
