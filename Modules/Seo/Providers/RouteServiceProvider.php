<?php

namespace Modules\Seo\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Seo';

    public function boot(): void
    {
        $this->map();
    }

    public function map(): void
    {
        Route::middleware('web')->group(__DIR__.'/../Routes/web.php');

        Route::middleware('api')->group(__DIR__.'/../Routes/api.php');
    }
}
