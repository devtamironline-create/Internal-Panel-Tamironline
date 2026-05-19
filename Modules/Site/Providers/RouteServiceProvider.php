<?php

namespace Modules\Site\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Site';

    public function boot(): void
    {
        $this->map();
    }

    public function map(): void
    {
        Route::middleware('web')->group(module_path($this->name, 'Routes/web.php'));
    }
}
