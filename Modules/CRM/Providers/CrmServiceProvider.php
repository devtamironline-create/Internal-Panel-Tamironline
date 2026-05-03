<?php

namespace Modules\CRM\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\CRM\Console\Commands\RecomputeWalletBalances;
use Modules\CRM\Livewire\OrderWizard;

class CrmServiceProvider extends ServiceProvider
{
    protected string $name = 'CRM';

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'crm');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'crm');

        Livewire::component('crm.order-wizard', OrderWizard::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RecomputeWalletBalances::class,
            ]);
        }
    }
}
