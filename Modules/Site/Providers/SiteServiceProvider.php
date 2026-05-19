<?php

namespace Modules\Site\Providers;

use Illuminate\Support\ServiceProvider;

class SiteServiceProvider extends ServiceProvider
{
    protected string $name = 'Site';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->name, 'Database/Migrations'));
        $this->loadViewsFrom(module_path($this->name, 'Resources/views'), 'site');

        $this->mergeConfigFrom(
            module_path($this->name, 'Config/page-sections.php'),
            'site.page-sections'
        );
    }
}
