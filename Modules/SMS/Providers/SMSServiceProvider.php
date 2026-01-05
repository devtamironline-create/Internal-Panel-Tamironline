<?php

namespace Modules\SMS\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\SMS\Services\KavenegarService;
use Modules\SMS\Services\OTPService;

class SMSServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KavenegarService::class, function ($app) {
            return new KavenegarService();
        });
        
        $this->app->singleton(OTPService::class, function ($app) {
            return new OTPService($app->make(KavenegarService::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
