<?php

namespace Modules\Technician\Providers;

use Illuminate\Support\ServiceProvider;

class TechnicianServiceProvider extends ServiceProvider
{
    protected string $name = 'Technician';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'Database/Migrations'));
        $this->loadViewsFrom(module_path($this->name, 'Resources/views'), 'technician');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Technician\Console\Commands\FixDocuments::class,
                \Modules\Technician\Console\Commands\RelinkOrphanDocuments::class,
            ]);
        }
    }
}
