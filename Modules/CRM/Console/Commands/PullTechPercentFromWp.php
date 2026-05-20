<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;

/**
 * کپی کردن «درصد» تکنسین‌ها از WP CRM به Panel (یک‌طرفه: WP → Panel).
 *
 * فقط این سه فیلد را کپی می‌کند، نه بقیه ستون‌ها:
 *   - percent (External)
 *   - tech_per_of_all (Internal)
 *   - type_of_calc_tech (external | internal)
 *
 * این برای موقعی است که در WP درصد را تنظیم کرده‌اید و می‌خواهید
 * Panel هم همان عدد را داشته باشد، بدون اینکه چیز دیگری از WP
 * (مثل profile، address، ...) را override کنید.
 *
 * نمونه:
 *   php artisan crm:pull-tech-percent-from-wp                # گزارش
 *   php artisan crm:pull-tech-percent-from-wp --tech=42      # یک تکنسین
 *   php artisan crm:pull-tech-percent-from-wp --apply        # اعمال
 */
class PullTechPercentFromWp extends Command
{
    protected $signature = 'crm:pull-tech-percent-from-wp
                            {--tech= : فقط یک تکنسین (id Panel)}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'کپی percent/tech_per_of_all/type_of_calc_tech از WP usermeta به Panel';

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
        if ($id = $this->option('tech')) {
            $query->where('id', (int) $id);
        }
        $techs = $query->get(['id', 'wp_id', 'mobile', 'firstname_tech', 'first_name', 'last_name', 'percent', 'tech_per_of_all', 'type_of_calc_tech']);

        if ($techs->isEmpty()) {
            $this->warn('تکنسینی پیدا نشد.');
            return self::SUCCESS;
        }

        // bulk read of WP usermeta
        $wpIds = $techs->pluck('wp_id')->all();
        $metas = $wp->table($prefix.'usermeta')
            ->whereIn('user_id', $wpIds)
            ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
            ->get();
        $byUser = [];
        foreach ($metas as $m) {
            $byUser[(int) $m->user_id][$m->meta_key] = $m->meta_value;
        }

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — تکنسین‌ها: {$techs->count()}");
        $this->newLine();

        $rows = [];
        $updated = 0;
        $same = 0;

        foreach ($techs as $tech) {
            $wp = $byUser[(int) $tech->wp_id] ?? [];
            $wpPercent = isset($wp['percent']) ? (int) $wp['percent'] : null;
            $wpPerAll  = isset($wp['tech_per_of_all']) ? (int) $wp['tech_per_of_all'] : null;
            $wpCalc    = $wp['type_of_calc_tech'] ?? null;

            $update = [];
            if ($wpPercent !== null && (int) $tech->percent !== $wpPercent) {
                $update['percent'] = $wpPercent;
            }
            if ($wpPerAll !== null && (int) ($tech->tech_per_of_all ?? 0) !== $wpPerAll) {
                $update['tech_per_of_all'] = $wpPerAll;
            }
            if ($wpCalc !== null && (string) ($tech->type_of_calc_tech ?? '') !== (string) $wpCalc) {
                $update['type_of_calc_tech'] = $wpCalc;
            }

            $name = mb_substr($tech->firstname_tech ?: trim($tech->first_name . ' ' . $tech->last_name), 0, 20);

            if (empty($update)) {
                $same++;
                continue;
            }

            $rows[] = [
                $tech->id,
                $tech->wp_id,
                $name,
                'P:' . ($tech->percent ?? '-') . ' → W:' . ($wpPercent ?? '-'),
                'P:' . ($tech->tech_per_of_all ?? '-') . ' → W:' . ($wpPerAll ?? '-'),
                'P:' . ($tech->type_of_calc_tech ?? '-') . ' → W:' . ($wpCalc ?? '-'),
            ];

            if ($apply) {
                DB::table('crm_technicians')
                    ->where('id', $tech->id)
                    ->update($update + ['updated_at' => now()]);
            }
            $updated++;
        }

        if (! empty($rows)) {
            $this->table(['id', 'wp_id', 'نام', 'percent (P→W)', 'per_all (P→W)', 'calc (P→W)'], $rows);
        }

        $this->newLine();
        $this->info("نیاز به تغییر: {$updated}");
        $this->line("از قبل یکسان: {$same}");

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال --apply اضافه کنید:');
            $this->line('php artisan crm:pull-tech-percent-from-wp --apply');
        } else {
            $this->newLine();
            $this->info("✓ {$updated} تکنسین در Panel به‌روز شد (طبق WP).");
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
