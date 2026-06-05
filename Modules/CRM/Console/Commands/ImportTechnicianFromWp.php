<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Models\Technician;

/**
 * Import یک تکنسین خاص از WP CRM به Laravel — وقتی تکنسین در WP وجود
 * دارد ولی به Laravel هرگز سینک نشده (مثلاً به دلیل خطای hookها).
 *
 * نمونه:
 *   php artisan crm:import-tech-from-wp 96015
 *
 * منبع: or_users + or_usermeta روی DB وردپرسی (credentials از env یا
 * مقادیر پیش‌فرض موجود).
 */
class ImportTechnicianFromWp extends Command
{
    protected $signature = 'crm:import-tech-from-wp {wp_id : شناسهٔ کاربر WP}
                            {--force : بدون تأیید}
                            {--update-existing : اگر تکنسینی با همان موبایل (یا wp_id) از قبل هست، آن را با داده WP overwrite کن}';

    protected $description = 'وارد کردن یک تکنسین از WP CRM به پنل لاراول بر اساس wp_id';

    public function handle(): int
    {
        $wpId = (int) $this->argument('wp_id');
        if ($wpId <= 0) {
            $this->error('wp_id نامعتبر است.');
            return self::FAILURE;
        }

        // اتصال موقت به DB وردپرس (در صورت نیاز credential از env بخوان)
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

        $prefix = env('WP_DB_PREFIX', 'or_');
        $wp = DB::connection('wp_crm');

        try {
            $wpUser = $wp->table($prefix.'users')->where('ID', $wpId)->first();
        } catch (\Throwable $e) {
            $this->error('اتصال به DB وردپرس ممکن نشد: ' . $e->getMessage());
            $this->warn('credential را در .env تنظیم کن:');
            $this->line('  WP_DB_HOST=...');
            $this->line('  WP_DB_NAME=crmtamironline_db_new');
            $this->line('  WP_DB_USER=crmtamironline_db_new');
            $this->line('  WP_DB_PASS=...');
            $this->line('  WP_DB_PREFIX=or_');
            return self::FAILURE;
        }

        if (! $wpUser) {
            $this->error("کاربر با wp_id={$wpId} در WP وجود ندارد.");
            return self::FAILURE;
        }

        // اگر تکنسین قبلاً در لاراول هست
        $existing = Technician::where('wp_id', $wpId)->first();
        if (! $existing) {
            // ممکن است با موبایل match کند (همان فرد، ولی wp_id نخورده)
            $mobileFromMeta = $this->normalizeMobile(($wp->table($prefix.'usermeta')->where('user_id', $wpId)->where('meta_key', 'mobile')->value('meta_value')) ?? '');
            if ($mobileFromMeta !== '') {
                $existing = Technician::where('mobile', $mobileFromMeta)->first();
            }
        }
        if ($existing && ! $this->option('update-existing')) {
            $this->warn("این تکنسین قبلاً در لاراول وجود دارد: id={$existing->id}، نام={$existing->firstname_tech} (wp_id={$existing->wp_id})");
            $this->line('برای overwrite با داده WP، فلگ --update-existing را اضافه کن.');
            return self::SUCCESS;
        }

        // متاهای کاربر را بخوان
        $meta = $wp->table($prefix.'usermeta')
            ->where('user_id', $wpId)
            ->pluck('meta_value', 'meta_key')
            ->toArray();

        $role = (string) ($meta['role'] ?? '');
        if ($role !== 'technician') {
            $this->warn("نقش این کاربر technician نیست (role={$role}).");
            if (! $this->option('force') && ! $this->confirm('با این حال ایمپورت شود؟', false)) {
                return self::SUCCESS;
            }
        }

        // mapper متاهای WP → ستون‌های Laravel
        $data = [
            'wp_id'              => $wpId,
            'first_name'         => $this->str($meta['first_name'] ?? ''),
            'firstname_tech'     => $this->str($meta['firstname_tech'] ?? ''),
            'technician_id'      => $this->str($meta['technician_id'] ?? ''),
            'national_code'      => $this->str($meta['national_code'] ?? ''),
            'mobile'             => $this->normalizeMobile($meta['mobile'] ?? ''),
            'phone'              => $this->normalizeMobile($meta['phone'] ?? '') ?: null,
            'phone_force'        => $this->normalizeMobile($meta['phone_force'] ?? '') ?: null,
            'address'            => $this->str($meta['address'] ?? ''),
            'description'        => $this->str($meta['description'] ?? ''),
            'percent'            => (int) ($meta['percent'] ?? 0),
            'tech_per_of_all'    => $this->intOrNull($meta['tech_per_of_all'] ?? null),
            'max_order'          => $this->intOrNull($meta['max_order'] ?? null),
            'max_price'          => $this->intOrNull($meta['max_price'] ?? null),
            'type_of_calc_tech'  => $this->str($meta['type_of_calc_tech'] ?? ''),
            'status'             => $this->normalizeStatus($meta['status'] ?? ''),
            'type_tech'          => $this->str($meta['type_tech'] ?? ''),
            'province'           => $this->str($meta['province'] ?? ''),
            'specialty'          => $this->str($meta['specialty'] ?? ''),
            'cart_img'           => $this->str($meta['cart_img'] ?? ''),
            'img_personal'       => $this->str($meta['img_Personal'] ?? ''), // WP با P بزرگ
            'ready_for_delivery' => filter_var($meta['ready_for_derliver'] ?? '0', FILTER_VALIDATE_BOOLEAN),
            // چون این تکنسین از قبل کار می‌کرده، آموزش را تمام‌شده در نظر بگیر
            'training_completed_at' => now(),
        ];

        // نمایش خلاصه
        $this->info("داده‌های آماده برای ایمپورت:");
        $this->line("  نام: " . ($data['firstname_tech'] ?: $data['first_name'] ?: '—'));
        $this->line("  موبایل: " . ($data['mobile'] ?: '—'));
        $this->line("  کد ملی: " . ($data['national_code'] ?: '—'));
        $this->line("  استان: " . ($data['province'] ?: '—'));
        $this->line("  تخصص: " . ($data['specialty'] ?: '—'));
        $this->line("  نوع: " . ($data['type_tech'] ?: '—'));
        $this->line("  درصد: " . $data['percent']);
        $this->line("  وضعیت: " . $data['status']);

        if (! $this->option('force') && ! $this->confirm('ایمپورت انجام شود؟', true)) {
            $this->info('لغو شد.');
            return self::SUCCESS;
        }

        // suppress push back به WP — این تکنسین از قبل آنجاست
        app()->instance('crm.suppress_outbound_push', true);

        try {
            if ($existing) {
                // overwrite کامل با داده WP — id لاراولی حفظ می‌شود تا
                // سفارش‌ها و فاکتورهای موجود به این رکورد متصل بمانند.
                $oldWpId = $existing->wp_id;
                $existing->forceFill($data)->save();
                $this->info("✓ تکنسین به‌روز شد در لاراول:");
                $this->line("  id={$existing->id} (حفظ شد)");
                $this->line("  wp_id={$existing->wp_id} (قبلاً: " . ($oldWpId ?: 'NULL') . ')');
                $this->line("  نام: {$existing->firstname_tech}");
            } else {
                $tech = Technician::create($data);
                $this->info("✓ تکنسین ساخته شد در لاراول:");
                $this->line("  id={$tech->id}");
                $this->line("  wp_id={$tech->wp_id}");
                $this->line("  نام: {$tech->firstname_tech}");
            }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('خطا: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function str($v): string
    {
        return trim((string) $v);
    }

    private function intOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        $i = (int) $v;
        return $i === 0 ? null : $i;
    }

    private function normalizeMobile($v): string
    {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $latin   = ['0','1','2','3','4','5','6','7','8','9'];
        $v = str_replace($persian, $latin, (string) $v);
        $v = str_replace($arabic, $latin, $v);
        return preg_replace('/\D/', '', trim($v));
    }

    private function normalizeStatus($v): string
    {
        $v = strtolower(trim((string) $v));
        if (in_array($v, ['1', 'active', 'enable', 'enabled', 'on', 'yes', 'true'], true)) {
            return 'active';
        }
        return 'inactive';
    }
}
