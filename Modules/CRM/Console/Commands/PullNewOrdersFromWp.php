<?php

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\CRM\Http\Controllers\Api\SyncOrderController;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;

/**
 * Pull سفارش‌های جدید از WP CRM که در Panel نیستند. مفید برای موقعی
 * که WP plugin push نکرده ولی سفارش‌ها در WP موجودند.
 *
 * منطق:
 *   ۱) همه post_type=orders در WP که wp_id آنها در Panel نیست را پیدا می‌کند
 *   ۲) postmeta هر سفارش را به فرمت SyncOrderController می‌فرستد
 *   ۳) همان upsertOne داخلی فراخوانی می‌شود (reuse منطق inbound)
 *
 * نمونه:
 *   php artisan crm:pull-new-orders-from-wp                  # dry-run
 *   php artisan crm:pull-new-orders-from-wp --since=2026-05-20
 *   php artisan crm:pull-new-orders-from-wp --apply
 */
class PullNewOrdersFromWp extends Command
{
    protected $signature = 'crm:pull-new-orders-from-wp
                            {--wp-id= : فقط یک سفارش خاص با این wp_id}
                            {--since= : فقط سفارش‌های بعد از این تاریخ (YYYY-MM-DD)}
                            {--limit=500 : حداکثر تعداد}
                            {--status= : فیلتر post_status با کاما (مثلاً publish,private)؛ پیش‌فرض همه publish,private,draft,pending}
                            {--debug : فقط dump متاها و taxonomyها (نیاز به --wp-id)}
                            {--force : حتی اگر در Panel موجود است، دوباره وارد کن}
                            {--apply : اعمال (پیش‌فرض dry-run)}';

    protected $description = 'Pull سفارش‌های WP که در Panel نیستند';

    public function handle(SyncOrderController $controller): int
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

        // پیش‌فرض همان statusهای استاندارد همهٔ کامندهای WP-importing
        // (publish, private, draft, pending). CRM واقعی این پروژه عمدتاً
        // private است نه publish. با --status می‌توان محدود کرد.
        // وقتی --wp-id داده شده، فیلتر status اعمال نمی‌شود — کاربر
        // صراحتاً یک ID خاص خواسته و باید بدون توجه به status بیاید
        // (مثلاً custom statusهای پلاگین orders).
        $statuses = $this->option('status')
            ? array_filter(array_map('trim', explode(',', $this->option('status'))))
            : ['publish', 'private', 'draft', 'pending'];

        $query = $wp->table($prefix.'posts')
            ->where('post_type', 'orders');

        if ($wpId = $this->option('wp-id')) {
            $query->where('ID', (int) $wpId);
        } else {
            $query->whereIn('post_status', $statuses);
        }

        // ─── debug mode — فقط dump متاها و taxonomyها ─────────────
        if ($this->option('debug')) {
            if (! $wpId) {
                $this->error('--debug نیاز به --wp-id دارد.');
                return self::FAILURE;
            }
            $pid = (int) $wpId;
            $post = $wp->table($prefix.'posts')->where('ID', $pid)->first();
            if (! $post) {
                $this->error("سفارش wp_id={$pid} پیدا نشد.");
                return self::FAILURE;
            }
            $this->info("POST:");
            foreach (['ID','post_type','post_status','post_author','post_title','post_date','post_modified'] as $f) {
                $this->line("  {$f}: " . ($post->{$f} ?? '—'));
            }

            $this->newLine();
            $this->info('POSTMETA (key → value):');
            $metas = $wp->table($prefix.'postmeta')->where('post_id', $pid)->get(['meta_key','meta_value']);
            foreach ($metas as $m) {
                $v = mb_substr((string) $m->meta_value, 0, 100);
                $this->line('  ' . str_pad((string) $m->meta_key, 32) . ' → ' . $v);
            }

            $this->newLine();
            $this->info('TAXONOMIES:');
            $tx = $wp->table($prefix.'term_relationships as tr')
                ->join($prefix.'term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->join($prefix.'terms as t', 't.term_id', '=', 'tt.term_id')
                ->where('tr.object_id', $pid)
                ->get(['tt.taxonomy','t.term_id','t.name']);
            foreach ($tx as $r) {
                $this->line("  {$r->taxonomy}: {$r->term_id} ({$r->name})");
            }

            return self::SUCCESS;
        }
        if ($since = $this->option('since')) {
            $query->where('post_date', '>=', $since . ' 00:00:00');
        }

        $allWpIds = $query->orderByDesc('ID')->limit((int) $this->option('limit'))->pluck('ID')->all();

        if (empty($allWpIds)) {
            $this->warn('سفارشی در WP پیدا نشد.');
            return self::SUCCESS;
        }

        // exclude existing (مگر --force)
        if ($this->option('force')) {
            $missingIds = $allWpIds;
            $existingWpIds = [];
        } else {
            $existingWpIds = Order::whereIn('wp_id', $allWpIds)->pluck('wp_id')->all();
            $missingIds = array_diff($allWpIds, $existingWpIds);
        }

        $this->info(($apply ? '🔥 APPLY' : 'DRY-RUN') . " — سفارش WP بررسی‌شده: " . count($allWpIds));
        $this->info("فیلتر post_status: " . implode(', ', $statuses));
        $this->info("از قبل در Panel: " . count($existingWpIds));
        $this->info("گم‌شده (قابل ایمپورت): " . count($missingIds));

        if (empty($missingIds)) {
            $this->info('✓ همه سفارش‌های WP در Panel موجودند.');
            return self::SUCCESS;
        }

        // breakdown گم‌شده‌ها بر اساس post_status + sample
        $sampleSize = min(20, count($missingIds));
        $sample = array_slice($missingIds, 0, $sampleSize);
        $sampleRows = $wp->table($prefix.'posts')
            ->whereIn('ID', $sample)
            ->get(['ID', 'post_status', 'post_date', 'post_title']);
        $byStatus = $wp->table($prefix.'posts')
            ->whereIn('ID', $missingIds)
            ->selectRaw('post_status, COUNT(*) cnt')
            ->groupBy('post_status')
            ->pluck('cnt', 'post_status')
            ->all();

        $this->newLine();
        $this->line('— تفکیک گم‌شده‌ها بر اساس post_status —');
        foreach ($byStatus as $st => $cnt) {
            $this->line("  $st: $cnt");
        }
        $this->newLine();
        $this->line("— نمونهٔ {$sampleSize} گم‌شدهٔ اول —");
        $this->table(
            ['wp_id', 'post_status', 'post_date', 'post_title'],
            $sampleRows->map(fn ($r) => [$r->ID, $r->post_status, $r->post_date, mb_substr((string) $r->post_title, 0, 40)])->all()
        );

        if (! $apply) {
            $this->newLine();
            $this->warn('این dry-run بود. برای اعمال:');
            $this->line('php artisan crm:pull-new-orders-from-wp --apply');
            return self::SUCCESS;
        }

        // suppress outbound push
        app()->instance('crm.suppress_outbound_push', true);

        $created = 0;
        $failed = 0;
        $customersAdded = 0;

        $bar = $this->output->createProgressBar(count($missingIds));
        $bar->start();

        foreach (array_chunk($missingIds, 50) as $chunk) {
            $posts = $wp->table($prefix.'posts')->whereIn('ID', $chunk)->get()->keyBy('ID');
            $metas = $wp->table($prefix.'postmeta')->whereIn('post_id', $chunk)->get();
            $metaByPost = [];
            foreach ($metas as $m) {
                $metaByPost[(int) $m->post_id][$m->meta_key] = $m->meta_value;
            }

            // taxonomyهای هر سفارش (state, city, brand, device)
            $taxes = $wp->table($prefix.'term_relationships as tr')
                ->join($prefix.'term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
                ->whereIn('tr.object_id', $chunk)
                ->whereIn('tt.taxonomy', ['state', 'city', 'brand', 'device'])
                ->get(['tr.object_id', 'tt.taxonomy', 'tt.term_id']);
            $taxByPost = [];
            foreach ($taxes as $t) {
                $taxByPost[(int) $t->object_id][(string) $t->taxonomy] = (int) $t->term_id;
            }

            foreach ($posts as $pid => $post) {
                $data = $this->buildPayloadFromWp($pid, $post, $metaByPost[$pid] ?? [], $taxByPost[$pid] ?? []);

                // اطمینان از وجود مشتری — اگر در پنل نباشد، on-the-fly از
                // WP DB می‌کشیم. علت اصلی گم شدن سفارش‌ها در سینک عادی
                // همین «customer not synced yet» بود.
                $custWpId = (int) ($data['customer_wp_id'] ?? 0);
                if ($custWpId > 0 && ! Customer::where('wp_id', $custWpId)->exists()) {
                    if ($this->ensureCustomer($wp, $prefix, $custWpId)) {
                        $customersAdded++;
                    }
                }

                try {
                    // مستقیم upsertOne استفاده کن — همان منطق inbound endpoint
                    $req = new \Illuminate\Http\Request();
                    $req->merge($data);
                    $controller->upsert($req);
                    $created++;
                } catch (\Throwable $e) {
                    $failed++;
                    \Illuminate\Support\Facades\Log::warning('pull_new_orders.failed', [
                        'wp_id' => $pid, 'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ ایجاد شد: {$created}");
        if ($customersAdded > 0) $this->info("✓ مشتری جدید ایمپورت شد (پیش‌نیاز سفارش): {$customersAdded}");
        if ($failed > 0) $this->warn("✗ شکست خورد: {$failed} (در laravel.log جزئیات)");

        return self::SUCCESS;
    }

    /**
     * مشتری گم‌شده را از WP DB (wp_users + wp_usermeta) ایمپورت کن.
     * مرجع نگاشت: CustomerImporter (role=customer، متاهای first_name/mobile/phone).
     *
     * @return bool true اگر مشتری ساخته/پیدا شد، false اگر اطلاعات کافی نبود.
     */
    private function ensureCustomer($wp, string $prefix, int $userId): bool
    {
        $user = $wp->table($prefix.'users')->where('ID', $userId)->first();
        if (! $user) {
            \Illuminate\Support\Facades\Log::warning('pull_new_orders.missing_customer_user', [
                'customer_wp_id' => $userId,
            ]);
            return false;
        }

        $meta = $wp->table($prefix.'usermeta')
            ->where('user_id', $userId)
            ->whereIn('meta_key', ['first_name', 'mobile', 'phone', 'last_name'])
            ->pluck('meta_value', 'meta_key')
            ->all();

        // mobile در WP گاهی چندتایی است (مثلاً 09... -09...)؛ اولی را می‌گیریم.
        $mobileRaw = trim((string) ($meta['mobile'] ?? $user->user_login ?? ''));
        $parts = preg_split('/[\s\-,،\/]+/u', $mobileRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mobile = trim((string) ($parts[0] ?? ''));

        if ($mobile === '') {
            \Illuminate\Support\Facades\Log::warning('pull_new_orders.customer_no_mobile', [
                'customer_wp_id' => $userId,
            ]);
            return false;
        }

        try {
            // پترن SyncCustomerController: اول با wp_id، سپس با mobile.
            // crm_customers روی mobile unique است، پس اگر مشتری از قبل با
            // همین شماره (با wp_id=null یا متفاوت) باشد، باید آن را
            // به‌جای create جدید، update کنیم.
            $customer = Customer::where('wp_id', $userId)->first()
                ?: Customer::where('mobile', $mobile)->first();

            if ($customer) {
                $customer->wp_id = $userId;
                if (! $customer->first_name && ! empty($meta['first_name'])) {
                    $customer->first_name = $meta['first_name'];
                }
                if (! $customer->phone && ! empty($meta['phone'])) {
                    $customer->phone = $meta['phone'];
                }
                $customer->save();
            } else {
                Customer::create([
                    'wp_id'      => $userId,
                    'mobile'     => $mobile,
                    'first_name' => $meta['first_name'] ?? null,
                    'phone'      => $meta['phone'] ?? null,
                ]);
            }
            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('pull_new_orders.customer_create_failed', [
                'customer_wp_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * ساخت payload هم‌سازگار با SyncOrderController::rules().
     *
     * نگاشت کلیدهای WP postmeta → کلیدهای API:
     *   customer       → customer_wp_id (post_author هم fallback)
     *   technician     → technician_wp_id
     *   brand_id       → brand_wp_id
     *   device_id      → device_wp_id
     *   (taxonomy state) → state_wp_id
     *   (taxonomy city)  → city_wp_id
     *
     * نمونه دیتای واقعی WP که بر اساس آن این نگاشت ساخته شد در
     * commit مربوطه (debug dump سفارش 176251) آمده.
     */
    private function buildPayloadFromWp(int $pid, $post, array $meta, array $tax = []): array
    {
        $payload = [
            'wp_id' => $pid,
            'post_title' => (string) ($post->post_title ?? ''),
            'post_status' => (string) ($post->post_status ?? 'publish'),
            'post_date' => (string) ($post->post_date ?? null),
            'post_modified' => (string) ($post->post_modified ?? null),
            'post_author' => (int) ($post->post_author ?? 0),
        ];

        // — نگاشت کلیدهای WP → API —
        $renames = [
            'customer'       => 'customer_wp_id',
            'technician'     => 'technician_wp_id',
            'brand_id'       => 'brand_wp_id',
            'device_id'      => 'device_wp_id',
        ];

        // — کلیدهایی که در API استفاده نمی‌شوند (validation اضافی نمی‌خواهد) —
        $skipKeys = [
            'device_name', 'device_slug',
            'brand_name', 'brand_slug',
            'city_name', 'province_name',
            'created_at', 'created_at_gmt',
            'created_at_jalali', 'created_at_jalali_date', 'created_at_jalali_time',
            'scheduled_date_jalali', 'scheduled_date_gregorian',
            'tracking_code',
        ];

        $arrayKeys = ['piece_list', 'customer_price_list', 'buy_price_list'];
        $arrayKeysLooseDecode = ['order_description_content', 'order_note_content', 'log_return'];
        $serializedArrayKeys = ['objection'];

        foreach ($meta as $key => $value) {
            if (in_array($key, $skipKeys, true)) {
                continue;
            }

            $targetKey = $renames[$key] ?? $key;

            if (in_array($key, $arrayKeys, true)) {
                $decoded = json_decode((string) $value, true);
                $payload[$targetKey] = is_array($decoded)
                    ? $decoded
                    : (is_string($value) ? maybe_unserialize_php($value) : []);
            } elseif (in_array($key, $arrayKeysLooseDecode, true)) {
                $decoded = json_decode((string) $value, true);
                if (! is_array($decoded)) {
                    $decoded = is_string($value) ? maybe_unserialize_php($value) : [];
                }
                $payload[$targetKey] = is_array($decoded) ? $decoded : [];
            } elseif (in_array($key, $serializedArrayKeys, true)) {
                // objection در WP serialized PHP است (a:1:{...})
                $unser = is_string($value) ? maybe_unserialize_php($value) : null;
                $payload[$targetKey] = is_array($unser) ? $unser : ($value !== '' ? [$value] : []);
            } else {
                $payload[$targetKey] = $value;
            }
        }

        // — fallback مشتری از post_author اگر postmeta نداشت —
        if (empty($payload['customer_wp_id']) && ! empty($payload['post_author'])) {
            $payload['customer_wp_id'] = (int) $payload['post_author'];
        }

        // — taxonomies → state_wp_id / city_wp_id (brand/device از postmeta گرفته شد) —
        if (! empty($tax['state']))  $payload['state_wp_id'] = (int) $tax['state'];
        if (! empty($tax['city']))   $payload['city_wp_id']  = (int) $tax['city'];
        // اگر brand_id/device_id در postmeta نبود، از taxonomy
        if (empty($payload['brand_wp_id']) && ! empty($tax['brand'])) {
            $payload['brand_wp_id'] = (int) $tax['brand'];
        }
        if (empty($payload['device_wp_id']) && ! empty($tax['device'])) {
            $payload['device_wp_id'] = (int) $tax['device'];
        }

        // — پاک کردن تکنسین خالی (validation min:1 نیاز دارد) —
        if (isset($payload['technician_wp_id']) && (int) $payload['technician_wp_id'] === 0) {
            unset($payload['technician_wp_id']);
        }

        return $payload;
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

if (! function_exists('maybe_unserialize_php')) {
    function maybe_unserialize_php($data) {
        if (is_string($data) && preg_match('/^(a|s|i|d|b|N):/', $data)) {
            try { return @unserialize($data); } catch (\Throwable) {}
        }
        return [];
    }
}
