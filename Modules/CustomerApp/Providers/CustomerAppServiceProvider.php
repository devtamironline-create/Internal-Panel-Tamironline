<?php

namespace Modules\CustomerApp\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider ماژول CustomerApp.
 *
 * این ماژول API اختصاصی اپلیکیشن مشتریان (PWA/موبایل) را روی namespace
 *   /v1/customer/*
 * سرو می‌کند. مستقل از Site (که Next.js مصرف می‌کند) و Identity (که فقط
 * احراز هویت OTP می‌دهد) است.
 *
 * Auth subject: Modules\CRM\Models\Customer (همان Sanctum token از Identity).
 */
class CustomerAppServiceProvider extends ServiceProvider
{
    protected string $name = 'CustomerApp';

    public function register(): void {}

    public function boot(): void
    {
        // Route::middleware('api') لازم است تا exceptionها JSON رندر شوند
        Route::middleware('api')->group(__DIR__.'/../Routes/api.php');

        // روت‌های ادمین — پنل «مدیریت اپلیکیشن (مشتریان)»
        Route::middleware('web')->group(__DIR__.'/../Routes/web.php');

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'customerapp');

        $this->mergeConfigFrom(
            __DIR__.'/../config/cancel-reasons.php',
            'customerapp.cancel-reasons'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/time-slots.php',
            'customerapp.time-slots'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/test-mode.php',
            'customerapp.test-mode'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/reviews.php',
            'customerapp.reviews'
        );

        // Observer: روی Order events گوش می‌دهد و notification به مشتری
        // می‌فرستد (در جدول notifications لاراول). جدا از WP push observer
        // در مدل Order تا منطق هر کانال مستقل بماند.
        \Modules\CRM\Models\Order::observe(\Modules\CustomerApp\Observers\CustomerOrderObserver::class);
    }
}
