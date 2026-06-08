<?php

namespace Modules\Site\Providers;

use Illuminate\Support\Facades\Validator;
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

        // Observers — auto-invalidate cache بنر
        \Modules\Site\Models\Banner::observe(\Modules\Site\Observers\BannerObserver::class);

        $this->mergeConfigFrom(
            module_path($this->name, 'Config/page-sections.php'),
            'site.page-sections'
        );

        $this->mergeConfigFrom(
            module_path($this->name, 'Config/activity-areas.php'),
            'site.activity-areas'
        );

        $this->registerValidators();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Site\Console\Commands\ImportArticlesFromWp::class,
            ]);
        }
    }

    /**
     * Validator اختصاصی site_url — برای فیلدهای لینک که در فرانت Next.js
     * می‌توانند هم مسیر داخلی (/order) و هم URL کامل (https://...) باشند.
     */
    private function registerValidators(): void
    {
        Validator::extend('site_url', function ($attribute, $value, $parameters, $validator) {
            if ($value === null || $value === '') {
                return true;
            }
            if (! is_string($value)) {
                return false;
            }
            $value = trim($value);
            if ($value === '') {
                return true;
            }
            // مسیر داخلی Next.js
            if (str_starts_with($value, '/')) {
                // اجازه نمی‌دهیم double-slash که می‌تواند protocol-relative باشد
                return ! str_starts_with($value, '//');
            }
            // پروتکل‌های ارتباطی مجاز
            if (str_starts_with($value, 'mailto:') || str_starts_with($value, 'tel:')) {
                return true;
            }

            // URL کامل
            return filter_var($value, FILTER_VALIDATE_URL) !== false
                && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
        });

        Validator::replacer('site_url', function ($message, $attribute, $rule, $parameters) {
            return trans('validation.site_url', ['attribute' => $attribute], 'fa') !== 'validation.site_url'
                ? trans('validation.site_url', ['attribute' => $attribute], 'fa')
                : "فیلد {$attribute} باید یک نشانی معتبر یا مسیر داخلی (مانند /order) باشد.";
        });
    }
}
