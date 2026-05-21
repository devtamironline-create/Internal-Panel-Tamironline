<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
    // ─── تنظیم مانده کیف‌پول به‌صورت گروهی (لیست paste شده) ───────

    public function bulkBalanceForm()
    {
        return view('crm::data-tools.bulk-balance');
    }

    public function bulkBalanceApply(Request $request)
    {
        $request->validate([
            'list' => 'required|string|max:50000',
            'action' => 'required|in:preview,apply',
        ]);

        $action = $request->input('action');
        $parsed = $this->parseBalanceList($request->input('list'));

        $results = [];
        $applied = 0;
        $notFound = 0;
        $ambiguous = 0;
        $backup = ['timestamp' => now()->toIso8601String(), 'techs' => []];

        foreach ($parsed as $row) {
            $matches = $this->matchTechnicians($row['name']);

            $entry = [
                'input_name' => $row['name'],
                'target' => $row['amount'],
                'status' => '',
                'matched_name' => null,
                'tech_id' => null,
                'matches_count' => $matches->count(),
                'current_balance' => null,
            ];

            if ($matches->isEmpty()) {
                $entry['status'] = 'notfound';
                $notFound++;
            } elseif ($matches->count() > 1) {
                $entry['status'] = 'ambiguous';
                $ambiguous++;
            } else {
                $tech = $matches->first();
                $entry['status'] = 'ok';
                $entry['tech_id'] = $tech->id;
                $entry['matched_name'] = $tech->firstname_tech ?: trim(($tech->first_name ?? '') . ' ' . ($tech->last_name ?? ''));

                $currentBalance = (int) $tech->wallet_balance - (int) DB::table('crm_invoices')
                    ->where('technician_id', $tech->id)
                    ->whereNull('superseded_at')
                    ->sum('company_share');
                $entry['current_balance'] = $currentBalance;

                if ($action === 'apply') {
                    // backup
                    $backup['techs'][$tech->id] = [
                        'tech_id' => $tech->id,
                        'wp_id' => $tech->wp_id,
                        'name' => $entry['matched_name'],
                        'wallet_txs' => DB::table('crm_tech_wallet_transactions')
                            ->where('technician_id', $tech->id)
                            ->get()->toArray(),
                        'invoices' => DB::table('crm_invoices')
                            ->where('technician_id', $tech->id)
                            ->get()->toArray(),
                        'old_balance' => $currentBalance,
                        'new_balance' => $row['amount'],
                    ];

                    // wipe + opening
                    DB::table('crm_tech_wallet_transactions')
                        ->where('technician_id', $tech->id)
                        ->delete();

                    DB::table('crm_invoices')
                        ->where('technician_id', $tech->id)
                        ->whereNull('superseded_at')
                        ->update(['superseded_at' => now()]);

                    $type = $row['amount'] >= 0 ? 'wallet_charge' : 'adjustment';
                    DB::table('crm_tech_wallet_transactions')->insert([
                        'technician_id' => $tech->id,
                        'order_id' => null,
                        'invoice_id' => null,
                        'wp_id' => null,
                        'type' => $type,
                        'amount' => $row['amount'],
                        'balance_after' => $row['amount'],
                        'note' => 'موجودی افتتاحیه از WP CRM (تنظیم دستی توسط ادمین ' . now()->format('Y-m-d H:i') . ')',
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('crm_technicians')
                        ->where('id', $tech->id)
                        ->update(['wallet_balance' => $row['amount'], 'updated_at' => now()]);

                    $entry['applied'] = true;
                    $applied++;
                }
            }

            $results[] = $entry;
        }

        // save backup if applied
        if ($action === 'apply' && ! empty($backup['techs'])) {
            \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('crm');
            $path = 'crm/wallet-bulk-balance-' . now()->format('Y-m-d_His') . '.json';
            \Illuminate\Support\Facades\Storage::disk('local')->put(
                $path,
                json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }

        return view('crm::data-tools.bulk-balance', [
            'results' => $results,
            'action' => $action,
            'list' => $request->input('list'),
            'summary' => [
                'total' => count($parsed),
                'ok' => count($parsed) - $notFound - $ambiguous,
                'notfound' => $notFound,
                'ambiguous' => $ambiguous,
                'applied' => $applied,
            ],
        ]);
    }

    /** Parse لیست: هر خط = نام<whitespace>amount (with optional sign). */
    private function parseBalanceList(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $parsed = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (str_contains($line, 'نام') || str_contains($line, 'مانده')) continue;

            // پیدا کردن عدد امضادار از انتها
            if (! preg_match('/^(.+?)[\s\t]+(-?[0-9]+)\s*$/u', $line, $m)) continue;
            $name = trim($m[1]);
            $amount = (int) $m[2];

            if ($name === '') continue;
            $parsed[] = ['name' => $name, 'amount' => $amount];
        }

        return $parsed;
    }

    // ─── تنظیم درصد تکنسین‌ها به صورت گروهی (لیست paste شده) ──────

    /** نمایش صفحه فرم. */
    public function bulkPercentForm()
    {
        return view('crm::data-tools.bulk-percent');
    }

    /** پردازش لیست و نمایش پیش‌نمایش یا اعمال. */
    public function bulkPercentApply(Request $request)
    {
        $request->validate([
            'list' => 'required|string|max:50000',
            'calc' => 'required|in:external,internal',
            'action' => 'required|in:preview,apply',
        ]);

        $action = $request->input('action');
        $calc = $request->input('calc');
        $calcStored = $calc === 'internal' ? '1' : '';

        $parsed = $this->parseList($request->input('list'));

        // configure WP connection
        $this->configureWpConnection();
        $prefix = env('WP_DB_PREFIX', 'or_');

        try {
            $wp = DB::connection('wp_crm');
            $wp->getPdo();
            $wpAvailable = true;
        } catch (\Throwable $e) {
            $wpAvailable = false;
        }

        $results = [];
        $applied = 0;
        $notFound = 0;
        $ambiguous = 0;

        foreach ($parsed as $row) {
            $name = $row['name'];
            $percent = $row['percent'];

            // ── جستجوی تکنسین با چند روش
            $matches = $this->matchTechnicians($name);

            $status = '';
            $tech = null;

            if ($matches->isEmpty()) {
                $status = 'notfound';
                $notFound++;
            } elseif ($matches->count() > 1) {
                $status = 'ambiguous';
                $ambiguous++;
            } else {
                $tech = $matches->first();
                $status = 'ok';
            }

            $currentPercent = null;
            $currentCalc = null;
            if ($tech) {
                $currentCalc = (string) ($tech->type_of_calc_tech ?? '');
                $isInternal = $currentCalc === '1' || $currentCalc === 'internal';
                $currentPercent = $isInternal
                    ? (int) ($tech->tech_per_of_all ?? 0)
                    : (int) ($tech->percent ?? 0);
            }

            $entry = [
                'input_name' => $name,
                'input_percent' => $percent,
                'status' => $status,
                'tech_id' => $tech?->id,
                'wp_id' => $tech?->wp_id,
                'matched_name' => $tech ? ($tech->firstname_tech ?: trim(($tech->first_name ?? '') . ' ' . ($tech->last_name ?? ''))) : null,
                'mobile' => $tech?->mobile,
                'current_percent' => $currentPercent,
                'matches_count' => $matches->count(),
                'sample_matches' => $matches->count() > 1
                    ? $matches->take(5)->map(fn($t) => $t->firstname_tech ?: trim(($t->first_name ?? '') . ' ' . ($t->last_name ?? ''))) ->implode(' / ')
                    : null,
            ];

            // اعمال
            if ($action === 'apply' && $status === 'ok' && $tech) {
                $this->applyPercentToTech($tech, $percent, $calcStored, $calc, $wp, $prefix, $wpAvailable);
                $applied++;
                $entry['applied'] = true;
            }

            $results[] = $entry;
        }

        return view('crm::data-tools.bulk-percent', [
            'results' => $results,
            'action' => $action,
            'calc' => $calc,
            'list' => $request->input('list'),
            'summary' => [
                'total' => count($parsed),
                'ok' => count($parsed) - $notFound - $ambiguous,
                'notfound' => $notFound,
                'ambiguous' => $ambiguous,
                'applied' => $applied,
            ],
            'wp_available' => $wpAvailable,
        ]);
    }

    /** Parse لیست paste شده — هر خط: نام<whitespace>عدد. */
    private function parseList(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $parsed = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // اولین کلمه «نام تکنسین» و «درصد کمیسیون» هدر است — skip
            if (str_contains($line, 'درصد') || str_contains($line, 'تکنسین')) continue;

            // split از آخر — آخرین token عدد است، بقیه نام
            if (! preg_match('/^(.+?)[\s\t]+([0-9]+)\s*$/u', $line, $m)) continue;
            $name = trim($m[1]);
            $percent = (int) $m[2];

            if ($name === '' || $percent < 0 || $percent > 100) continue;

            $parsed[] = ['name' => $name, 'percent' => $percent];
        }

        return $parsed;
    }

    /** پیدا کردن تکنسین با چند روش — exact roughly normalized then LIKE. */
    private function matchTechnicians(string $name)
    {
        // نرمالایز: حذف کاراکترهای اضافی مثل zero-width و فاصله‌های متعدد
        $normalized = preg_replace('/[\s\x{200C}\x{200D}\x{200E}\x{200F}]+/u', ' ', $name);
        $normalized = trim($normalized);

        // اولویت ۱: exact match روی firstname_tech
        $exact = Technician::where('firstname_tech', $normalized)->get();
        if ($exact->isNotEmpty()) return $exact;

        // اولویت ۲: first_name + last_name exact
        $parts = explode(' ', $normalized, 2);
        if (count($parts) === 2) {
            $exact2 = Technician::where('first_name', $parts[0])
                ->where('last_name', $parts[1])
                ->get();
            if ($exact2->isNotEmpty()) return $exact2;
        }

        // اولویت ۳: LIKE %name% روی firstname_tech
        $like = Technician::where('firstname_tech', 'like', '%' . $normalized . '%')->get();
        if ($like->isNotEmpty()) return $like;

        // اولویت ۴: LIKE روی first_name + last_name
        return Technician::where(function ($q) use ($normalized) {
            $q->whereRaw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?", ['%' . $normalized . '%']);
        })->get();
    }

    /** اعمال درصد روی یک تکنسین در Panel و WP. */
    private function applyPercentToTech($tech, int $percent, string $calcStored, string $calcKey, $wp, string $prefix, bool $wpAvailable): void
    {
        $isInternal = $calcStored === '1';

        $panelUpdate = ['type_of_calc_tech' => $calcStored, 'updated_at' => now()];
        if ($isInternal) {
            $panelUpdate['tech_per_of_all'] = $percent;
        } else {
            $panelUpdate['percent'] = $percent;
        }

        DB::table('crm_technicians')
            ->where('id', $tech->id)
            ->update($panelUpdate);

        // WP فقط اگر wp_id داشته باشد و WP در دسترس باشد
        if (! $wpAvailable || ! $tech->wp_id) return;

        $wpUpdate = ['type_of_calc_tech' => $calcStored];
        if ($isInternal) {
            $wpUpdate['tech_per_of_all'] = (string) $percent;
        } else {
            $wpUpdate['percent'] = (string) $percent;
        }

        foreach ($wpUpdate as $key => $value) {
            $exists = $wp->table($prefix.'usermeta')
                ->where('user_id', $tech->wp_id)
                ->where('meta_key', $key)
                ->exists();
            if ($exists) {
                $wp->table($prefix.'usermeta')
                    ->where('user_id', $tech->wp_id)
                    ->where('meta_key', $key)
                    ->update(['meta_value' => $value]);
            } else {
                $wp->table($prefix.'usermeta')->insert([
                    'user_id' => $tech->wp_id,
                    'meta_key' => $key,
                    'meta_value' => $value,
                ]);
            }
        }
    }

    private function configureWpConnection(): void
    {
        config(['database.connections.wp_crm' => [
            'driver'    => 'mysql',
            'host'      => env('WP_DB_HOST', '127.0.0.1'),
            'port'      => (int) env('WP_DB_PORT', 3306),
            'database'  => env('WP_DB_NAME', 'crmtamironline_db_new'),
            'username'  => env('WP_DB_USER', 'crmtamironline_db_new'),
            'password'  => env('WP_DB_PASS', 'Rayanew_0935'),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);
    }

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
