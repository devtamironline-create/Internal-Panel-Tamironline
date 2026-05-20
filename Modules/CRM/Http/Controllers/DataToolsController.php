<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Technician;

/**
 * صفحهٔ «ابزارهای داده» — اجرای commandهای import/resync از UI بدون
 * نیاز به SSH/CLI. مسئول داده می‌تواند:
 *   - یک تکنسین خاص را از WP وارد Laravel کند
 *   - کیف‌پول یک تکنسین را بازسازی کند (Import wallet txs + recompute)
 *   - resync کلی تکنسین‌ها، فاکتورها، یا تراکنش‌های wallet
 *   - balance همه کیف‌پول‌ها را دوباره محاسبه کند
 *
 * Permission: manage-crm-sync.
 */
class DataToolsController extends Controller
{
    public function index()
    {
        $techCount = Technician::count();
        $techPanelReadonly = CrmSetting::get('tech_panel_readonly') === '1';
        return view('crm::data-tools.index', compact('techCount', 'techPanelReadonly'));
    }

    /** Import یک تکنسین از WP. */
    public function importTechFromWp(Request $request)
    {
        $request->validate([
            'wp_id' => 'required|integer|min:1',
            'update_existing' => 'nullable|boolean',
        ]);

        $params = [
            'wp_id' => (int) $request->input('wp_id'),
            '--force' => true,
        ];
        if ($request->boolean('update_existing')) {
            $params['--update-existing'] = true;
        }

        return $this->runArtisan('crm:import-tech-from-wp', $params);
    }

    /** بازسازی کیف‌پول یک تکنسین. */
    public function rebuildTechWallet(Request $request)
    {
        $request->validate([
            'tech_id' => 'required|integer|min:1|exists:crm_technicians,id',
            'dry_run' => 'nullable|boolean',
        ]);

        $params = ['tech_id' => (int) $request->input('tech_id')];
        if ($request->boolean('dry_run')) {
            $params['--dry-run'] = true;
        }

        return $this->runArtisan('crm:rebuild-tech-wallet', $params);
    }

    /** resync کلی تکنسین‌ها. */
    public function resyncTechnicians(Request $request)
    {
        $params = [];
        if ($request->boolean('laravel_only')) {
            $params['--laravel-only'] = true;
        }
        return $this->runArtisan('crm:resync-technicians', $params);
    }

    /** resync کلی فاکتورها. */
    public function resyncInvoices(Request $request)
    {
        $params = [];
        if ($request->boolean('all')) $params['--all'] = true;
        if ($request->boolean('with_superseded')) $params['--with-superseded'] = true;
        if ($id = $request->input('id')) $params['--id'] = (int) $id;
        return $this->runArtisan('crm:resync-invoices', $params);
    }

    /** resync کلی تراکنش‌های wallet. */
    public function resyncWalletTransactions(Request $request)
    {
        $params = [];
        if ($request->boolean('all'))   $params['--all'] = true;
        if ($request->boolean('force')) $params['--force'] = true;
        if ($type = $request->input('type')) $params['--type'] = $type;
        return $this->runArtisan('crm:resync-wallet-transactions', $params);
    }

    /** بازخوانی همه balanceهای کیف‌پول. */
    public function recomputeBalances(Request $request)
    {
        $params = [];
        if ($id = $request->input('technician')) {
            $params['--technician'] = (int) $id;
        }
        return $this->runArtisan('crm:wallet:recompute-balances', $params);
    }

    /** Resync وضعیت سفارش‌ها از WP به Laravel (سریع، فقط فیلد status). */
    public function resyncOrderStatuses(Request $request)
    {
        $request->validate([
            'limit'  => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:0',
            'since'  => 'nullable|date_format:Y-m-d',
            'dry_run' => 'nullable|boolean',
        ]);

        $params = [];
        if ($request->filled('limit'))  $params['--limit']  = (int) $request->input('limit');
        if ($request->filled('offset')) $params['--offset'] = (int) $request->input('offset');
        if ($request->filled('since'))  $params['--since']  = $request->input('since');
        if ($request->boolean('dry_run')) $params['--dry-run'] = true;

        return $this->runArtisan('crm:resync-order-statuses-from-wp', $params);
    }

    /** بازرسی فقط-خواندنی کیف‌پول یک تکنسین (تشخیص ناهنجاری). */
    public function walletAudit(Request $request)
    {
        $request->validate([
            'tech_id' => 'nullable|integer|min:1',
            'mobile'  => 'nullable|string|max:20',
            'show_all' => 'nullable|boolean',
        ]);

        if (! $request->filled('tech_id') && ! $request->filled('mobile')) {
            return back()->with('error', 'یکی از فیلدهای «id تکنسین» یا «موبایل» الزامی است.');
        }

        $params = [];
        if ($request->filled('tech_id')) {
            $params['tech'] = (int) $request->input('tech_id');
        }
        if ($request->filled('mobile')) {
            $params['--mobile'] = $request->input('mobile');
        }
        if ($request->boolean('show_all')) {
            $params['--show-all'] = true;
        }

        return $this->runArtisan('crm:wallet-audit', $params);
    }

    /** Toggle حالت فقط-خواندنی پنل تکنسین. */
    public function toggleTechPanelReadonly(Request $request)
    {
        $current = CrmSetting::get('tech_panel_readonly') === '1';
        $next = $current ? '0' : '1';
        CrmSetting::set('tech_panel_readonly', $next);

        $msg = $next === '1'
            ? '❄️ پنل تکنسین در حالت فقط-خواندنی قرار گرفت — تکنسین‌ها فقط می‌توانند مشاهده کنند.'
            : '✅ پنل تکنسین از حالت فقط-خواندنی خارج شد — تکنسین‌ها می‌توانند تغییر ایجاد کنند.';

        return back()->with('success', $msg);
    }

    /** فعال‌سازی گروهی تکنسین‌ها بر اساس لیست اسامی. */
    public function activateTechniciansByName(Request $request)
    {
        $params = [];
        if ($request->boolean('apply')) {
            $params['--apply'] = true;
        }
        return $this->runArtisan('crm:activate-technicians-by-name', $params);
    }

    /**
     * اجرای artisan و گرفتن خروجی برای نمایش.
     */
    private function runArtisan(string $command, array $params)
    {
        try {
            // افزایش time-limit برای commandهای طولانی
            @set_time_limit(600);

            // capture output
            $output = new \Symfony\Component\Console\Output\BufferedOutput();
            $exit = Artisan::call($command, $params, $output);
            $log = $output->fetch();

            return back()
                ->with('tool_output', $log)
                ->with('tool_command', $command . ' ' . $this->formatParams($params))
                ->with('tool_exit', $exit)
                ->with($exit === 0 ? 'success' : 'error',
                    $exit === 0
                        ? 'دستور با موفقیت اجرا شد.'
                        : 'دستور با خطا بسته شد (exit=' . $exit . ').'
                );
        } catch (\Throwable $e) {
            return back()
                ->with('tool_output', $e->getMessage() . "\n\n" . $e->getTraceAsString())
                ->with('tool_command', $command)
                ->with('tool_exit', 1)
                ->with('error', 'خطا در اجرا: ' . $e->getMessage());
        }
    }

    private function formatParams(array $params): string
    {
        $parts = [];
        foreach ($params as $k => $v) {
            if (is_bool($v) && $v) { $parts[] = $k; continue; }
            $parts[] = is_int($k) ? (string) $v : ($k . '=' . $v);
        }
        return implode(' ', $parts);
    }
}
