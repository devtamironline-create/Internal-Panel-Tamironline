<?php

namespace Modules\Customer\Providers;

use Illuminate\Support\ServiceProvider;

class CustomerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'customer');
    }

    public function register()
    {
        //
    }
}
