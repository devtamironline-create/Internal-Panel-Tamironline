<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Technician;

/**
 * بررسی دستی فاکتورهای یک تکنسین — نمایش side-by-side از WP و Panel.
 * فقط-خواندنی.
 *
 * نمونه:
 *   php artisan crm:verify-invoice-calc --tech=42
 *   php artisan crm:verify-invoice-calc --tech=42 --limit=20
 */
class VerifyInvoiceCalc extends Command
{
    protected $signature = 'crm:verify-invoice-calc
                            {--tech= : id تکنسین (Panel)}
                            {--mobile= : موبایل تکنسین}
                            {--limit=10 : تعداد فاکتور نمایشی}';

    protected $description = 'مقایسه side-by-side اعداد فاکتورها بین WP و Panel (فقط-خواندنی)';

    public function handle(): int
    {
        $this->configureWpConnection();
        try {
            $wp = DB::connection('wp_crm');
            $wp->getPdo();
        } catch (\Throwable $e) {
            $this->error('اتصال به WP DB ناموفق.');
            return self::FAILURE;
        }
        $prefix = env('WP_DB_PREFIX', 'or_');

        $query = Technician::query();
        if ($id = $this->option('tech')) {
            $query->where('id', (int) $id);
        } elseif ($mobile = $this->option('mobile')) {
            $query->where('mobile', $mobile);
        } else {
            $this->error('--tech یا --mobile الزامی است.');
            return self::FAILURE;
        }

        $tech = $query->first();
        if (! $tech) {
            $this->error('تکنسین پیدا نشد.');
            return self::FAILURE;
        }

        // WP percent
        $wpMeta = $tech->wp_id ? $wp->table($prefix.'usermeta')
            ->where('user_id', $tech->wp_id)
            ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
            ->pluck('meta_value', 'meta_key')->toArray() : [];

        $wpPercent = (int) ($wpMeta['percent'] ?? 0);
        $wpPerAll  = (int) ($wpMeta['tech_per_of_all'] ?? 0);
        $wpCalc    = (string) ($wpMeta['type_of_calc_tech'] ?? '');
        $isInternal = $wpCalc === '1' || $wpCalc === 'internal';
        $effectivePercent = $isInternal ? $wpPerAll : $wpPercent;

        $this->info('━━━ تکنسین ──────────────────────────');
        $this->table(['فیلد', 'Panel', 'WP'], [
            ['id', $tech->id, $tech->wp_id ?? '—'],
            ['نام', $tech->firstname_tech ?: trim($tech->first_name . ' ' . $tech->last_name), '—'],
            ['percent', $tech->percent ?? '-', $wpPercent],
            ['tech_per_of_all', $tech->tech_per_of_all ?? '-', $wpPerAll],
            ['type_of_calc_tech', $tech->type_of_calc_tech ?? '-', $wpCalc],
            ['درصد موثر', '—', $effectivePercent . '% (' . ($isInternal ? 'INT' : 'EXT') . ')'],
        ]);

        $invoices = Invoice::withoutGlobalScope('active')
            ->where('technician_id', $tech->id)
            ->whereNotNull('wp_id')
            ->orderByDesc('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($invoices->isEmpty()) {
            $this->warn('فاکتوری با wp_id پیدا نشد.');
            return self::SUCCESS;
        }

        $wpIds = $invoices->pluck('wp_id')->all();
        $metas = $wp->table($prefix.'postmeta')
            ->whereIn('post_id', $wpIds)
            ->whereIn('meta_key', ['price_customer', 'cost_price', 'total_invoice'])
            ->get();
        $metaByPost = [];
        foreach ($metas as $m) {
            $metaByPost[(int) $m->post_id][$m->meta_key] = $m->meta_value;
        }

        $this->newLine();
        $this->info('━━━ فاکتورها (آخرین ' . $invoices->count() . ' تا) ─────');

        $rows = [];
        foreach ($invoices as $inv) {
            $meta = $metaByPost[(int) $inv->wp_id] ?? [];
            $pc = (int) ($meta['price_customer'] ?? 0);
            $cp = (int) ($meta['cost_price'] ?? 0);
            $ti = (int) ($meta['total_invoice'] ?? 0);
            if ($ti === 0 && $pc > 0) $ti = max(0, $pc - $cp);

            // محاسبه صحیح
            $expectedCompany = intdiv($ti * $effectivePercent, 100);
            $expectedTech = max(0, $ti - $expectedCompany);

            $superseded = $inv->superseded_at ? 'S' : 'A';

            $rows[] = [
                $inv->id,
                $inv->wp_id,
                $superseded,
                number_format($pc),
                number_format($cp),
                number_format($ti),
                // Panel فعلی
                number_format((int) $inv->total_amount),
                number_format((int) $inv->tech_share),
                number_format((int) $inv->company_share),
                $inv->commission_percent . '%',
                // محاسبه درست
                number_format($expectedTech),
                number_format($expectedCompany),
            ];
        }

        $this->table(
            [
                'id', 'wp_id', 'A/S',
                'WP:pc', 'WP:cp', 'WP:ti',
                'Panel:total', 'Panel:tech', 'Panel:company', '%',
                'محاسبه‌صحیح:tech', 'محاسبه‌صحیح:company',
            ],
            $rows
        );

        $this->newLine();
        $this->line('A=Active, S=Superseded');
        $this->line('pc=price_customer, cp=cost_price, ti=total_invoice (مبنای کمیسیون)');
        $this->line('«محاسبه صحیح» = ti × ' . $effectivePercent . '% (با درصد WP)');
        $this->line('اگر Panel:tech با محاسبه‌صحیح فرق دارد → فاکتور نیاز به fix-invoices-from-wp دارد.');

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
