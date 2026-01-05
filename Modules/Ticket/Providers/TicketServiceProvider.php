<?php

namespace Modules\Ticket\Providers;

use Illuminate\Support\ServiceProvider;

class TicketServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'ticket');
    }
}
