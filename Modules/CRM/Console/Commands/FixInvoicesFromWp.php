<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Invoice;

/**
 * تصحیح یک‌بارهٔ فاکتورهای تاریخی Panel با مقادیر اصلی WP.
 *
 * علت: SyncFinancialController قبلاً company_share را روی price_customer
 * (مبلغ کل) محاسبه می‌کرد، در حالی که WP خودش روی total_invoice (پس از
 * کسر قطعات) محاسبه می‌کند. این command:
 *
 *   ۱) تمام فاکتورهای Panel که wp_id دارند را پیدا می‌کند
 *   ۲) از WP DB مستقیماً total_invoice و price_customer و cost_price
 *      را می‌خواند
 *   ۳) company_share و tech_share را با فرمول صحیح (روی total_invoice)
 *      بازمحاسبه و آپدیت می‌کند
 *
 * این تغییر را به WP push نمی‌کند (suppress_outbound_push فعال است).
 *
 * نمونه:
 *   php artisan crm:fix-invoices-from-wp --dry-run        # گزارش
 *   php artisan crm:fix-invoices-from-wp                  # اعمال
 *   php artisan crm:fix-invoices-from-wp --technician=42  # فقط یک تکنسین
 */
class FixInvoicesFromWp extends Command
{
    protected $signature = 'crm:fix-invoices-from-wp
                            {--technician= : فقط فاکتورهای این تکنسین (id لاراولی)}
                            {--dry-run : فقط شمارش بدون اعمال}';

    protected $description = 'تصحیح company_share/tech_share فاکتورهای تاریخی با مقادیر اصلی WP (روی total_invoice)';

    public function handle(): int
    {
        $this->configureWpConnection();
        $prefix = env('WP_DB_PREFIX', 'or_');

        try {
            $wp = DB::connection('wp_crm');
            $wp->getPdo();
        } catch (\Throwable $e) {
            $this->error('اتصال به WP DB ممکن نشد: ' . $e->getMessage());
            return self::FAILURE;
        }

        $base = Invoice::withoutGlobalScope('active')
            ->whereNotNull('wp_id')
            ->whereNotNull('technician_id');

        if ($techId = $this->option('technician')) {
            $base->where('technician_id', (int) $techId);
        }

        $total = (clone $base)->count();
        $this->info("فاکتورهای هدف: {$total}");
        if ($total === 0) return self::SUCCESS;

        // suppress push back به WP
        app()->instance('crm.suppress_outbound_push', true);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $changed = 0;
        $unchanged = 0;
        $missing = 0;
        $samples = []; // 10 تای اول برای نمایش

        $base->with(['technician:id,wp_id', 'order:id,status'])
            ->select(['id', 'wp_id', 'technician_id', 'order_id', 'total_amount', 'tech_share', 'company_share', 'commission_percent', 'calc_type'])
            ->orderBy('id')
            ->chunkById(200, function ($invoices) use ($wp, $prefix, $bar, &$changed, &$unchanged, &$missing, &$samples) {
                $wpIds = $invoices->pluck('wp_id')->all();

                // bulk: همه postmetaهای مرتبط با فاکتورها
                $metas = $wp->table($prefix.'postmeta')
                    ->whereIn('post_id', $wpIds)
                    ->whereIn('meta_key', ['price_customer', 'cost_price', 'total_invoice'])
                    ->get();

                $metaByPost = [];
                foreach ($metas as $m) {
                    $metaByPost[(int) $m->post_id][$m->meta_key] = $m->meta_value;
                }

                // bulk: درصد همه تکنسین‌های مرتبط از or_usermeta (مرجع WP)
                $techWpIds = $invoices->pluck('technician.wp_id')->filter()->unique()->all();
                $techMetaByUser = [];
                if (! empty($techWpIds)) {
                    $userMetas = $wp->table($prefix.'usermeta')
                        ->whereIn('user_id', $techWpIds)
                        ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
                        ->get();
                    foreach ($userMetas as $um) {
                        $techMetaByUser[(int) $um->user_id][$um->meta_key] = $um->meta_value;
                    }
                }

                foreach ($invoices as $inv) {
                    $meta = $metaByPost[(int) $inv->wp_id] ?? null;
                    if (! $meta) {
                        $missing++;
                        $bar->advance();
                        continue;
                    }

                    $priceCustomer = (int) ($meta['price_customer'] ?? 0);
                    $costPrice = (int) ($meta['cost_price'] ?? 0);
                    $totalInvoice = (int) ($meta['total_invoice'] ?? 0);

                    if ($totalInvoice === 0 && $priceCustomer > 0) {
                        $totalInvoice = max(0, $priceCustomer - $costPrice);
                    }
                    if ($totalInvoice === 0) {
                        $missing++;
                        $bar->advance();
                        continue;
                    }

                    $tech = $inv->technician;
                    if (! $tech || ! $tech->wp_id) {
                        $missing++;
                        $bar->advance();
                        continue;
                    }

                    // ── محاسبه با درصد WP (مرجع) ─────────────────────
                    // به‌جای CommissionCalculator که از Panel می‌خواند،
                    // درصد را مستقیم از or_usermeta WP می‌گیریم تا نتیجه
                    // دقیقاً مثل WP CRM باشد.
                    $wpMeta = $techMetaByUser[(int) $tech->wp_id] ?? [];
                    $wpPercent      = (int) ($wpMeta['percent'] ?? 0);
                    $wpTechPerAll   = (int) ($wpMeta['tech_per_of_all'] ?? 0);
                    $wpCalcType     = (string) ($wpMeta['type_of_calc_tech'] ?? '');

                    // ایاب و ذهاب (Transit) → تکنسین ۱۰۰٪
                    $orderStatus = $inv->order?->status;
                    $statusValue = $orderStatus instanceof OrderStatus ? $orderStatus->value : (string) $orderStatus;
                    $isTransit = $statusValue === OrderStatus::Transit->value;

                    if ($isTransit) {
                        $newTechShare = $totalInvoice;
                        $newCompanyShare = 0;
                        $newPercent = 100;
                        $newCalcType = $wpCalcType !== '' ? $wpCalcType : 'transit_full';
                    } elseif ($wpCalcType === '1' || $wpCalcType === 'internal') {
                        // Internal: درصد شرکت = tech_per_of_all
                        $newCompanyShare = intdiv($totalInvoice * max(0, min(100, $wpTechPerAll)), 100);
                        $newTechShare = max(0, $totalInvoice - $newCompanyShare);
                        $newPercent = $wpTechPerAll;
                        $newCalcType = $wpCalcType;
                    } else {
                        // External: درصد شرکت = percent
                        $newCompanyShare = intdiv($totalInvoice * max(0, min(100, $wpPercent)), 100);
                        $newTechShare = max(0, $totalInvoice - $newCompanyShare);
                        $newPercent = $wpPercent;
                        $newCalcType = $wpCalcType !== '' ? $wpCalcType : 'percent_of_total';
                    }

                    $newTotalAmount = $totalInvoice;

                    $isSame = (int) $inv->total_amount === $newTotalAmount
                        && (int) $inv->tech_share === $newTechShare
                        && (int) $inv->company_share === $newCompanyShare;

                    if ($isSame) {
                        $unchanged++;
                        $bar->advance();
                        continue;
                    }

                    if (count($samples) < 15) {
                        $samples[] = [
                            'id' => $inv->id,
                            'wp_id' => $inv->wp_id,
                            'before' => "tech={$inv->tech_share}, company={$inv->company_share}",
                            'after' => "tech={$newTechShare}, company={$newCompanyShare}",
                            'pc' => $priceCustomer,
                            'cp' => $costPrice,
                            'ti' => $totalInvoice,
                            'wp_percent' => $wpPercent,
                            'wp_internal' => $wpTechPerAll,
                            'calc' => $wpCalcType === '1' ? 'INT' : 'EXT',
                            'used' => "{$newPercent}%",
                        ];
                    }

                    if (! $this->option('dry-run')) {
                        DB::table('crm_invoices')
                            ->where('id', $inv->id)
                            ->update([
                                'total_amount' => $newTotalAmount,
                                'tech_share' => $newTechShare,
                                'company_share' => $newCompanyShare,
                                'commission_percent' => $newPercent,
                                'calc_type' => $newCalcType,
                                'updated_at' => now(),
                            ]);
                    }

                    $changed++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("نیاز به تغییر: {$changed}");
        $this->line("بدون تغییر: {$unchanged}");
        if ($missing > 0) $this->warn("داده WP کافی نبود: {$missing}");

        if (! empty($samples)) {
            $this->newLine();
            $this->info('نمونه‌های قبل/بعد:');
            $rows = [];
            foreach ($samples as $s) {
                $rows[] = [
                    $s['id'],
                    $s['wp_id'],
                    "pc={$s['pc']} cp={$s['cp']} ti={$s['ti']}",
                    "WP:{$s['calc']} %={$s['wp_percent']} per_all={$s['wp_internal']} → {$s['used']}",
                    $s['before'],
                    $s['after'],
                ];
            }
            $this->table(['inv_id', 'wp_id', 'WP data', 'WP درصد تکنسین', 'قبل Panel', 'بعد (مطابق WP)'], $rows);
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run فعال — هیچ تغییری اعمال نشد.');
            $this->line('برای اعمال: php artisan crm:fix-invoices-from-wp');
            $this->line('سپس: php artisan crm:wallet:recompute-balances');
        } else {
            $this->newLine();
            $this->info('✓ تصحیح فاکتورها انجام شد.');
            $this->warn('گام بعدی: php artisan crm:wallet:recompute-balances');
        }

        return self::SUCCESS;
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
