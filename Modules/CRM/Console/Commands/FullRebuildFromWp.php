<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * Nuclear rebuild: همه wallet_txs و invoices تکنسین‌ها را پاک می‌کند
 * و از WP CRM فاکتورها + شارژها/پاداش‌ها/جریمه‌ها را بازنویسی می‌کند.
 *
 * مراحل برای هر تکنسین:
 *   ۱) Backup فعلی به storage/app/crm/full-rebuild-{timestamp}.jsonl (per-tech line)
 *   ۲) حذف wallet_txs تکنسین
 *   ۳) حذف invoices تکنسین (HARD DELETE — نه superseded)
 *   ۴) ایمپورت تمیز از WP:
 *      - wallet=1 → WalletCharge
 *      - reward_type=1 → Reward
 *      - reward_type=0 → Penalty
 *      - order_id > 0 → Invoice (با درصد WP)
 *
 * نمونه:
 *   php artisan crm:full-rebuild-from-wp                    # dry-run شمارش
 *   php artisan crm:full-rebuild-from-wp --tech=42          # یک تکنسین
 *   php artisan crm:full-rebuild-from-wp --apply --recompute
 */
class FullRebuildFromWp extends Command
{
    protected $signature = 'crm:full-rebuild-from-wp
                            {--tech= : فقط یک تکنسین}
                            {--apply : اعمال (پیش‌فرض dry-run)}
                            {--recompute : بعد، wallet:recompute-balances اجرا شود}';

    protected $description = 'پاکسازی کامل wallet+invoices و بازسازی از WP CRM';

    public function handle(): int
    {
        $this->configureWpConnection();
        try {
            $wp = DB::connection('wp_crm');
            $wp->getPdo();
        } catch (\Throwable $e) {
            $this->error('اتصال به WP DB ناموفق: ' . $e->getMessage());
            return self::FAILURE;
        }
        $prefix = env('WP_DB_PREFIX', 'or_');
        $apply = (bool) $this->option('apply');

        $query = Technician::query()->whereNotNull('wp_id');
        if ($techId = $this->option('tech')) {
            $query->where('id', (int) $techId);
        }
        $techs = $query->get(['id', 'wp_id', 'firstname_tech', 'first_name', 'last_name', 'wallet_balance']);

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — تکنسین‌ها: {$techs->count()}");
        $this->newLine();

        // Backup file (stream)
        $backupFp = null;
        $backupPath = null;
        if ($apply) {
            \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('crm');
            $backupPath = storage_path('app/crm/full-rebuild-' . now()->format('Y-m-d_His') . '.jsonl');
            $backupFp = fopen($backupPath, 'w');
        }

        app()->instance('crm.suppress_outbound_push', true);

        $totalWalletImported = 0;
        $totalInvoicesImported = 0;
        $totalNoOrder = 0;
        $perTechStats = [];

        $bar = $this->output->createProgressBar($techs->count());
        $bar->start();

        foreach ($techs as $tech) {
            // ── backup
            if ($apply && $backupFp) {
                $bk = [
                    'tech_id' => $tech->id,
                    'wp_id' => $tech->wp_id,
                    'wallet_txs' => DB::table('crm_tech_wallet_transactions')->where('technician_id', $tech->id)->get()->toArray(),
                    'invoices' => DB::table('crm_invoices')->where('technician_id', $tech->id)->get()->toArray(),
                ];
                fwrite($backupFp, json_encode($bk, JSON_UNESCAPED_UNICODE) . "\n");
                unset($bk);
            }

            // ── wipe
            if ($apply) {
                DB::table('crm_tech_wallet_transactions')->where('technician_id', $tech->id)->delete();
                DB::table('crm_invoices')->where('technician_id', $tech->id)->delete();
            }

            // ── import from WP
            $stats = $this->importTechData($wp, $prefix, $tech, $apply);
            $totalWalletImported += $stats['wallet'];
            $totalInvoicesImported += $stats['invoices'];
            $totalNoOrder += $stats['no_order'];

            if ($stats['wallet'] > 0 || $stats['invoices'] > 0) {
                $name = mb_substr($tech->firstname_tech ?: trim($tech->first_name . ' ' . $tech->last_name), 0, 22);
                $perTechStats[] = [
                    $tech->id,
                    $tech->wp_id,
                    $name,
                    $stats['wallet'],
                    $stats['invoices'],
                    $stats['no_order'],
                ];
            }

            $bar->advance();
        }

        if ($backupFp) fclose($backupFp);

        $bar->finish();
        $this->newLine(2);

        if (! empty($perTechStats) && count($perTechStats) <= 50) {
            $this->table(['tech_id', 'wp_id', 'نام', 'wallet#', 'invoice#', 'order ندارد'], $perTechStats);
        } else {
            $this->line('بیش از ۵۰ تکنسین تغییر کردند — جدول کامل نشان داده نمی‌شود.');
        }

        $this->newLine();
        $this->info("تکنسین‌ها: {$techs->count()}");
        $this->info("کل wallet eventهای ایمپورت‌شده: {$totalWalletImported}");
        $this->info("کل invoiceهای ایمپورت‌شده: {$totalInvoicesImported}");
        if ($totalNoOrder > 0) $this->warn("invoice با سفارش گم‌شده در Panel: {$totalNoOrder}");

        if ($apply) {
            $this->info("✓ Backup در: {$backupPath}");
            if ($this->option('recompute')) {
                $this->info('در حال recompute...');
                Artisan::call('crm:wallet:recompute-balances');
                $this->info('✓ تمام.');
            } else {
                $this->warn('گام بعدی: php artisan crm:wallet:recompute-balances');
            }
        } else {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:full-rebuild-from-wp --apply --recompute');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{wallet:int, invoices:int, no_order:int}
     */
    private function importTechData($wp, string $prefix, Technician $tech, bool $apply): array
    {
        // درصد WP
        $userMeta = $wp->table($prefix.'usermeta')
            ->where('user_id', $tech->wp_id)
            ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
            ->pluck('meta_value', 'meta_key')->toArray();
        $isInternal = ($userMeta['type_of_calc_tech'] ?? '') === '1';
        $percent = $isInternal
            ? (int) ($userMeta['tech_per_of_all'] ?? 0)
            : (int) ($userMeta['percent'] ?? 0);
        $calcType = $isInternal ? '1' : '';

        // financial posts
        $ids1 = $wp->table($prefix.'postmeta')
            ->whereIn('meta_key', ['technician_id', 'technician', 'tech_id'])
            ->where('meta_value', (string) $tech->wp_id)
            ->pluck('post_id')->all();
        $ids2 = $wp->table($prefix.'posts')
            ->where('post_type', 'financial')
            ->where('post_author', $tech->wp_id)
            ->pluck('ID')->all();
        $allIds = array_unique(array_merge((array) $ids1, (array) $ids2));
        if (empty($allIds)) {
            return ['wallet' => 0, 'invoices' => 0, 'no_order' => 0];
        }

        $posts = $wp->table($prefix.'posts')
            ->whereIn('ID', $allIds)
            ->where('post_type', 'financial')
            ->whereIn('post_status', ['publish', 'private', 'draft', 'pending'])
            ->select('ID', 'post_date')
            ->orderBy('ID')
            ->get()->keyBy('ID');

        if ($posts->isEmpty()) {
            return ['wallet' => 0, 'invoices' => 0, 'no_order' => 0];
        }

        $metas = $wp->table($prefix.'postmeta')
            ->whereIn('post_id', $posts->keys()->all())
            ->get();
        $metaByPost = [];
        foreach ($metas as $m) {
            $metaByPost[(int) $m->post_id][$m->meta_key] = $m->meta_value;
        }

        $walletCount = 0;
        $invoiceCount = 0;
        $noOrder = 0;

        foreach ($posts as $pid => $post) {
            $meta = $metaByPost[(int) $pid] ?? [];
            $paymentStatus = (int) ($meta['payment_status'] ?? 0);
            $wallet = (int) ($meta['wallet'] ?? 0);
            $rewardType = $meta['reward_type'] ?? null;
            $orderWpId = (int) ($meta['order_id'] ?? 0);

            // ── ۱) شارژ wallet ─────────────────────────────────
            if ($wallet === 1) {
                $amt = (int) ($meta['wallet_pay'] ?? 0);
                if ($amt === 0) continue;
                if ($apply) {
                    DB::table('crm_tech_wallet_transactions')->insert([
                        'wp_id' => (int) $pid,
                        'technician_id' => $tech->id,
                        'order_id' => null,
                        'invoice_id' => null,
                        'type' => WalletTxType::WalletCharge->value,
                        'amount' => $amt,
                        'balance_after' => 0,
                        'note' => mb_substr((string) ($meta['rewardDesc'] ?? $meta['description'] ?? 'شارژ از WP'), 0, 500),
                        'created_by' => null,
                        'created_at' => $this->safeDate($post->post_date) ?? now(),
                        'updated_at' => now(),
                    ]);
                }
                $walletCount++;
                continue;
            }

            // ── ۲) پاداش/جریمه ────────────────────────────────
            if ($rewardType !== null && $rewardType !== '') {
                $amt = (int) ($meta['total_invoice'] ?? 0);
                if ($amt === 0) continue;
                $type = ((int) $rewardType === 1) ? WalletTxType::Reward : WalletTxType::Penalty;
                $signed = ((int) $rewardType === 1) ? abs($amt) : -1 * abs($amt);
                if ($apply) {
                    DB::table('crm_tech_wallet_transactions')->insert([
                        'wp_id' => (int) $pid,
                        'technician_id' => $tech->id,
                        'order_id' => null,
                        'invoice_id' => null,
                        'type' => $type->value,
                        'amount' => $signed,
                        'balance_after' => 0,
                        'note' => mb_substr((string) ($meta['rewardDesc'] ?? $meta['description'] ?? ''), 0, 500),
                        'created_by' => null,
                        'created_at' => $this->safeDate($post->post_date) ?? now(),
                        'updated_at' => now(),
                    ]);
                }
                $walletCount++;
                continue;
            }

            // ── ۳) فاکتور (order_id > 0) ──────────────────────
            if ($orderWpId > 0) {
                $order = Order::where('wp_id', $orderWpId)->first();
                if (! $order) {
                    $noOrder++;
                    continue;
                }

                $pc = (int) ($meta['price_customer'] ?? 0);
                $cp = (int) ($meta['cost_price'] ?? 0);
                $ti = (int) ($meta['total_invoice'] ?? 0);
                if ($ti === 0 && $pc > 0) $ti = max(0, $pc - $cp);
                if ($ti === 0) continue;

                $companyShare = intdiv($ti * max(0, min(100, $percent)), 100);
                $techShare = max(0, $ti - $companyShare);
                $isPaid = $paymentStatus === 1;
                $issuedAt = $this->safeDate($post->post_date);

                if ($apply) {
                    DB::table('crm_invoices')->insert([
                        'wp_id' => (int) $pid,
                        'invoice_code' => Invoice::generateInvoiceCode(),
                        'order_id' => $order->id,
                        'customer_id' => $order->customer_id,
                        'technician_id' => $tech->id,
                        'total_amount' => $ti,
                        'tech_share' => $techShare,
                        'company_share' => $companyShare,
                        'commission_percent' => $percent,
                        'calc_type' => $calcType ?: null,
                        'status' => $isPaid ? 'paid' : 'issued',
                        'issued_at' => $issuedAt,
                        'paid_at' => $isPaid ? $issuedAt : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $invoiceCount++;
            }
        }

        return ['wallet' => $walletCount, 'invoices' => $invoiceCount, 'no_order' => $noOrder];
    }

    private function safeDate(?string $v): ?string
    {
        if (! $v || str_starts_with($v, '0000-')) return null;
        try { return \Carbon\Carbon::parse($v)->toDateTimeString(); }
        catch (\Throwable) { return null; }
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
}
