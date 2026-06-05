<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * Import فاکتورهای WP (financial posts با order_id > 0) که در Panel
 * نیستند. برای موقعی که فاکتورهای زیادی در WP وجود دارد ولی sync قبلی
 * فقط برخی را آورده.
 *
 * هر فاکتور با درصد WP محاسبه می‌شود:
 *   company_share = total_invoice × percent / 100
 *   tech_share    = total_invoice − company_share
 *
 * نمونه:
 *   php artisan crm:import-invoices-from-wp                  # dry-run
 *   php artisan crm:import-invoices-from-wp --tech=42        # یک تکنسین
 *   php artisan crm:import-invoices-from-wp --apply --recompute
 */
class ImportInvoicesFromWp extends Command
{
    protected $signature = 'crm:import-invoices-from-wp
                            {--tech= : فقط یک تکنسین (id Panel)}
                            {--apply : درج واقعی (پیش‌فرض dry-run)}
                            {--recompute : بعد، wallet:recompute-balances اجرا شود}';

    protected $description = 'Import فاکتورهای WP که در Panel نیستند';

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

        $techsQuery = Technician::query()->whereNotNull('wp_id');
        if ($techId = $this->option('tech')) {
            $techsQuery->where('id', (int) $techId);
        }
        $techs = $techsQuery->get(['id', 'wp_id', 'firstname_tech', 'first_name', 'last_name', 'percent', 'tech_per_of_all', 'type_of_calc_tech']);

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — تکنسین‌ها: {$techs->count()}");
        $this->newLine();

        app()->instance('crm.suppress_outbound_push', true);

        $totalImported = 0;
        $totalSkipped = 0;
        $totalNoOrder = 0;
        $perTechStats = [];

        $bar = $this->output->createProgressBar($techs->count());
        $bar->start();

        foreach ($techs as $tech) {
            $stats = $this->importTechInvoices($wp, $prefix, $tech, $apply);
            $totalImported += $stats['imported'];
            $totalSkipped += $stats['skipped'];
            $totalNoOrder += $stats['no_order'];

            if ($stats['imported'] > 0 || $stats['no_order'] > 0) {
                $name = mb_substr($tech->firstname_tech ?: trim($tech->first_name . ' ' . $tech->last_name), 0, 25);
                $perTechStats[] = [
                    $tech->id,
                    $tech->wp_id,
                    $name,
                    $stats['imported'],
                    $stats['skipped'],
                    $stats['no_order'],
                    number_format($stats['company_share_sum']),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (! empty($perTechStats)) {
            $this->table(
                ['tech_id', 'wp_id', 'نام', 'import', 'تکراری', 'order ندارد', 'جمع company_share'],
                $perTechStats
            );
        }

        $this->newLine();
        $this->info("کل ایمپورت: {$totalImported} فاکتور");
        if ($totalSkipped > 0) $this->line("قبلاً در Panel بود: {$totalSkipped}");
        if ($totalNoOrder > 0) $this->warn("سفارش متناظر در Panel نیست: {$totalNoOrder}");

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:import-invoices-from-wp --apply --recompute');
        } else {
            if ($this->option('recompute')) {
                $this->info('در حال recompute...');
                Artisan::call('crm:wallet:recompute-balances');
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{imported:int, skipped:int, no_order:int, company_share_sum:int}
     */
    private function importTechInvoices($wp, string $prefix, Technician $tech, bool $apply): array
    {
        // درصد موثر WP
        $userMeta = $wp->table($prefix.'usermeta')
            ->where('user_id', $tech->wp_id)
            ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
            ->pluck('meta_value', 'meta_key')->toArray();
        $isInternal = ($userMeta['type_of_calc_tech'] ?? '') === '1';
        $percent = $isInternal
            ? (int) ($userMeta['tech_per_of_all'] ?? 0)
            : (int) ($userMeta['percent'] ?? 0);
        $calcType = $isInternal ? '1' : '';

        // financial postهای این تکنسین
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
            return ['imported' => 0, 'skipped' => 0, 'no_order' => 0, 'company_share_sum' => 0];
        }

        $posts = $wp->table($prefix.'posts')
            ->whereIn('ID', $allIds)
            ->where('post_type', 'financial')
            ->whereIn('post_status', ['publish', 'private', 'draft', 'pending'])
            ->select('ID', 'post_date')
            ->get()->keyBy('ID');
        if ($posts->isEmpty()) {
            return ['imported' => 0, 'skipped' => 0, 'no_order' => 0, 'company_share_sum' => 0];
        }

        $metas = $wp->table($prefix.'postmeta')
            ->whereIn('post_id', $posts->keys()->all())
            ->get();
        $metaByPost = [];
        foreach ($metas as $m) {
            $metaByPost[(int) $m->post_id][$m->meta_key] = $m->meta_value;
        }

        $imported = 0;
        $skipped = 0;
        $noOrder = 0;
        $companyShareSum = 0;

        foreach ($posts as $pid => $post) {
            $meta = $metaByPost[(int) $pid] ?? [];
            $orderWpId = (int) ($meta['order_id'] ?? 0);
            $wallet = (int) ($meta['wallet'] ?? 0);
            $rewardType = $meta['reward_type'] ?? null;

            // فقط invoiceها (order_id > 0 و بدون wallet/reward)
            if ($orderWpId === 0) continue;
            if ($wallet === 1) continue;
            if ($rewardType !== null && $rewardType !== '') continue;

            // قبلاً موجود است؟
            if (Invoice::withoutGlobalScope('active')->where('wp_id', (int) $pid)->exists()) {
                $skipped++;
                continue;
            }

            // سفارش لاراولی
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

            $isPaid = ((int) ($meta['payment_status'] ?? 0)) === 1;
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

            $imported++;
            $companyShareSum += $companyShare;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'no_order' => $noOrder,
            'company_share_sum' => $companyShareSum,
        ];
    }

    private function safeDate(?string $v): ?string
    {
        if (! $v || str_starts_with($v, '0000-')) return null;
        try {
            return \Carbon\Carbon::parse($v)->toDateTimeString();
        } catch (\Throwable) {
            return null;
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
}
