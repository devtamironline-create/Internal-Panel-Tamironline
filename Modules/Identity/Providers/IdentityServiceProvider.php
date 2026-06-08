<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    protected string $name = 'Identity';

    public function register(): void {}

    public function boot(): void
    {
        // مهم: api middleware group لازم است تا ValidationException به‌جای
        // redirect به 422 JSON تبدیل شود و throttle stateless کار کند.
        Route::middleware('api')->group(__DIR__.'/../Routes/api.php');
    }
}
