<?php
/**
 * سینک تکنسین‌ها (کاربران WP با meta role=technician) به پنل لاراول.
 *
 * مرجع متاهای WP: libs/technician.php
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Technician_Sync
{
    private const ROLE_META_KEY = 'role';
    private const TECH_ROLE_VALUE = 'technician';

    /**
     * متاهای اختصاصی تکنسین — حضور یکی از این‌ها به این معناست که
     * کاربر «تکنسین است یا بوده» حتی اگر فعلاً role یا role-meta نداشته
     * باشد. این لیست باعث می‌شود تکنسین‌های قدیمی که role از آن‌ها
     * حذف شده ولی همچنان در سفارش‌ها به‌عنوان technician رفرنس می‌شوند
     * هم سینک شوند.
     */
    private const TECH_DISCRIMINATOR_META_KEYS = [
        'firstname_tech',
        'technician_id',
    ];

    /** متاهایی که تغییرشان باید سینک را تریگر کند. */
    private const META_KEYS = [
        'role', 'first_name', 'firstname_tech', 'technician_id', 'national_code',
        'mobile', 'phone', 'phone_force', 'address', 'description', 'percent',
        'max_order', 'max_price', 'status', 'type_tech', 'cart_img', 'province',
        'specialty', 'ready_for_derliver', 'type_of_calc_tech', 'tech_per_of_all',
        'img_Personal',
    ];

    public function __construct()
    {
        // hookهای real-time
        add_action('user_register', [$this, 'on_user_register'], 10, 1);
        add_action('profile_update', [$this, 'on_profile_update'], 10, 1);
        add_action('updated_user_meta', [$this, 'on_meta_updated'], 10, 4);
        add_action('added_user_meta', [$this, 'on_meta_updated'], 10, 4);

        // backfill با AJAX (دسته‌ای ۵۰‌تایی، در مرورگر تا پایان loop می‌خورد)
        add_action('wp_ajax_tcs_sync_technicians_batch', [$this, 'ajax_sync_batch']);

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

    public function on_meta_updated($meta_id, $object_id, $meta_key, $_meta_value): void
    {
        if (! in_array($meta_key, self::META_KEYS, true)) {
            return;
        }
        $this->sync_user((int) $object_id);
    }

    /**
     * بررسی این‌که آیا یک کاربر تکنسین است یا بوده. شامل سه مسیر:
     *  1) usermeta.role == 'technician'
     *  2) WP role list شامل 'technician'
     *  3) داشتن یکی از متاهای اختصاصی تکنسین — برای کاربرانی که role
     *     از آن‌ها حذف شده ولی هنوز رکورد تکنسینی دارند و در سفارش‌های
     *     قدیمی استفاده شده‌اند.
     */
    protected function is_technician(int $user_id): bool
    {
        $role_meta = get_user_meta($user_id, self::ROLE_META_KEY, true);
        if ($role_meta === self::TECH_ROLE_VALUE) {
            return true;
        }

        $user = get_userdata($user_id);
        if ($user && in_array(self::TECH_ROLE_VALUE, (array) $user->roles, true)) {
            return true;
        }

        // legacy: کاربر سابقاً تکنسین بوده — متاهای اختصاصی هنوز هست
        foreach (self::TECH_DISCRIMINATOR_META_KEYS as $key) {
            $val = get_user_meta($user_id, $key, true);
            if (is_string($val) && trim($val) !== '') {
                return true;
            }
        }

        return false;
    }

    public function sync_user(int $user_id): ?array
    {
        if (! $this->is_technician($user_id)) {
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

        return TCS_API_Client::send_or_queue('technician', $payload);
    }

    /**
     * payload یک تکنسین را بر اساس متاهای WP می‌سازد.
     * نام فیلدها دقیقاً مطابق libs/technician.php سمت WP است.
     */
    protected function build_payload(\WP_User $user): array
    {
        $get = function (string $key) use ($user) {
            $v = get_user_meta($user->ID, $key, true);
            $v = is_string($v) ? trim($v) : $v;
            return $v === '' ? null : $v;
        };

        $mobile = $get('mobile');
        if (! $mobile) {
            // fallback به user_login (بسیاری CRMها موبایل را user_login می‌گذارند)
            $mobile = trim((string) $user->user_login) ?: null;
        }

        return [
            'wp_id'            => (int) $user->ID,
            'mobile'           => $mobile,
            'first_name'       => $get('first_name'),
            'firstname_tech'   => $get('firstname_tech'),
            'technician_id'    => $get('technician_id'),
            'national_code'    => $get('national_code'),
            'phone'            => $get('phone'),
            'phone_force'      => $get('phone_force'),
            'province'         => $get('province'),
            'address'          => $get('address'),
            'specialty'        => $get('specialty'),
            'type_tech'        => $get('type_tech'),
            'description'      => $get('description'),
            'img_personal'     => $get('img_Personal'), // در WP با P بزرگ ذخیره شده
            'cart_img'         => $get('cart_img'),
            'percent'          => $this->intOrNull($get('percent')),
            'tech_per_of_all'  => $this->intOrNull($get('tech_per_of_all')),
            'max_order'        => $this->intOrNull($get('max_order')),
            'max_price'        => $this->intOrNull($get('max_price')),
            'type_of_calc_tech' => $get('type_of_calc_tech'),
            'status'           => $get('status'),
            'ready_for_derliver' => $this->boolOrNull($get('ready_for_derliver')),
        ];
    }

    private function intOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        return (int) $v;
    }

    private function boolOrNull($v): ?bool
    {
        if ($v === null) return null;
        if (is_bool($v)) return $v;
        $s = strtolower(trim((string) $v));
        if ($s === '' || $s === '0' || $s === 'false' || $s === 'no' || $s === 'off') return false;
        return true;
    }

    /**
     * شمارش کل تکنسین‌ها بر اساس متای role=technician.
     */
    /**
     * SQL برای DISTINCT user_id از همهٔ شاخص‌های تکنسین:
     *  - role meta = 'technician'
     *  - یا داشتن هر یک از متاهای اختصاصی (firstname_tech, technician_id)
     *
     * این تضمین می‌کند تکنسین‌های قدیمی که فقط role‌شان حذف شده هم
     * در batch backfill شناسایی شوند.
     */
    protected function technician_ids_subquery_sql(\wpdb $wpdb): string
    {
        $extra_keys = array_map(
            fn ($k) => "'" . esc_sql($k) . "'",
            self::TECH_DISCRIMINATOR_META_KEYS
        );
        $extra_keys_in = implode(',', $extra_keys);

        $role_key = esc_sql(self::ROLE_META_KEY);
        $role_val = esc_sql(self::TECH_ROLE_VALUE);

        return "
            SELECT DISTINCT user_id FROM {$wpdb->usermeta}
            WHERE (meta_key = '{$role_key}' AND meta_value = '{$role_val}')
               OR (meta_key IN ({$extra_keys_in}) AND meta_value <> '' AND meta_value IS NOT NULL)
        ";
    }

    protected function count_technicians(): int
    {
        global $wpdb;
        $sub = $this->technician_ids_subquery_sql($wpdb);
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM ({$sub}) AS t");
    }

    /**
     * @return int[]
     */
    protected function fetch_technician_ids(int $offset, int $limit): array
    {
        global $wpdb;
        $sub = $this->technician_ids_subquery_sql($wpdb);
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM ({$sub}) AS t
             ORDER BY user_id ASC
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
        return array_map('intval', (array) $rows);
    }

    public function ajax_sync_batch(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
        }
        check_ajax_referer('tcs_sync_technicians_batch', '_ajax_nonce');

        $offset     = max(0, intval($_POST['offset'] ?? 0));
        $batch_size = max(1, min(200, intval($_POST['batch_size'] ?? 50)));

        @set_time_limit(120);

        $user_ids = $this->fetch_technician_ids($offset, $batch_size);

        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0];

        if (empty($user_ids)) {
            wp_send_json_success([
                'batch'             => $stats,
                'next_offset'       => $offset,
                'done'              => true,
                'total_technicians' => $this->count_technicians(),
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

        $detail = ['errors' => [], 'http_status' => null, 'http_error' => null];

        if (! empty($items)) {
            $result = TCS_API_Client::post('technicians/batch', ['items' => $items]);

            if (! empty($result['ok']) && isset($result['body'])) {
                $stats['total']   = (int) ($result['body']['total']   ?? count($items));
                $stats['created'] = (int) ($result['body']['created'] ?? 0);
                $stats['updated'] = (int) ($result['body']['updated'] ?? 0);
                $stats['errors']  = isset($result['body']['errors']) ? count((array) $result['body']['errors']) : 0;

                // detail تا ۵ خطای اول برای دیباگ سمت مرورگر
                $detail['errors'] = array_slice((array) ($result['body']['errors'] ?? []), 0, 5);
            } else {
                TCS_Sync_Queue::push('technicians/batch', ['items' => $items], $result['error'] ?? '');
                $stats['errors'] = count($items);
                $detail['http_status'] = $result['status'] ?? null;
                $detail['http_error']  = $result['error']  ?? null;
            }
        }

        $next_offset = $offset + $batch_size;
        $done = count($user_ids) < $batch_size;

        wp_send_json_success([
            'batch'             => $stats,
            'detail'            => $detail,
            'next_offset'       => $next_offset,
            'done'              => $done,
            'total_technicians' => $this->count_technicians(),
        ]);
    }

    public function render_box(): void
    {
        $count = $this->count_technicians();
        $nonce = wp_create_nonce('tcs_sync_technicians_batch');
        ?>
        <hr>
        <h2>سینک تکنسین‌ها</h2>
        <p>
            تعداد کل تکنسین‌ها (role=technician): <strong><?php echo number_format_i18n($count); ?></strong>
        </p>
        <p class="description">
            از این به بعد هر بار که تکنسینی ساخته یا ویرایش شود، به‌صورت خودکار سینک می‌شود.<br>
            برای ارسال یک‌بارهٔ همهٔ تکنسین‌های فعلی، دکمهٔ زیر را بزنید. ارسال در دسته‌های ۵۰‌تایی است؛ این صفحه را تا پایان باز نگه دارید.
        </p>

        <p>
            <button type="button" id="tcs-start-tech-backfill" class="button button-primary"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    data-total="<?php echo (int) $count; ?>"
                    <?php disabled($count === 0); ?>>
                شروع سینک کامل تکنسین‌ها
            </button>
            <button type="button" id="tcs-cancel-tech-backfill" class="button" style="display:none;">
                لغو
            </button>
            <span id="tcs-tech-progress-text" style="margin-inline-start:10px;font-weight:bold;"></span>
        </p>

        <progress id="tcs-tech-progress-bar" max="<?php echo (int) $count; ?>" value="0"
                  style="width:100%;height:25px;display:none;"></progress>

        <div id="tcs-tech-log"
             style="display:none;max-height:300px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;margin-top:10px;font-family:Menlo,Consolas,monospace;font-size:12px;direction:ltr;text-align:left;"></div>

        <script>
        (function ($) {
            var $btn    = $('#tcs-start-tech-backfill');
            var $cancel = $('#tcs-cancel-tech-backfill');
            var $bar    = $('#tcs-tech-progress-bar');
            var $log    = $('#tcs-tech-log');
            var $text   = $('#tcs-tech-progress-text');
            var batchSize = 50;
            var delayMs   = 150;
            var state = null;

            function log(msg) {
                var ts = new Date().toLocaleTimeString();
                $log.prepend('<div>[' + ts + '] ' + msg + '</div>');
            }

            function fmt(t) {
                return 'created=' + t.created + ' updated=' + t.updated +
                       ' skipped=' + t.skipped + ' errors=' + t.errors;
            }

            function tick() {
                if (state.cancelled) { finish('🛑 توسط کاربر لغو شد. ' + fmt(state.totals)); return; }
                $.post(ajaxurl, {
                    action: 'tcs_sync_technicians_batch',
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
                    var det = d.detail || {};
                    state.totals.total   += (b.total   || 0);
                    state.totals.created += (b.created || 0);
                    state.totals.updated += (b.updated || 0);
                    state.totals.skipped += (b.skipped || 0);
                    state.totals.errors  += (b.errors  || 0);
                    state.offset = d.next_offset;
                    var done = Math.min(state.offset, state.total);
                    $bar.val(done);
                    $text.text(done + ' / ' + state.total + ' — ' + fmt(state.totals));
                    log('offset=' + state.offset + ' +created=' + (b.created || 0) +
                        ' +updated=' + (b.updated || 0) + ' +errors=' + (b.errors || 0));

                    if (det.errors && det.errors.length) {
                        det.errors.forEach(function (e) {
                            log('  ✗ wp_id=' + e.wp_id + ' mobile=' + (e.mobile || '-') +
                                ' → ' + e.error);
                        });
                    }
                    if (det.http_error) {
                        log('  ⚠ HTTP ' + (det.http_status || '?') + ': ' + det.http_error);
                    }

                    if (d.done) {
                        finish('✅ پایان یافت. ' + fmt(state.totals));
                    } else {
                        setTimeout(tick, delayMs);
                    }
                }).fail(function (xhr) {
                    finish('❌ HTTP ' + xhr.status + ' — می‌توانید دوباره بزنید (سینک idempotent است).');
                });
            }

            function finish(msg) {
                log(msg);
                $btn.prop('disabled', false).text('شروع سینک کامل تکنسین‌ها');
                $cancel.hide();
                state = null;
            }

            $btn.on('click', function () {
                var total = parseInt($btn.data('total'), 10) || 0;
                if (total === 0) return;
                if (!confirm('شروع سینک ' + total + ' تکنسین در دسته‌های ' + batchSize + '‌تایی؟')) return;
                state = {
                    offset: 0, total: total, cancelled: false,
                    totals: { total:0, created:0, updated:0, skipped:0, errors:0 }
                };
                $bar.val(0).attr('max', total).show();
                $log.empty().show();
                $text.text('0 / ' + total);
                $btn.prop('disabled', true).text('در حال سینک...');
                $cancel.show();
                log('🚀 شروع backfill تکنسین‌ها — کل: ' + total);
                tick();
            });

            $cancel.on('click', function () {
                if (state) { state.cancelled = true; log('در حال لغو پس از پایان دستهٔ جاری...'); }
            });
        })(jQuery);
        </script>
        <?php
    }
}
