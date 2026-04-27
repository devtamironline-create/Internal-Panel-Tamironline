<?php
/**
 * سینک مشتری‌ها (کاربران WP با meta role=customer) به پنل لاراول.
 *
 * در این CRM وردپرسی، نقش مشتری در usermeta با کلید `role` و مقدار
 * `customer` نگه‌داری می‌شود (نه در فیلد استاندارد wp_capabilities).
 * بنابراین تمام تشخیص‌ها/بک‌فیل‌ها روی همان متا انجام می‌شود.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Customer_Sync
{
    /** متاهایی که تغییرشان باید سینک را تریگر کند. */
    private const META_KEYS = ['first_name', 'mobile', 'phone', 'role'];

    /** نام متای نقش (در این CRM سفارشی است، نه wp_capabilities). */
    private const ROLE_META_KEY = 'role';

    /** مقدار مورد انتظار متای نقش برای مشتری. */
    private const CUSTOMER_ROLE_VALUE = 'customer';

    public function __construct()
    {
        // hookهای real-time
        add_action('user_register', [$this, 'on_user_register'], 10, 1);
        add_action('profile_update', [$this, 'on_profile_update'], 10, 1);
        add_action('updated_user_meta', [$this, 'on_meta_updated'], 10, 4);
        add_action('added_user_meta', [$this, 'on_meta_updated'], 10, 4);

        // backfill (سینک کامل)
        add_action('admin_post_tcs_sync_all_customers', [$this, 'sync_all_customers']);

        // افزودن باکس سینک به صفحهٔ تنظیمات
        add_action('tcs_settings_after_form', [$this, 'render_box']);
    }

    public function on_user_register($user_id): void
    {
        $this->sync_user((int) $user_id);
    }

    public function on_profile_update($user_id): void
    {
        $this->sync_user((int) $user_id);
    }

    /**
     * فقط وقتی meta مرتبط با مشتری تغییر کرد، سینک می‌کنیم.
     */
    public function on_meta_updated($meta_id, $object_id, $meta_key, $_meta_value): void
    {
        if (! in_array($meta_key, self::META_KEYS, true)) {
            return;
        }
        $this->sync_user((int) $object_id);
    }

    /**
     * بررسی این‌که آیا یک کاربر مشتری است یا نه.
     * اولویت با متای سفارشی `role` است؛ اگر نبود، fallback به نقش
     * استاندارد وردپرس (wp_capabilities).
     */
    protected function is_customer(int $user_id): bool
    {
        $role_meta = get_user_meta($user_id, self::ROLE_META_KEY, true);
        if ($role_meta === self::CUSTOMER_ROLE_VALUE) {
            return true;
        }

        $user = get_userdata($user_id);
        if ($user && in_array(self::CUSTOMER_ROLE_VALUE, (array) $user->roles, true)) {
            return true;
        }

        return false;
    }

    /**
     * یک کاربر را سینک می‌کند (در صورتی که مشتری باشد).
     */
    public function sync_user(int $user_id): ?array
    {
        if (! $this->is_customer($user_id)) {
            return null;
        }

        $user = get_userdata($user_id);
        if (! $user) {
            return null;
        }

        $payload = $this->build_payload($user);
        if (empty($payload['mobile'])) {
            return null;
        }

        return TCS_API_Client::send_or_queue('customer', $payload);
    }

    /**
     * payload یک کاربر را بر اساس قالب لاراول می‌سازد.
     */
    protected function build_payload(\WP_User $user): array
    {
        $first_name = trim((string) get_user_meta($user->ID, 'first_name', true));
        $mobile_raw = trim((string) get_user_meta($user->ID, 'mobile', true));
        $phone_raw  = trim((string) get_user_meta($user->ID, 'phone', true));

        // برخی کاربران چند شماره را با خط‌تیره/فاصله/ویرگول چسبانده‌اند.
        // اولین مقدار mobile، در صورت خالی‌بودن phone، دومی به phone.
        $source = $mobile_raw !== '' ? $mobile_raw : (string) $user->user_login;
        $parts = preg_split('/[\s\-,،\/]+/u', $source, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $mobile    = isset($parts[0]) ? trim((string) $parts[0]) : '';
        $secondary = isset($parts[1]) ? trim((string) $parts[1]) : null;

        return [
            'wp_id'      => (int) $user->ID,
            'mobile'     => $mobile,
            'first_name' => $first_name !== '' ? $first_name : null,
            'phone'      => $phone_raw !== '' ? $phone_raw : $secondary,
        ];
    }

    /**
     * شمارش کل مشتری‌ها (بر اساس متای سفارشی role=customer).
     */
    protected function count_customers(): int
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
            self::ROLE_META_KEY,
            self::CUSTOMER_ROLE_VALUE
        );
        return (int) $wpdb->get_var($sql);
    }

    /**
     * گرفتن یک دسته از user_idهای مشتری از usermeta.
     *
     * @return int[]
     */
    protected function fetch_customer_ids(int $offset, int $limit): array
    {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
             WHERE meta_key = %s AND meta_value = %s
             ORDER BY user_id ASC
             LIMIT %d OFFSET %d",
            self::ROLE_META_KEY,
            self::CUSTOMER_ROLE_VALUE,
            $limit,
            $offset
        );
        $rows = $wpdb->get_col($sql);
        return array_map('intval', (array) $rows);
    }

    /**
     * Backfill همهٔ مشتری‌ها به‌صورت دسته‌ای (هر بار ۱۰۰‌تا).
     */
    public function sync_all_customers(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('tcs_sync_all_customers');

        // برای جلوگیری از تایم‌اوت روی تعداد بالا
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $batch_size = 100;
        $offset = 0;
        $totals = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0];

        do {
            $user_ids = $this->fetch_customer_ids($offset, $batch_size);

            if (empty($user_ids)) {
                break;
            }

            $items = [];
            foreach ($user_ids as $user_id) {
                $user = get_userdata($user_id);
                if (! $user) {
                    $totals['skipped']++;
                    continue;
                }
                $payload = $this->build_payload($user);
                if (empty($payload['mobile'])) {
                    $totals['skipped']++;
                    continue;
                }
                $items[] = $payload;
            }

            if (! empty($items)) {
                $result = TCS_API_Client::post('customers/batch', ['items' => $items]);

                if (! empty($result['ok']) && isset($result['body'])) {
                    $totals['total']   += (int) ($result['body']['total']   ?? count($items));
                    $totals['created'] += (int) ($result['body']['created'] ?? 0);
                    $totals['updated'] += (int) ($result['body']['updated'] ?? 0);
                    $totals['errors']  += isset($result['body']['errors']) ? count((array) $result['body']['errors']) : 0;
                } else {
                    // اگر دسته شکست خورد، کل دسته را روی صف می‌گذاریم تا cron بعداً تلاش کند.
                    TCS_Sync_Queue::push('customers/batch', ['items' => $items], $result['error'] ?? '');
                    $totals['errors'] += count($items);
                }
            }

            $offset += $batch_size;
        } while (count($user_ids) === $batch_size);

        $msg = sprintf(
            '✅ Backfill مشتری‌ها انجام شد. کل: %d، ایجادشده: %d، به‌روزشده: %d، رد: %d، خطا: %d.',
            $totals['total'],
            $totals['created'],
            $totals['updated'],
            $totals['skipped'],
            $totals['errors']
        );

        $url = add_query_arg(
            ['tcs_test' => $msg, 'tcs_ok' => $totals['errors'] === 0 ? 1 : 0],
            admin_url('options-general.php?page=tcs-settings')
        );
        wp_safe_redirect($url);
        exit;
    }

    /**
     * نمایش باکس «سینک کامل مشتری‌ها» زیر فرم تنظیمات.
     */
    public function render_box(): void
    {
        $customer_count = $this->count_customers();
        ?>
        <hr>
        <h2>سینک مشتری‌ها</h2>
        <p>
            تعداد کل مشتری‌ها (role=customer): <strong><?php echo number_format_i18n($customer_count); ?></strong>
        </p>
        <p class="description">
            از این به بعد هر بار که مشتری‌ای ساخته یا ویرایش شود، به‌صورت خودکار سینک می‌شود.
            برای ارسال یک‌بارهٔ همهٔ مشتری‌های فعلی، دکمهٔ زیر را بزنید (ممکن است چند دقیقه طول بکشد).
        </p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              onsubmit="return confirm('آیا از سینک کامل همهٔ مشتری‌ها مطمئنید؟ این عمل ممکن است چند دقیقه طول بکشد.');">
            <?php wp_nonce_field('tcs_sync_all_customers'); ?>
            <input type="hidden" name="action" value="tcs_sync_all_customers">
            <?php submit_button('سینک کامل همهٔ مشتری‌ها', 'primary', 'submit', false); ?>
        </form>
        <?php
    }
}
