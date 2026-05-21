<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;

/**
 * جایگزینی **فقط اطلاعات پروفایل** یک تکنسین Panel از روی یک WP user.
 *
 * فیلدهای جایگزین:
 *   first_name, firstname_tech, technician_id, national_code,
 *   mobile, phone, phone_force, province, address, specialty,
 *   type_tech, description, img_personal, cart_img, status,
 *   ready_for_delivery
 *
 * فیلدهایی که دست‌نخورده می‌مانند (مهم!):
 *   wallet_balance, percent, tech_per_of_all, type_of_calc_tech,
 *   max_order, max_price, satisfaction_score, training_completed_at,
 *   order_sync_direction, wallet_sync_direction, wp_id (همان قبلی),
 *   user_id, password
 *
 * فاکتورها، تراکنش‌های کیف‌پول، سفارش‌ها → هیچ‌کدام لمس نمی‌شوند.
 *
 * نمونه:
 *   php artisan crm:replace-tech-info --panel-tech=42 --wp-source=96016
 *   php artisan crm:replace-tech-info --panel-tech=42 --wp-source=96016 --apply
 */
class ReplaceTechInfo extends Command
{
    protected $signature = 'crm:replace-tech-info
                            {--panel-tech= : id تکنسین در Panel (مقصد)}
                            {--wp-source= : wp_id کاربری در WP که اطلاعاتش را می‌خواهیم}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'جایگزینی فقط اطلاعات پروفایل یک تکنسین از روی WP user — بدون لمس wallet/invoices/percent';

    /** فیلدهایی که از WP کپی می‌شوند. */
    private const COPY_FIELDS = [
        'first_name',
        'firstname_tech',
        'technician_id',
        'national_code',
        'mobile',
        'phone',
        'phone_force',
        'province',
        'address',
        'specialty',
        'type_tech',
        'description',
        'img_personal',
        'cart_img',
        'status',
        'ready_for_delivery',
    ];

    public function handle(): int
    {
        $panelId = (int) $this->option('panel-tech');
        $wpId = (int) $this->option('wp-source');

        if (! $panelId || ! $wpId) {
            $this->error('--panel-tech و --wp-source هر دو الزامی هستند.');
            return self::FAILURE;
        }

        $tech = Technician::find($panelId);
        if (! $tech) {
            $this->error("تکنسین Panel با id={$panelId} پیدا نشد.");
            return self::FAILURE;
        }

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

        // خواندن WP user
        $user = $wp->table($prefix.'users')->where('ID', $wpId)->first();
        if (! $user) {
            $this->error("کاربری در WP با ID={$wpId} پیدا نشد.");
            return self::FAILURE;
        }

        $meta = $wp->table($prefix.'usermeta')
            ->where('user_id', $wpId)
            ->pluck('meta_value', 'meta_key')->toArray();

        // مپ فیلدهای WP → Laravel (هم‌سو با logic موجود در پروژه)
        $wpData = [
            'first_name' => $meta['first_name'] ?? null,
            'firstname_tech' => $meta['firstname_tech'] ?? null,
            'technician_id' => $meta['technician_id'] ?? null,
            'national_code' => $meta['national_code'] ?? null,
            'mobile' => $meta['mobile'] ?? null,
            'phone' => $meta['phone'] ?? null,
            'phone_force' => $meta['phone_force'] ?? null,
            'province' => $meta['province'] ?? null,
            'address' => $meta['address'] ?? null,
            'specialty' => $meta['specialty'] ?? null,
            'type_tech' => $meta['type_tech'] ?? null,
            'description' => $meta['description'] ?? null,
            'img_personal' => $meta['img_Personal'] ?? null, // در WP با P بزرگ
            'cart_img' => $meta['cart_img'] ?? null,
            'status' => $meta['status'] ?? null,
            'ready_for_delivery' => isset($meta['ready_for_derliver'])
                ? ((int) $meta['ready_for_derliver'] === 1 ? 1 : 0)
                : null,
        ];

        // مقایسه قبل/بعد
        $this->info('━━━ مقایسه فیلدها ─────────────────────────────');
        $this->info("Panel tech_id={$panelId}, WP source uid={$wpId}");
        $rows = [];
        $update = [];
        foreach (self::COPY_FIELDS as $field) {
            $current = $tech->{$field};
            $new = $wpData[$field] ?? null;
            $changed = ((string) $current !== (string) $new);

            $rows[] = [
                $field,
                mb_substr((string) ($current ?? '—'), 0, 30),
                mb_substr((string) ($new ?? '—'), 0, 30),
                $changed ? '⚠' : '✓',
            ];

            if ($changed && $new !== null) {
                $update[$field] = $new;
            }
        }
        $this->table(['فیلد', 'Panel فعلی', 'WP', 'تغییر'], $rows);

        $this->newLine();
        $this->info('فیلدهای دست‌نخورده (محفوظ):');
        $this->line('  wallet_balance, percent, tech_per_of_all, type_of_calc_tech');
        $this->line('  max_order, max_price, satisfaction_score, training_completed_at');
        $this->line('  order_sync_direction, wallet_sync_direction, wp_id, user_id');
        $this->newLine();
        $this->info('فاکتورها، wallet_txs، سفارش‌ها → لمس نمی‌شوند.');
        $this->newLine();

        if (empty($update)) {
            $this->info('✓ هیچ فیلدی نیاز به تغییر ندارد.');
            return self::SUCCESS;
        }

        $this->info('تعداد فیلد قابل تغییر: ' . count($update));

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line("php artisan crm:replace-tech-info --panel-tech={$panelId} --wp-source={$wpId} --apply");
            return self::SUCCESS;
        }

        // اعمال — مستقیم با DB::table تا observer push trigger نشود
        $update['updated_at'] = now();
        DB::table('crm_technicians')
            ->where('id', $panelId)
            ->update($update);

        $this->info('✓ اطلاعات پروفایل به‌روز شد. wallet/invoices/percent دست‌نخورده.');
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
