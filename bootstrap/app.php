<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // اعتماد به همه proxyها — لازم برای کارکرد ساب‌دامین تکنسین در
        // حالت ریورس‌پروکسی (هاست دوم → panel.tamironline.com). بدون این،
        // request->host() همیشه panel.tamironline.com می‌شد و قانون
        // app.tech_subdomain فعال نمی‌شد.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // محدود کردن ساب‌دامین تکنسین به فقط مسیرهای /tech و فایل‌های
        // استاتیک — هر چیز دیگر redirect به صفحه ورود تکنسین می‌شود.
        $middleware->web(append: [
            \App\Http\Middleware\TechSubdomainScope::class,
            // مسدودسازیِ بخش‌های بازنشسته/غیرفعالِ پنل (کارتابل پرسنلی، OKR،
            // انبار، حقوق و دستمزد) حتی در برابرِ دسترسیِ مستقیم به URL.
            \App\Http\Middleware\BlockRetiredSections::class,
        ]);

        // وقتی guest:tech middleware روی روت /tech فایر می‌شود و کاربر از
        // قبل با guard tech لاگین است، Laravel به‌طور پیش‌فرض به / می‌فرستد —
        // و / هم چون فقط guard web را چک می‌کند، تکنسین را به admin login
        // پرت می‌کند. با redirectUsersTo مقصد را بر اساس guard فعال انتخاب
        // می‌کنیم تا تکنسینِ لاگین مستقیم به داشبورد تکنسین برود.
        $middleware->redirectUsersTo(function (Request $request) {
            if (Auth::guard('tech')->check()) {
                return route('tech.dashboard');
            }
            if (Auth::check()) {
                return route('admin.dashboard');
            }
            return '/';
        });

        $middleware->alias([
            'verified.mobile' => \App\Http\Middleware\EnsureMobileIsVerified::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'internal.token' => \App\Http\Middleware\VerifyInternalToken::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'join-technician/register/biometric-callback',
            // callback درگاه پرداخت (ملت با POST و بدون توکن CSRF برمی‌گردد)
            'crm/payment/callback',
            'public/crm/payment/callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // پاسخ‌های روت‌های /v1/* همیشه JSON باشد و در پروداکشن stack-trace
        // به فرانت برنگردد.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $e) => $request->is('v1/*'));

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('v1/*')) {
                return null;
            }
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null; // Laravel خودش 422 با ساختار درست برمی‌گرداند
            }
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return response()->json(
                    ['message' => $e->getMessage() ?: 'Error'],
                    $e->getStatusCode()
                );
            }
            return response()->json([
                'message' => app()->isProduction() ? 'Server error' : $e->getMessage(),
            ], 500);
        });
    })->create();
