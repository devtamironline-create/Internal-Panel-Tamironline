<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;

/**
 * نمایش تنظیمات درصد/نوع محاسبه تکنسین‌ها مستقیم از WP DB.
 * فقط-خواندنی — هیچ تغییری اعمال نمی‌کند.
 *
 * نمونه:
 *   php artisan crm:show-wp-tech-settings                   # همه
 *   php artisan crm:show-wp-tech-settings --tech=42         # یک تکنسین (id Panel)
 *   php artisan crm:show-wp-tech-settings --wp=460          # با wp_id
 *   php artisan crm:show-wp-tech-settings --mobile=09120000000
 *   php artisan crm:show-wp-tech-settings --diff-only       # فقط اختلاف Panel/WP
 */
class ShowWpTechSettings extends Command
{
    protected $signature = 'crm:show-wp-tech-settings
                            {--tech= : id تکنسین در Panel}
                            {--wp= : wp_id تکنسین}
                            {--mobile= : موبایل تکنسین}
                            {--diff-only : فقط آنهایی که Panel و WP اختلاف دارند}';

    protected $description = 'نمایش percent/tech_per_of_all/type_of_calc_tech تکنسین‌ها در WP DB (فقط خواندنی)';

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

        // فیلتر روی Panel
        $query = Technician::query()->whereNotNull('wp_id');
        if ($id = $this->option('tech')) {
            $query->where('id', (int) $id);
        } elseif ($wpId = $this->option('wp')) {
            $query->where('wp_id', (int) $wpId);
        } elseif ($mobile = $this->option('mobile')) {
            $query->where('mobile', $mobile);
        }
        $techs = $query->orderBy('id')->get(['id', 'wp_id', 'mobile', 'first_name', 'last_name', 'firstname_tech', 'percent', 'tech_per_of_all', 'type_of_calc_tech']);

        if ($techs->isEmpty()) {
            $this->warn('تکنسینی پیدا نشد.');
            return self::SUCCESS;
        }

        $wpIds = $techs->pluck('wp_id')->all();
        $userMetas = $wp->table($prefix.'usermeta')
            ->whereIn('user_id', $wpIds)
            ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
            ->get();
        $byUser = [];
        foreach ($userMetas as $um) {
            $byUser[(int) $um->user_id][$um->meta_key] = $um->meta_value;
        }

        $rows = [];
        foreach ($techs as $t) {
            $wpMeta = $byUser[(int) $t->wp_id] ?? [];
            $wpPercent = $wpMeta['percent'] ?? '—';
            $wpPerAll  = $wpMeta['tech_per_of_all'] ?? '—';
            $wpCalc    = $wpMeta['type_of_calc_tech'] ?? '—';

            $diff = ((string) $t->percent !== (string) $wpPercent)
                || ((string) ($t->tech_per_of_all ?? '') !== (string) $wpPerAll)
                || ((string) ($t->type_of_calc_tech ?? '') !== (string) $wpCalc);

            if ($this->option('diff-only') && ! $diff) continue;

            $rows[] = [
                $t->id,
                $t->wp_id,
                mb_substr($t->firstname_tech ?: ($t->first_name . ' ' . $t->last_name), 0, 25),
                $t->mobile,
                "P:{$t->percent} / W:{$wpPercent}",
                "P:" . ($t->tech_per_of_all ?? '—') . " / W:{$wpPerAll}",
                "P:" . ($t->type_of_calc_tech ?? '—') . " / W:{$wpCalc}",
                $diff ? '⚠' : '✓',
            ];
        }

        if (empty($rows)) {
            $this->info('✓ همه تکنسین‌ها همگام هستند (هیچ اختلاف Panel/WP نیست).');
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'wp_id', 'نام', 'موبایل', 'percent (Panel/WP)', 'tech_per_of_all', 'type_of_calc_tech', 'حالت'],
            $rows
        );

        $this->newLine();
        $this->info('P = مقدار در Panel، W = مقدار در WP CRM.');
        $this->info('⚠ یعنی Panel و WP اختلاف دارند.');

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
