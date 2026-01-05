<?php

namespace Modules\Product\Providers;

use Illuminate\Support\ServiceProvider;

class ProductServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'product');
    }

    public function register()
    {
        //
    }
}
