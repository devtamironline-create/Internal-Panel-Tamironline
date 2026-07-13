<?php

namespace Modules\CRM\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\CRM\Http\Middleware\EnforceSyncMode;
use Modules\CRM\Http\Middleware\LogWpSyncInbound;
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

        Route::middleware(['api', VerifyWpSyncToken::class, EnforceSyncMode::class, LogWpSyncInbound::class])
            ->prefix('api/crm/sync')
            ->group(module_path($this->name, 'Routes/api.php'));

        // API اپِ تکنسین (توکنِ Sanctum) — مسیرها زیرِ /v1/technician، مثلِ اپِ مشتری.
        Route::middleware('api')
            ->group(module_path($this->name, 'Routes/tech_api.php'));
    }
}
