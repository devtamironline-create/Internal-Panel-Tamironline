<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;

/**
 * Merge: جایگزینی اطلاعات یک تکنسین Panel با اطلاعات یک WP user
 * + assign کردن سفارش‌های WP این user به تکنسین Panel.
 *
 * مرحله‌ها:
 *   ۱) شناسایی Panel tech (دستی با --panel-tech، یا auto با match موبایل)
 *   ۲) جایگزینی فیلدهای پروفایل از WP (مثل crm:replace-tech-info)
 *   ۳) پیدا کردن WP orders که technician_id = wp-source در آنهاست
 *   ۴) برای هر یک، اگر Panel order با همان wp_id موجود است،
 *      `technician_id` آن را به Panel tech تنظیم می‌کنیم
 *
 * فاکتورها، wallet_txs، درصد، wallet_balance → لمس نمی‌شوند.
 * سفارش‌ها فقط `technician_id` و `technician_wp_id` آپدیت می‌شوند.
 *
 * نمونه:
 *   php artisan crm:tech-merge-from-wp --wp-source=96016
 *   php artisan crm:tech-merge-from-wp --wp-source=96016 --panel-tech=42
 *   php artisan crm:tech-merge-from-wp --wp-source=96016 --apply
 */
class TechMergeFromWp extends Command
{
    protected $signature = 'crm:tech-merge-from-wp
                            {--wp-source= : wp_id کاربری WP که اطلاعاتش را می‌خواهیم}
                            {--panel-tech= : id تکنسین Panel (اختیاری، در صورت ندادن با موبایل auto-detect)}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'جایگزینی اطلاعات تکنسین Panel با WP user + assign سفارش‌ها';

    private const COPY_FIELDS = [
        'first_name', 'firstname_tech', 'technician_id', 'national_code',
        'mobile', 'phone', 'phone_force',
        'province', 'address', 'specialty', 'type_tech', 'description',
        'img_personal', 'cart_img', 'status', 'ready_for_delivery',
    ];

    public function handle(): int
    {
        $wpId = (int) $this->option('wp-source');
        if (! $wpId) {
            $this->error('--wp-source الزامی است.');
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

        // ── ۱) خواندن WP user ──────────────────────────────────────
        $wpUser = $wp->table($prefix.'users')->where('ID', $wpId)->first();
        if (! $wpUser) {
            $this->error("WP user با id={$wpId} پیدا نشد.");
            return self::FAILURE;
        }

        $wpMeta = $wp->table($prefix.'usermeta')
            ->where('user_id', $wpId)
            ->pluck('meta_value', 'meta_key')->toArray();

        $wpMobile = $wpMeta['mobile'] ?? null;
        $wpName = $wpMeta['firstname_tech'] ?? ($wpMeta['first_name'] ?? '—');

        $this->info("━━━ WP user ──────────────────────────────────────");
        $this->line("  wp_id: {$wpId}");
        $this->line("  نام: {$wpName}");
        $this->line("  موبایل: " . ($wpMobile ?? '—'));
        $this->newLine();

        // ── ۲) پیدا کردن Panel tech ────────────────────────────────
        $panelTechId = $this->option('panel-tech') ? (int) $this->option('panel-tech') : null;
        $panelTech = null;

        if ($panelTechId) {
            $panelTech = Technician::find($panelTechId);
            if (! $panelTech) {
                $this->error("Panel tech با id={$panelTechId} پیدا نشد.");
                return self::FAILURE;
            }
        } else {
            // Auto با موبایل
            if (! $wpMobile) {
                $this->error('WP user موبایل ندارد. --panel-tech را دستی بدهید.');
                return self::FAILURE;
            }
            $candidates = Technician::where('mobile', $wpMobile)->get();
            if ($candidates->isEmpty()) {
                $this->error("هیچ Panel tech با موبایل {$wpMobile} پیدا نشد. --panel-tech را دستی بدهید.");
                return self::FAILURE;
            }
            if ($candidates->count() > 1) {
                $this->error("چند Panel tech با موبایل {$wpMobile} موجود است:");
                foreach ($candidates as $c) {
                    $this->line("  id={$c->id}, نام={$c->firstname_tech}, wp_id={$c->wp_id}");
                }
                $this->warn('با --panel-tech دستی مشخص کن.');
                return self::FAILURE;
            }
            $panelTech = $candidates->first();
            $panelTechId = $panelTech->id;
        }

        $this->info("━━━ Panel tech (مقصد) ─────────────────────────");
        $this->line("  id: {$panelTech->id}");
        $this->line("  نام فعلی: " . ($panelTech->firstname_tech ?? '—'));
        $this->line("  wp_id فعلی: " . ($panelTech->wp_id ?? '—'));
        $this->line("  موبایل: " . $panelTech->mobile);
        $this->newLine();

        // ── ۳) مقایسه فیلدهای پروفایل ──────────────────────────────
        $newData = [
            'first_name' => $wpMeta['first_name'] ?? null,
            'firstname_tech' => $wpMeta['firstname_tech'] ?? null,
            'technician_id' => $wpMeta['technician_id'] ?? null,
            'national_code' => $wpMeta['national_code'] ?? null,
            'mobile' => $wpMeta['mobile'] ?? null,
            'phone' => $wpMeta['phone'] ?? null,
            'phone_force' => $wpMeta['phone_force'] ?? null,
            'province' => $wpMeta['province'] ?? null,
            'address' => $wpMeta['address'] ?? null,
            'specialty' => $wpMeta['specialty'] ?? null,
            'type_tech' => $wpMeta['type_tech'] ?? null,
            'description' => $wpMeta['description'] ?? null,
            'img_personal' => $wpMeta['img_Personal'] ?? null,
            'cart_img' => $wpMeta['cart_img'] ?? null,
            'status' => $wpMeta['status'] ?? null,
            'ready_for_delivery' => isset($wpMeta['ready_for_derliver'])
                ? ((int) $wpMeta['ready_for_derliver'] === 1 ? 1 : 0)
                : null,
        ];

        $rows = [];
        $update = [];
        foreach (self::COPY_FIELDS as $field) {
            $current = $panelTech->{$field};
            $new = $newData[$field] ?? null;
            $changed = ((string) $current !== (string) $new);
            $rows[] = [$field, mb_substr((string)($current ?? '—'), 0, 25), mb_substr((string)($new ?? '—'), 0, 25), $changed ? '⚠' : '✓'];
            if ($changed && $new !== null) {
                $update[$field] = $new;
            }
        }
        // wp_id را هم به wp-source تنظیم کن تا future syncها درست باشد
        if ((int) $panelTech->wp_id !== $wpId) {
            $rows[] = ['wp_id', $panelTech->wp_id ?? '—', $wpId, '⚠'];
            $update['wp_id'] = $wpId;
        }

        $this->info('━━━ تغییرات فیلدهای پروفایل ─────────────────');
        $this->table(['فیلد', 'Panel فعلی', 'WP', 'تغییر'], $rows);

        // ── ۴) پیدا کردن سفارش‌های WP این user ───────────────────
        $wpOrderIds = $wp->table($prefix.'postmeta')
            ->whereIn('meta_key', ['technician_id', 'technician'])
            ->where('meta_value', (string) $wpId)
            ->pluck('post_id')->unique()->all();

        // filter به post_type=orders
        $wpOrderIds = $wp->table($prefix.'posts')
            ->whereIn('ID', $wpOrderIds)
            ->where('post_type', 'orders')
            ->pluck('ID')->all();

        $this->newLine();
        $this->info("━━━ سفارش‌های WP که technician = {$wpId} ──");
        $this->line('  تعداد: ' . count($wpOrderIds));

        if (empty($wpOrderIds)) {
            $this->line('  هیچ سفارشی برای assign نیست.');
        } else {
            // Panel orders با همین wp_id
            $panelOrders = Order::whereIn('wp_id', $wpOrderIds)
                ->get(['id', 'wp_id', 'order_code', 'technician_id', 'status']);

            $needsReassign = $panelOrders->filter(fn($o) => (int) $o->technician_id !== $panelTechId);

            $this->line('  از این تعداد در Panel: ' . $panelOrders->count());
            $this->line('  نیاز به re-assign: ' . $needsReassign->count());

            if ($needsReassign->count() > 0 && $needsReassign->count() <= 30) {
                $sampleRows = [];
                foreach ($needsReassign as $o) {
                    $sampleRows[] = [$o->id, $o->wp_id, $o->order_code, $o->technician_id ?? '—', $o->status];
                }
                $this->table(['id', 'wp_id', 'code', 'tech_id فعلی', 'status'], $sampleRows);
            }
        }

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line("php artisan crm:tech-merge-from-wp --wp-source={$wpId} --panel-tech={$panelTechId} --apply");
            return self::SUCCESS;
        }

        // ── ۵) اعمال ─────────────────────────────────────────────
        app()->instance('crm.suppress_outbound_push', true);

        // الف) آپدیت پروفایل
        if (! empty($update)) {
            $update['updated_at'] = now();
            DB::table('crm_technicians')->where('id', $panelTechId)->update($update);
            $this->info('✓ اطلاعات پروفایل به‌روز شد.');
        }

        // ب) assign سفارش‌ها
        $assigned = 0;
        if (! empty($wpOrderIds)) {
            $assigned = DB::table('crm_orders')
                ->whereIn('wp_id', $wpOrderIds)
                ->update([
                    'technician_id' => $panelTechId,
                    'technician_wp_id' => $wpId,
                    'updated_at' => now(),
                ]);
        }

        $this->info("✓ {$assigned} سفارش به Panel tech={$panelTechId} تخصیص شد.");
        $this->newLine();
        $this->info('فاکتورها، wallet_txs، درصد، wallet_balance → لمس نشدند.');

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
