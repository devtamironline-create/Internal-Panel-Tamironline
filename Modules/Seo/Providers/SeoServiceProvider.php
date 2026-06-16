<?php

namespace Modules\Seo\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Seo\Livewire\SeoMetaPanel;

class SeoServiceProvider extends ServiceProvider
{
    protected string $name = 'Seo';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'seo');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'seo');

        // پنل متای سئو که داخل فرم‌های ویرایشِ موجود embed می‌شود.
        Livewire::component('seo.meta-panel', SeoMetaPanel::class);
    }
}
