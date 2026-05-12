<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * در زمانی که app.tech_subdomain ست شده باشد و درخواست از همان میزبان
 * بیاید، این میدلور هر مسیری به‌جز /tech و فایل‌های ضروری PWA/استاتیک
 * را به صفحه ورود/داشبورد تکنسین می‌فرستد. هدف: ساب‌دامین مخصوص تکنسین
 * (مثل karbalad.tamironline.com) فقط پنل تکنسین را نشان دهد و هرگز
 * مسیرهای /admin یا /api ادمین را افشا نکند.
 *
 * وقتی پشت ریورس‌پروکسی اجرا می‌شویم، تشخیص میزبان به Trusted Proxies
 * + X-Forwarded-Host وابسته است (که در bootstrap/app.php تنظیم شده).
 */
class TechSubdomainScope
{
    /** مسیرهایی که حتی روی ساب‌دامین تکنسین آزاد هستند. */
    private const ALLOWED_PREFIXES = [
        'tech',          // همه مسیرهای پنل تکنسین
        'tech-panel/',   // سرو تصاویر برند (لوگو/بنر/Hero) از طریق PHP
        'crm/ticket-image/', // سرو تصاویر تیکت پشتیبانی
        'file/',         // Storage proxy عمومی برای فایل‌های public disk
        'storage/',      // فایل‌های آپلود شده (لوگو/بنر/...)
        'css/',
        'js/',
        'fonts/',
        'vendor/',       // tailwind/alpine از /vendor/js
        'tech-pwa/',     // آیکون‌های PWA
        'livewire/',     // Livewire endpoint (در صورت استفاده در آینده)
        '_debugbar/',
        'build/',        // Vite assets
        'up',            // health-check Laravel 11
    ];

    private const ALLOWED_EXACT = [
        'manifest.json',
        'sw.js',
        'favicon.ico',
        'robots.txt',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $techHost = config('app.tech_subdomain');

        if (! $techHost || $request->host() !== $techHost) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        // root → redirect to tech home
        if ($path === '' || $path === '/') {
            return $this->redirectHome();
        }

        if (in_array($path, self::ALLOWED_EXACT, true)) {
            return $next($request);
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        return $this->redirectHome();
    }

    private function redirectHome(): Response
    {
        return Auth::guard('tech')->check()
            ? redirect()->route('tech.dashboard')
            : redirect()->route('tech.login');
    }
}
