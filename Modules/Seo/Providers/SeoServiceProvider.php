<?php

namespace Modules\Seo\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Seo\Console\Commands\CrawlCommand;
use Modules\Seo\Console\Commands\PingCommand;
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

        if ($this->app->runningInConsole()) {
            $this->commands([CrawlCommand::class, PingCommand::class]);
        }

        // کرال زمان‌بندی‌شدهٔ روزانه (در صورت تنظیم‌بودن scheduler سیستم).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('seo:crawl --source=scheduled')
                ->dailyAt('03:00')
                ->withoutOverlapping();
        });
    }
}
