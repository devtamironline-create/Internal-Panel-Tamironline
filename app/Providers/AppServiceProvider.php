<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
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
    }
}
