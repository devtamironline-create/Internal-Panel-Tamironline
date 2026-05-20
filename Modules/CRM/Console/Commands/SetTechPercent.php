<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;

/**
 * تنظیم درصد تکنسین به‌صورت همگام در Panel و WP CRM.
 *
 * نمونه‌ها:
 *   # نمایش وضعیت همه (Panel/WP)
 *   php artisan crm:set-tech-percent
 *
 *   # نمایش وضعیت یک تکنسین
 *   php artisan crm:set-tech-percent --tech=42
 *   php artisan crm:set-tech-percent --mobile=09120000000
 *
 *   # تنظیم یک تکنسین خاص (External, درصد شرکت=30 → تکنسین 70٪)
 *   php artisan crm:set-tech-percent --tech=42 --percent=30 --calc=external --apply
 *
 *   # تنظیم یک تکنسین خاص (Internal, درصد شرکت=50 → تکنسین 50٪)
 *   php artisan crm:set-tech-percent --tech=42 --per-all=50 --calc=internal --apply
 *
 *   # تنظیم همه به External 50%
 *   php artisan crm:set-tech-percent --all --percent=50 --calc=external --apply
 */
class SetTechPercent extends Command
{
    protected $signature = 'crm:set-tech-percent
                            {--tech= : id تکنسین در Panel}
                            {--mobile= : موبایل تکنسین}
                            {--wp= : wp_id تکنسین}
                            {--all : همه تکنسین‌ها (بدون فیلتر)}
                            {--percent= : درصد External (سهم شرکت 0-100)}
                            {--per-all= : درصد Internal — tech_per_of_all (سهم شرکت 0-100)}
                            {--calc= : نوع محاسبه — external|internal}
                            {--apply : اعمال (پیش‌فرض dry-run)}
                            {--panel-only : فقط Panel آپدیت شود (WP دست‌نخورده)}
                            {--wp-only : فقط WP آپدیت شود (Panel دست‌نخورده)}';

    protected $description = 'تنظیم درصد تکنسین در Panel و WP CRM به‌صورت همگام';

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

        // ── انتخاب تکنسین‌ها ───────────────────────────────────────
        $query = Technician::query()->whereNotNull('wp_id');
        if ($id = $this->option('tech')) {
            $query->where('id', (int) $id);
        } elseif ($mobile = $this->option('mobile')) {
            $query->where('mobile', $mobile);
        } elseif ($wpId = $this->option('wp')) {
            $query->where('wp_id', (int) $wpId);
        } elseif (! $this->option('all')) {
            // اگر هیچ فیلتری نداده، فقط لیست همه را نشان بده
            $this->showAll($wp, $prefix);
            return self::SUCCESS;
        }

        $techs = $query->get(['id', 'wp_id', 'mobile', 'firstname_tech', 'first_name', 'last_name', 'percent', 'tech_per_of_all', 'type_of_calc_tech']);
        if ($techs->isEmpty()) {
            $this->warn('تکنسینی پیدا نشد.');
            return self::SUCCESS;
        }

        // ── تعیین مقادیر هدف ───────────────────────────────────────
        $hasPercent = $this->option('percent') !== null;
        $hasPerAll  = $this->option('per-all') !== null;
        $hasCalc    = $this->option('calc') !== null;

        if (! $hasPercent && ! $hasPerAll && ! $hasCalc) {
            // فقط نمایش وضعیت
            $this->showTechs($techs, $wp, $prefix);
            return self::SUCCESS;
        }

        $newPercent = $hasPercent ? max(0, min(100, (int) $this->option('percent'))) : null;
        $newPerAll  = $hasPerAll ? max(0, min(100, (int) $this->option('per-all'))) : null;
        $newCalc    = null;
        if ($hasCalc) {
            $c = strtolower((string) $this->option('calc'));
            if (! in_array($c, ['external', 'internal'], true)) {
                $this->error('--calc باید external یا internal باشد.');
                return self::FAILURE;
            }
            $newCalc = $c === 'internal' ? '1' : '';
        }

        $apply     = (bool) $this->option('apply');
        $panelOnly = (bool) $this->option('panel-only');
        $wpOnly    = (bool) $this->option('wp-only');

        if ($panelOnly && $wpOnly) {
            $this->error('--panel-only و --wp-only با هم نمی‌توانند ست شوند.');
            return self::FAILURE;
        }

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — تکنسین‌های هدف: " . $techs->count());
        $this->newLine();

        $rows = [];
        $updated = 0;

        foreach ($techs as $tech) {
            // فعلی WP
            $wpMeta = $wp->table($prefix.'usermeta')
                ->where('user_id', $tech->wp_id)
                ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
                ->pluck('meta_value', 'meta_key')
                ->toArray();

            $name = mb_substr($tech->firstname_tech ?: trim($tech->first_name . ' ' . $tech->last_name), 0, 20);

            $rows[] = [
                $tech->id,
                $tech->wp_id,
                $name,
                $tech->mobile,
                // قبل
                'P:' . ($tech->percent ?? '-') . ' / W:' . ($wpMeta['percent'] ?? '-'),
                'P:' . ($tech->tech_per_of_all ?? '-') . ' / W:' . ($wpMeta['tech_per_of_all'] ?? '-'),
                'P:' . ($tech->type_of_calc_tech ?? '-') . ' / W:' . ($wpMeta['type_of_calc_tech'] ?? '-'),
                // بعد
                $newPercent !== null ? (string) $newPercent : '—',
                $newPerAll !== null ? (string) $newPerAll : '—',
                $newCalc !== null ? ($newCalc === '1' ? 'internal' : 'external') : '—',
            ];

            if (! $apply) continue;

            // ── اعمال Panel ──────────────────────────────────────
            if (! $wpOnly) {
                $panelUpdate = [];
                if ($newPercent !== null) $panelUpdate['percent'] = $newPercent;
                if ($newPerAll !== null)  $panelUpdate['tech_per_of_all'] = $newPerAll;
                if ($newCalc !== null)    $panelUpdate['type_of_calc_tech'] = $newCalc;

                if (! empty($panelUpdate)) {
                    // مستقیم با DB::table — جلوگیری از observer push
                    DB::table('crm_technicians')
                        ->where('id', $tech->id)
                        ->update($panelUpdate + ['updated_at' => now()]);
                }
            }

            // ── اعمال WP ─────────────────────────────────────────
            if (! $panelOnly) {
                $wpUpdate = [];
                if ($newPercent !== null) $wpUpdate['percent'] = (string) $newPercent;
                if ($newPerAll !== null)  $wpUpdate['tech_per_of_all'] = (string) $newPerAll;
                if ($newCalc !== null)    $wpUpdate['type_of_calc_tech'] = $newCalc;

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

            $updated++;
        }

        $this->table(
            ['id', 'wp_id', 'نام', 'موبایل', 'percent (P/W)', 'tech_per_of_all (P/W)', 'type_of_calc (P/W)', '→ percent', '→ per_all', '→ calc'],
            $rows
        );

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود — هیچ تغییری اعمال نشد. برای اعمال --apply اضافه کنید.');
        } else {
            $this->newLine();
            $this->info("✓ {$updated} تکنسین در Panel و WP به‌روز شدند.");
            $this->warn('یادآوری: این تغییر روی فاکتورهای تاریخی Panel اعمال نمی‌شود (snapshot قفل‌اند).');
            $this->warn('برای فاکتورهای تاریخی: php artisan crm:fix-invoices-from-wp');
        }

        return self::SUCCESS;
    }

    private function showAll($wp, string $prefix): void
    {
        $techs = Technician::whereNotNull('wp_id')
            ->orderBy('id')
            ->get(['id', 'wp_id', 'mobile', 'firstname_tech', 'first_name', 'last_name', 'percent', 'tech_per_of_all', 'type_of_calc_tech']);

        $this->showTechs($techs, $wp, $prefix);
    }

    private function showTechs($techs, $wp, string $prefix): void
    {
        $wpIds = $techs->pluck('wp_id')->all();
        $metas = $wp->table($prefix.'usermeta')
            ->whereIn('user_id', $wpIds)
            ->whereIn('meta_key', ['percent', 'tech_per_of_all', 'type_of_calc_tech'])
            ->get();
        $byUser = [];
        foreach ($metas as $m) {
            $byUser[(int) $m->user_id][$m->meta_key] = $m->meta_value;
        }

        $rows = [];
        foreach ($techs as $t) {
            $wpMeta = $byUser[(int) $t->wp_id] ?? [];
            $name = mb_substr($t->firstname_tech ?: trim($t->first_name . ' ' . $t->last_name), 0, 20);
            $diff = ((string) $t->percent !== (string) ($wpMeta['percent'] ?? ''))
                || ((string) ($t->tech_per_of_all ?? '') !== (string) ($wpMeta['tech_per_of_all'] ?? ''))
                || ((string) ($t->type_of_calc_tech ?? '') !== (string) ($wpMeta['type_of_calc_tech'] ?? ''));
            $rows[] = [
                $t->id,
                $t->wp_id,
                $name,
                $t->mobile,
                'P:' . ($t->percent ?? '-') . ' / W:' . ($wpMeta['percent'] ?? '-'),
                'P:' . ($t->tech_per_of_all ?? '-') . ' / W:' . ($wpMeta['tech_per_of_all'] ?? '-'),
                'P:' . ($t->type_of_calc_tech ?? '-') . ' / W:' . ($wpMeta['type_of_calc_tech'] ?? '-'),
                $diff ? '⚠' : '✓',
            ];
        }

        $this->table(['id', 'wp_id', 'نام', 'موبایل', 'percent', 'per_all', 'calc', 'وضعیت'], $rows);
        $this->info('برای تنظیم: --tech=X --percent=N --calc=external|internal --apply');
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
