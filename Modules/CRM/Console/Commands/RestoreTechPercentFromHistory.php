<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Technician;

/**
 * بازگرداندن درصد واقعی هر تکنسین از روی تاریخچهٔ فاکتورهای خودش.
 *
 * منطق: هر فاکتور در Panel یک snapshot از percent در لحظهٔ صدور دارد
 * (commission_percent). با گرفتن «پرتکرارترین» percent در فاکتورهای
 * هر تکنسین، می‌فهمیم درصد واقعی تاریخی او چه بوده. سپس Panel و WP
 * را به آن مقدار بازمی‌گردانیم.
 *
 * این مفید است وقتی sync یا import کل تکنسین‌ها را روی یک درصد
 * (مثلاً ۳۰) ست کرده، در حالی که تکنسین‌های تاریخی هر کدام درصد
 * مختلف داشتند.
 *
 * نمونه:
 *   php artisan crm:restore-tech-percent-from-history
 *   php artisan crm:restore-tech-percent-from-history --tech=42
 *   php artisan crm:restore-tech-percent-from-history --apply
 *   php artisan crm:restore-tech-percent-from-history --min-invoices=5 --apply
 */
class RestoreTechPercentFromHistory extends Command
{
    protected $signature = 'crm:restore-tech-percent-from-history
                            {--tech= : فقط یک تکنسین (id Panel)}
                            {--min-invoices=3 : حداقل تعداد فاکتور برای اطمینان (پیش‌فرض 3)}
                            {--apply : اعمال (پیش‌فرض dry-run)}
                            {--panel-only : فقط Panel آپدیت شود (WP دست‌نخورده)}
                            {--wp-only : فقط WP آپدیت شود (Panel دست‌نخورده)}';

    protected $description = 'بازگرداندن درصد واقعی هر تکنسین از روی پرتکرارترین commission_percent فاکتورهای تاریخی‌اش';

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

        $minInvoices = max(1, (int) $this->option('min-invoices'));
        $apply = (bool) $this->option('apply');

        $query = Technician::query()->whereNotNull('wp_id');
        if ($id = $this->option('tech')) {
            $query->where('id', (int) $id);
        }
        $techs = $query->get(['id', 'wp_id', 'mobile', 'firstname_tech', 'first_name', 'last_name', 'percent', 'tech_per_of_all', 'type_of_calc_tech']);

        if ($techs->isEmpty()) {
            $this->warn('تکنسینی پیدا نشد.');
            return self::SUCCESS;
        }

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — تکنسین‌های هدف: {$techs->count()}, حداقل فاکتور: {$minInvoices}");
        $this->newLine();

        $rows = [];
        $changed = 0;
        $skippedNoData = 0;
        $skippedSame = 0;

        // WP usermeta برای همه‌ی تکنسین‌ها (یک‌بار)
        $wpIds = $techs->pluck('wp_id')->all();
        $wpMetasRaw = $wp->table($prefix.'usermeta')
            ->whereIn('user_id', $wpIds)
            ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
            ->get();
        $wpMetas = [];
        foreach ($wpMetasRaw as $m) {
            $wpMetas[(int) $m->user_id][$m->meta_key] = $m->meta_value;
        }

        foreach ($techs as $tech) {
            // پرتکرارترین commission_percent و calc_type از فاکتورهای تکنسین
            $stats = Invoice::withoutGlobalScope('active')
                ->where('technician_id', $tech->id)
                ->where('commission_percent', '>', 0)
                ->selectRaw('commission_percent, calc_type, COUNT(*) as cnt')
                ->groupBy('commission_percent', 'calc_type')
                ->orderByDesc('cnt')
                ->get();

            if ($stats->isEmpty()) {
                $skippedNoData++;
                continue;
            }

            $totalInvoices = (int) $stats->sum('cnt');
            if ($totalInvoices < $minInvoices) {
                $skippedNoData++;
                continue;
            }

            // پرتکرارترین (mode)
            $top = $stats->first();
            $histPercent = (int) $top->commission_percent;
            $histCalc = (string) ($top->calc_type ?? '');

            // نوع calc: 'transit_full' را نادیده بگیر (موقت)
            $isInternal = $histCalc === '1' || $histCalc === 'internal';

            // درصد فعلی Panel
            $currentPanelPercent = $isInternal
                ? (int) ($tech->tech_per_of_all ?? 0)
                : (int) ($tech->percent ?? 0);
            $currentPanelCalc = (string) ($tech->type_of_calc_tech ?? '');

            // درصد فعلی WP
            $wpMeta = $wpMetas[(int) $tech->wp_id] ?? [];
            $currentWpPercent = $isInternal
                ? (int) ($wpMeta['tech_per_of_all'] ?? 0)
                : (int) ($wpMeta['percent'] ?? 0);
            $currentWpCalc = (string) ($wpMeta['type_of_calc_tech'] ?? '');

            $needsPanelUpdate = $currentPanelPercent !== $histPercent || $currentPanelCalc !== $histCalc;
            $needsWpUpdate = $currentWpPercent !== $histPercent || $currentWpCalc !== $histCalc;

            $name = mb_substr($tech->firstname_tech ?: trim($tech->first_name . ' ' . $tech->last_name), 0, 18);

            $rows[] = [
                $tech->id,
                $tech->wp_id,
                $name,
                $totalInvoices,
                $isInternal ? 'INT' : 'EXT',
                $histPercent . '%',
                "P:{$currentPanelPercent}/W:{$currentWpPercent}",
                ($needsPanelUpdate || $needsWpUpdate) ? '⚠ تغییر' : '✓',
            ];

            if (! $needsPanelUpdate && ! $needsWpUpdate) {
                $skippedSame++;
                continue;
            }

            if (! $apply) {
                $changed++;
                continue;
            }

            // ── اعمال Panel ─────────────────────────────────────
            if (! $this->option('wp-only') && $needsPanelUpdate) {
                $panelUpdate = ['type_of_calc_tech' => $histCalc, 'updated_at' => now()];
                if ($isInternal) {
                    $panelUpdate['tech_per_of_all'] = $histPercent;
                } else {
                    $panelUpdate['percent'] = $histPercent;
                }
                DB::table('crm_technicians')
                    ->where('id', $tech->id)
                    ->update($panelUpdate);
            }

            // ── اعمال WP ────────────────────────────────────────
            if (! $this->option('panel-only') && $needsWpUpdate) {
                $wpUpdate = [];
                if ($isInternal) {
                    $wpUpdate['tech_per_of_all'] = (string) $histPercent;
                } else {
                    $wpUpdate['percent'] = (string) $histPercent;
                }
                $wpUpdate['type_of_calc_tech'] = $histCalc;

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

            $changed++;
        }

        if (! empty($rows)) {
            $this->table(
                ['id', 'wp_id', 'نام', '#فاکتور', 'حالت', 'تاریخی', 'فعلی P/W', 'وضعیت'],
                $rows
            );
        }

        $this->newLine();
        $this->info("نیاز به تغییر: {$changed}");
        $this->line("بدون تغییر: {$skippedSame}");
        if ($skippedNoData > 0) {
            $this->warn("داده ناکافی (کمتر از {$minInvoices} فاکتور): {$skippedNoData}");
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال --apply اضافه کنید.');
            $this->line('php artisan crm:restore-tech-percent-from-history --apply');
        } else {
            $this->newLine();
            $this->info("✓ {$changed} تکنسین در Panel و WP به درصد تاریخی بازگشت.");
            $this->warn('یادآوری: فاکتورهای تاریخی Panel تغییری نمی‌کنند (snapshot قفل‌اند).');
            $this->warn('WP خودش با درصد جدید فاکتورها را درست نمایش می‌دهد.');
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
