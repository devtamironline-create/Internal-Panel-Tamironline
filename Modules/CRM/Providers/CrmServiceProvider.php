<?php

namespace Modules\CRM\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\CRM\Models\CrmSetting;
use Livewire\Livewire;
use Modules\CRM\Console\Commands\BackfillInvoices;
use Modules\CRM\Console\Commands\RecomputeInvoices;
use Modules\CRM\Console\Commands\RecomputeWalletBalances;
use Modules\CRM\Console\Commands\ActivateTechniciansByName;
use Modules\CRM\Console\Commands\FindWalletDuplicates;
use Modules\CRM\Console\Commands\FixInvoicesFromWp;
use Modules\CRM\Console\Commands\ImportInvoicesFromWp;
use Modules\CRM\Console\Commands\ImportTechnicianFromWp;
use Modules\CRM\Console\Commands\PullTechPercentFromWp;
use Modules\CRM\Console\Commands\RebuildTechWallet;
use Modules\CRM\Console\Commands\ReimportAllWalletFromWp;
use Modules\CRM\Console\Commands\RemoveManualAdjustments;
use Modules\CRM\Console\Commands\ResetWalletFromWp;
use Modules\CRM\Console\Commands\RestoreDeletedAdjustments;
use Modules\CRM\Console\Commands\RestoreWalletFromSnapshot;
use Modules\CRM\Console\Commands\UndoRestoreAdjustments;
use Modules\CRM\Console\Commands\SupersedeAllInvoices;
use Modules\CRM\Console\Commands\UnsupersedeInvoices;
use Modules\CRM\Console\Commands\VerifyInvoiceCalc;
use Modules\CRM\Console\Commands\ResolveOrphanTechnicians;
use Modules\CRM\Console\Commands\ResyncInvoices;
use Modules\CRM\Console\Commands\ResyncOrderStatusesFromWp;
use Modules\CRM\Console\Commands\ResyncTechnicians;
use Modules\CRM\Console\Commands\ResyncWalletTransactions;
use Modules\CRM\Console\Commands\RestoreTechPercentFromHistory;
use Modules\CRM\Console\Commands\SetTechPercent;
use Modules\CRM\Console\Commands\ShowWpTechSettings;
use Modules\CRM\Console\Commands\SnapshotTechnicians;
use Modules\CRM\Console\Commands\WalletAudit;
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

        // متغیرهای branding پنل تکنسین به همهٔ ویوهای tech-panel.* پاس
        // داده می‌شوند. تعریف در parent layout @php کافی نیست چون scope
        // آن به child sections (@section) منتقل نمی‌شود.
        View::composer('crm::tech-panel.*', function ($view) {
            $view->with([
                'brandLogo'          => Setting::get('tech_panel_logo'),
                'brandBanner'        => Setting::get('tech_panel_banner'),
                'brandHero'          => Setting::get('tech_panel_hero'),
                'brandDefaultAvatar' => Setting::get('tech_panel_default_avatar'),
                'appName'            => Setting::get('tech_panel_name', 'تعمیرآنلاین'),
                'supportPhone'       => Setting::get('tech_panel_support_phone'),
                'isFrozen'           => CrmSetting::get('tech_panel_readonly') === '1',
            ]);
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillInvoices::class,
                RecomputeInvoices::class,
                RecomputeWalletBalances::class,
                ResolveOrphanTechnicians::class,
                ResyncTechnicians::class,
                ResyncInvoices::class,
                ResyncOrderStatusesFromWp::class,
                ResyncWalletTransactions::class,
                ActivateTechniciansByName::class,
                SnapshotTechnicians::class,
                ImportTechnicianFromWp::class,
                RebuildTechWallet::class,
                PullTechPercentFromWp::class,
                WalletAudit::class,
                FixInvoicesFromWp::class,
                FindWalletDuplicates::class,
                ShowWpTechSettings::class,
                SetTechPercent::class,
                RestoreTechPercentFromHistory::class,
                RemoveManualAdjustments::class,
                ResetWalletFromWp::class,
                RestoreDeletedAdjustments::class,
                UndoRestoreAdjustments::class,
                ReimportAllWalletFromWp::class,
                UnsupersedeInvoices::class,
                VerifyInvoiceCalc::class,
                ImportInvoicesFromWp::class,
                RestoreWalletFromSnapshot::class,
                SupersedeAllInvoices::class,
            ]);
        }
    }
}
