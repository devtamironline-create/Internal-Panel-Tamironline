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

        // backfill با AJAX (دسته‌ای ۵۰‌تایی، در مرورگر تا پایان loop می‌خورد)
        add_action('wp_ajax_tcs_sync_customers_batch', [$this, 'ajax_sync_batch']);

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
     * Endpoint AJAX برای پردازش یک دسته از مشتری‌ها.
     *
     * مرورگر این endpoint را در حلقه فراخوانی می‌کند: هر بار با یک offset
     * جدید و یک batch_size کوچک (پیش‌فرض ۵۰). این طور هیچ ریکوست تنهایی
     * طولانی نمی‌شود و روی شِرد هاستینگ هم پایدار است.
     */
    public function ajax_sync_batch(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
        }
        check_ajax_referer('tcs_sync_customers_batch', '_ajax_nonce');

        $offset     = max(0, intval($_POST['offset'] ?? 0));
        $batch_size = max(1, min(200, intval($_POST['batch_size'] ?? 50)));

        @set_time_limit(120);

        $user_ids = $this->fetch_customer_ids($offset, $batch_size);

        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0];

        if (empty($user_ids)) {
            wp_send_json_success([
                'batch'           => $stats,
                'next_offset'     => $offset,
                'done'            => true,
                'total_customers' => $this->count_customers(),
            ]);
        }

        $items = [];
        foreach ($user_ids as $uid) {
            $user = get_userdata($uid);
            if (! $user) {
                $stats['skipped']++;
                continue;
            }
            $payload = $this->build_payload($user);
            if (empty($payload['mobile'])) {
                $stats['skipped']++;
                continue;
            }
            $items[] = $payload;
        }

        if (! empty($items)) {
            $result = TCS_API_Client::post('customers/batch', ['items' => $items]);

            if (! empty($result['ok']) && isset($result['body'])) {
                $stats['total']   = (int) ($result['body']['total']   ?? count($items));
                $stats['created'] = (int) ($result['body']['created'] ?? 0);
                $stats['updated'] = (int) ($result['body']['updated'] ?? 0);
                $stats['errors']  = isset($result['body']['errors']) ? count((array) $result['body']['errors']) : 0;
            } else {
                // دسته را روی صف می‌گذاریم تا cron بعداً تلاش کند.
                TCS_Sync_Queue::push('customers/batch', ['items' => $items], $result['error'] ?? '');
                $stats['errors'] = count($items);
            }
        }

        $next_offset = $offset + $batch_size;
        // اگر این دسته کم‌تر از batch_size بود، یعنی به انتها رسیدیم.
        $done = count($user_ids) < $batch_size;

        wp_send_json_success([
            'batch'           => $stats,
            'next_offset'     => $next_offset,
            'done'            => $done,
            'total_customers' => $this->count_customers(),
        ]);
    }

    /**
     * نمایش باکس «سینک کامل مشتری‌ها» زیر فرم تنظیمات.
     * Backfill با AJAX دسته‌ای انجام می‌شود تا روی هاستینگ شِرد timeout نخورد.
     */
    public function render_box(): void
    {
        $customer_count = $this->count_customers();
        $nonce          = wp_create_nonce('tcs_sync_customers_batch');
        ?>
        <hr>
        <h2>سینک مشتری‌ها</h2>
        <p>
            تعداد کل مشتری‌ها (role=customer): <strong><?php echo number_format_i18n($customer_count); ?></strong>
        </p>
        <p class="description">
            از این به بعد هر بار که مشتری‌ای ساخته یا ویرایش شود، به‌صورت خودکار سینک می‌شود.<br>
            برای ارسال یک‌بارهٔ همهٔ مشتری‌های فعلی، دکمهٔ زیر را بزنید. ارسال به‌صورت دسته‌های ۵۰‌تایی انجام می‌شود؛ این صفحه را تا پایان باز نگه دارید.
        </p>

        <p>
            <button type="button" id="tcs-start-backfill" class="button button-primary"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    data-total="<?php echo (int) $customer_count; ?>"
                    <?php disabled($customer_count === 0); ?>>
                شروع سینک کامل
            </button>
            <button type="button" id="tcs-cancel-backfill" class="button" style="display:none;">
                لغو
            </button>
            <span id="tcs-progress-text" style="margin-inline-start:10px;font-weight:bold;"></span>
        </p>

        <progress id="tcs-progress-bar" max="<?php echo (int) $customer_count; ?>" value="0"
                  style="width:100%;height:25px;display:none;"></progress>

        <div id="tcs-log"
             style="display:none;max-height:300px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;margin-top:10px;font-family:Menlo,Consolas,monospace;font-size:12px;direction:ltr;text-align:left;"></div>

        <script>
        (function ($) {
            var $btn        = $('#tcs-start-backfill');
            var $cancel     = $('#tcs-cancel-backfill');
            var $bar        = $('#tcs-progress-bar');
            var $log        = $('#tcs-log');
            var $text       = $('#tcs-progress-text');
            var batchSize   = 50;
            var delayMs     = 150;

            var state = null;

            function log(msg) {
                var ts = new Date().toLocaleTimeString();
                $log.prepend('<div>[' + ts + '] ' + msg + '</div>');
            }

            function fmtTotals(t) {
                return 'created=' + t.created + ' updated=' + t.updated +
                       ' skipped=' + t.skipped + ' errors=' + t.errors;
            }

            function tick() {
                if (state.cancelled) {
                    finish('🛑 توسط کاربر لغو شد. ' + fmtTotals(state.totals));
                    return;
                }

                $.post(ajaxurl, {
                    action: 'tcs_sync_customers_batch',
                    _ajax_nonce: $btn.data('nonce'),
                    offset: state.offset,
                    batch_size: batchSize
                }).done(function (res) {
                    if (!res || !res.success) {
                        var em = (res && res.data && res.data.message) ? res.data.message : 'پاسخ نامعتبر';
                        finish('❌ ' + em);
                        return;
                    }
                    var d = res.data;
                    var b = d.batch || {};
                    state.totals.total   += (b.total   || 0);
                    state.totals.created += (b.created || 0);
                    state.totals.updated += (b.updated || 0);
                    state.totals.skipped += (b.skipped || 0);
                    state.totals.errors  += (b.errors  || 0);
                    state.offset = d.next_offset;

                    var done = Math.min(state.offset, state.totalCustomers);
                    $bar.val(done);
                    $text.text(done + ' / ' + state.totalCustomers + ' — ' + fmtTotals(state.totals));
                    log('offset=' + state.offset + ' +created=' + (b.created || 0) +
                        ' +updated=' + (b.updated || 0) + ' +errors=' + (b.errors || 0));

                    if (d.done) {
                        finish('✅ پایان یافت. ' + fmtTotals(state.totals));
                    } else {
                        setTimeout(tick, delayMs);
                    }
                }).fail(function (xhr) {
                    finish('❌ خطای شبکه: HTTP ' + xhr.status + ' — می‌توانید با همین دکمه ادامه دهید (سینک idempotent است).');
                });
            }

            function finish(msg) {
                log(msg);
                $btn.prop('disabled', false).text('شروع سینک کامل');
                $cancel.hide();
                state = null;
            }

            $btn.on('click', function () {
                var total = parseInt($btn.data('total'), 10) || 0;
                if (total === 0) { return; }
                if (!confirm('شروع سینک ' + total + ' مشتری در دسته‌های ' + batchSize + '‌تایی؟')) {
                    return;
                }
                state = {
                    offset: 0,
                    totalCustomers: total,
                    cancelled: false,
                    totals: { total:0, created:0, updated:0, skipped:0, errors:0 }
                };
                $bar.val(0).attr('max', total).show();
                $log.empty().show();
                $text.text('0 / ' + total);
                $btn.prop('disabled', true).text('در حال سینک...');
                $cancel.show();
                log('🚀 شروع backfill — کل: ' + total);
                tick();
            });

            $cancel.on('click', function () {
                if (state) {
                    state.cancelled = true;
                    log('در حال لغو پس از پایان دستهٔ جاری...');
                }
            });
        })(jQuery);
        </script>
        <?php
    }
}
