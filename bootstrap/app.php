<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        ]);

        $middleware->alias([
            'verified.mobile' => \App\Http\Middleware\EnsureMobileIsVerified::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'join-technician/register/biometric-callback',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
