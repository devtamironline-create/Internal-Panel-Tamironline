<?php

namespace Modules\CRM\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\CRM\Http\Middleware\VerifyWpSyncToken;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'CRM';

    public function boot(): void
    {
        $this->map();
    }

    public function map(): void
    {
        Route::middleware('web')->group(module_path($this->name, 'Routes/web.php'));

        Route::middleware(['api', VerifyWpSyncToken::class])
            ->prefix('api/crm/sync')
            ->group(module_path($this->name, 'Routes/api.php'));
    }
}
