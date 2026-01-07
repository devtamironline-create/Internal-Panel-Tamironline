<?php

namespace Modules\Salary\Providers;

use Illuminate\Support\ServiceProvider;

class SalaryServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Salary';
    protected string $moduleNameLower = 'salary';

    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        $this->loadViewsFrom(module_path($this->moduleName, 'Resources/views'), $this->moduleNameLower);
        $this->loadRoutes();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        //
    }

    protected function loadRoutes(): void
    {
        $routePath = module_path($this->moduleName, 'Routes/web.php');
        if (file_exists($routePath)) {
            $this->loadRoutesFrom($routePath);
        }
    }

    public function provides(): array
    {
        return [];
    }
}
