<?php
/**
 * سینک سفارش‌ها (post_type=orders) به پنل لاراول.
 *
 * مرجع متاهای WP: libs/order.php و libs/OrderCPT.php.
 *
 * - Real-time: save_post_orders + updated_post_meta + set_object_terms
 *   (تغییر تاکسونومی هم تریگر می‌کند چون brand/device/state/city
 *   روی wp_term_relationships ذخیره می‌شوند نه postmeta).
 * - Backfill: AJAX دسته‌ای ۵۰‌تایی، تا پایان loop می‌خورد.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Order_Sync
{
    /** taxonomyهایی که تغییرشان باید سینک سفارش را تریگر کند. */
    private const ORDER_TAXONOMIES = ['brand', 'device', 'state', 'city'];

    public function __construct()
    {
        // real-time
        add_action('save_post_orders', [$this, 'on_save_post'], 20, 3);
        add_action('updated_post_meta', [$this, 'on_meta_change'], 20, 4);
        add_action('added_post_meta',   [$this, 'on_meta_change'], 20, 4);
        add_action('set_object_terms',  [$this, 'on_terms_set'],   20, 6);

        // برای پوشش تغییر post_status (publish/draft/...) که از مسیر
        // save_post رد نمی‌شود اما هنوز روی نمایش سفارش اثر دارد.
        add_action('transition_post_status', [$this, 'on_status_transition'], 20, 3);

        // backfill
        add_action('wp_ajax_tcs_sync_orders_batch', [$this, 'ajax_sync_batch']);
        add_action('tcs_settings_after_form', [$this, 'render_box']);
    }

    public function on_save_post($post_id, $post, $update): void
    {
        if (wp_is_post_revision($post_id)) {
            return;
        }
        if (! $post instanceof WP_Post || $post->post_type !== 'orders') {
            return;
        }
        if (in_array($post->post_status, ['auto-draft', 'trash', 'inherit'], true)) {
            return;
        }
        $this->sync_post((int) $post_id);
    }

    public function on_meta_change($meta_id, $object_id, $meta_key, $_value): void
    {
        if (get_post_type($object_id) !== 'orders') {
            return;
        }
        $this->sync_post((int) $object_id);
    }

    /**
     * هر تغییر post_status روی orders را سینک می‌کند — مثلاً انتشار یک
     * draft، انتقال به trash و برگشت، یا تغییر private<->publish. این
     * هوک معادل save_post نیست و بعضی تغییرات وضعیت را زودتر می‌گیرد.
     */
    public function on_status_transition($new_status, $old_status, $post): void
    {
        if (! $post instanceof WP_Post || $post->post_type !== 'orders') {
            return;
        }
        if (in_array($new_status, ['auto-draft', 'trash', 'inherit'], true)) {
            return;
        }
        if ($new_status === $old_status) {
            return;
        }
        $this->sync_post((int) $post->ID);
    }

    public function on_terms_set($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids): void
    {
        if (! in_array($taxonomy, self::ORDER_TAXONOMIES, true)) {
            return;
        }
        if (get_post_type($object_id) !== 'orders') {
            return;
        }
        $this->sync_post((int) $object_id);
    }

    /** سینک یک سفارش (post). در نبود مشتری، رد می‌شود. */
    public function sync_post(int $post_id): ?array
    {
        $post = get_post($post_id);
        if (! $post || $post->post_type !== 'orders') {
            return null;
        }
        if (in_array($post->post_status, ['auto-draft', 'trash', 'inherit'], true)) {
            return null;
        }

        $payload = $this->build_payload($post);
        if (! $payload) {
            return null;
        }

        return TCS_API_Client::send_or_queue('order', $payload);
    }

    /**
     * ساخت payload از روی postmeta و taxonomyهای یک سفارش.
     * در صورت نبود customer (که FK اجباری است) null برمی‌گرداند.
     */
    protected function build_payload(WP_Post $post): ?array
    {
        $get = function ($key) use ($post) {
            return get_post_meta($post->ID, $key, true);
        };

        $customer_wp_id = (int) $get('customer');
        if ($customer_wp_id <= 0) {
            return null;
        }
        $technician_wp_id = (int) $get('technician');

        $payload = [
            'wp_id' => (int) $post->ID,
            'customer_wp_id' => $customer_wp_id,
            'technician_wp_id' => $technician_wp_id ?: null,

            // تاریخ ثبت سفارش در WP — برای حفظ تاریخچهٔ واقعی در پنل
            // به‌جای زمان import. post_date با timezone سایت ست شده.
            'post_date' => $post->post_date,

            // taxonomy term_ids
            'brand_wp_id'  => $this->first_term_id($post->ID, 'brand'),
            'device_wp_id' => $this->first_term_id($post->ID, 'device'),
            'state_wp_id'  => $this->first_term_id($post->ID, 'state'),
            'city_wp_id'   => $this->first_term_id($post->ID, 'city'),

            // پایه
            'subscription' => $this->meta_int($get('subscription')),
            'introduction' => $this->meta_str($get('introduction')),
            'mobile'       => $this->meta_str($get('mobile')),
            'phone'        => $this->meta_str($get('phone')),
            'address'      => $this->meta_str($get('address')),
            // objection در WP postmeta آرایه‌ای از عنوان‌های ایراد است
            // (مشتری می‌تواند چند ایراد انتخاب کند). با meta_str() همیشه
            // null می‌رسید چون ورودی آرایه بود.
            'objection'             => $this->meta_array($get('objection')),
            'objection_description' => $this->meta_str($get('objection_description')),

            // وضعیت + پرچم‌های متن
            'status'                => $this->meta_int($get('status')),
            'order_type'            => $this->meta_str($get('order_type')),
            'return_type'           => $this->meta_str($get('return_type')),
            'return_description'    => $this->meta_str($get('return_description')),
            'status_internal_order' => $this->meta_str($get('status_internal_order')),
            'qc_status'             => $this->meta_str($get('qc_status')),

            // پرچم‌های boolean
            'send_technician'   => $this->meta_bool($get('send_technician')),
            'send_sms_tec'      => $this->meta_bool($get('send_sms_tec')),
            'send_sms_customer' => $this->meta_bool($get('send_sms_customer')),
            'save_as_draft'     => $this->meta_bool($get('save_as_draft')),
            'have_invoice'      => $this->meta_bool($get('have_invoice')),
            'happy_call'        => $this->meta_bool($get('happyCall')),
            'hc_customer'       => $this->meta_bool($get('hc_customer')),
            'hc_tech'           => $this->meta_bool($get('hc_tech')),
            'finish_order'      => $this->meta_bool($get('finish_order')),
            'finish_order_sh'   => $this->meta_bool($get('finish_order_sh')),

            // مالی
            'customer_price'   => $this->price_int($get('customer_price')),
            'buy_price'        => $this->price_int($get('buy_price')),
            'price_customer'   => $this->price_int($get('price_customer')),
            'cost_price'       => $this->price_int($get('cost_price')),
            'total_invoice'    => $this->price_int($get('total_invoice')),
            'negative_invoice' => $this->price_int($get('negative_invoice')),
            'price_return'     => $this->price_int($get('price_return')),
            'type_of_send_invoice'  => $this->meta_str($get('type_of_send_invoice')),
            'invoice_email'         => $this->meta_str($get('invoice_email')),
            'invoice_paper'         => $this->meta_str($get('invoice_paper')),
            'invoice_descripotion'  => $this->meta_str($get('invoice_descripotion')),
            'hire'           => $this->price_int($get('hire')),
            'transportation' => $this->price_int($get('transportation')),
            'discount'       => $this->price_int($get('discount')),

            // تکنسین — متن و آرایه
            'description_tech'  => $this->meta_str($get('description_tech')),
            'description_tech1' => $this->meta_str($get('description_tech1')),
            'description_tech2' => $this->meta_str($get('description_tech2')),
            'piece_list'           => $this->meta_array($get('piece_list')),
            'customer_price_list'  => $this->meta_array($get('customer_price_list')),
            'buy_price_list'       => $this->meta_array($get('buy_price_list')),
            'device_img1'          => $this->resolve_image_url($get('device_img1')),
            'device_image_input'   => $this->resolve_image_url($get('device_image_input')),

            // happy call — جفت‌های key/value در WP به یک نگاشت تبدیل می‌شوند
            'hc_customer_data' => $this->collect_kv($post->ID, 'hc_customer_key', 'hc_customer_value'),
            'hc_tech_data'     => $this->collect_kv($post->ID, 'hc_tech_key',     'hc_tech_value'),

            // logging — این سه postmeta در WP همیشه آرایهٔ چندرویدادی
            // هستند (subject/content/author/date/status). meta_str وقتی
            // ورودی آرایه باشد null برمی‌گرداند و باعث می‌شود لاگ هرگز
            // به پنل نرسد. باید meta_array استفاده کنیم.
            'order_description_content' => $this->meta_array($get('order_description_content')),
            'order_note_content'        => $this->meta_array($get('order_note_content')),
            'log_return'                => $this->meta_array($get('log_return')),

            // زمان مراجعه — قالب tamironline-v3 از scheduled_date و
            // scheduled_time استفاده می‌کند. بقیه fallback ها برای
            // نسخه‌های قدیمی‌تر CRM-Hostlino نگه داشته شده.
            'visit_date'  => $this->meta_str($get('scheduled_date'))
                          ?? $this->meta_str($get('visit_date'))
                          ?? $this->meta_str($get('date_meet'))
                          ?? $this->meta_str($get('day_meet'))
                          ?? $this->meta_str($get('day'))
                          ?? $this->meta_str($get('morooja_date'))
                          ?? $this->meta_str($get('appointment_date')),
            'visit_time'  => $this->meta_str($get('scheduled_time'))
                          ?? $this->meta_str($get('visit_time'))
                          ?? $this->meta_str($get('time_meet'))
                          ?? $this->meta_str($get('hour_meet'))
                          ?? $this->meta_str($get('hour'))
                          ?? $this->meta_str($get('morooja_time'))
                          ?? $this->meta_str($get('appointment_time'))
                          ?? $this->meta_str($get('time')),

            // ─── DEBUG: همهٔ متاهای پست را بفرست ───
            // این کمک می‌کند کلید واقعی زمان مراجعه را در نصب کاربر پیدا
            // کنیم. سمت Laravel در لاگ ذخیره می‌شود تا قابل بررسی باشد.
            // وقتی کلید درست شناسایی شد این بلاک حذف خواهد شد.
            '_debug_all_meta' => array_map(
                fn($v) => is_array($v) && count($v) === 1 ? $v[0] : $v,
                get_post_meta($post->ID)
            ),
        ];

        // null/'' را از payload خارج می‌کنیم (false و 0 و [] حفظ می‌شوند)
        return array_filter($payload, function ($v) {
            return $v !== null && $v !== '';
        });
    }

    public function ajax_sync_batch(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
        }
        check_ajax_referer('tcs_sync_orders_batch', '_ajax_nonce');

        $offset     = max(0, intval($_POST['offset'] ?? 0));
        $batch_size = max(1, min(100, intval($_POST['batch_size'] ?? 50)));

        @set_time_limit(120);

        $total = $this->count_orders();

        $posts = get_posts([
            'post_type'      => 'orders',
            'post_status'    => ['publish', 'pending', 'draft', 'private', 'future'],
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'numberposts'    => $batch_size,
            'offset'         => $offset,
            'suppress_filters' => true,
        ]);

        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0];

        if (empty($posts)) {
            wp_send_json_success([
                'batch'       => $stats,
                'next_offset' => $offset,
                'done'        => true,
                'total_orders' => $total,
            ]);
        }

        $items = [];
        foreach ($posts as $p) {
            $payload = $this->build_payload($p);
            if (! $payload) {
                $stats['skipped']++;
                continue;
            }
            $items[] = $payload;
        }

        if (! empty($items)) {
            $result = TCS_API_Client::post('orders/batch', ['items' => $items]);

            if (! empty($result['ok']) && isset($result['body'])) {
                $stats['total']   = (int) ($result['body']['total']   ?? count($items));
                $stats['created'] = (int) ($result['body']['created'] ?? 0);
                $stats['updated'] = (int) ($result['body']['updated'] ?? 0);
                $stats['errors']  = isset($result['body']['errors']) ? count((array) $result['body']['errors']) : 0;
            } else {
                TCS_Sync_Queue::push('orders/batch', ['items' => $items], $result['error'] ?? '');
                $stats['errors'] = count($items);
            }
        }

        $next_offset = $offset + $batch_size;
        $done        = count($posts) < $batch_size;

        wp_send_json_success([
            'batch'        => $stats,
            'next_offset'  => $next_offset,
            'done'         => $done,
            'total_orders' => $total,
        ]);
    }

    public function render_box(): void
    {
        $total = $this->count_orders();
        $nonce = wp_create_nonce('tcs_sync_orders_batch');
        ?>
        <hr>
        <h2>سینک سفارش‌ها</h2>
        <p>تعداد کل سفارش‌ها: <strong><?php echo number_format_i18n($total); ?></strong></p>
        <p class="description">
            از این به بعد هر بار که سفارشی ساخته/ویرایش شود (شامل تغییر متا یا تاکسونومی)
            به‌صورت خودکار سینک می‌شود.<br>
            برای ارسال یک‌بارهٔ همهٔ سفارش‌های فعلی، دکمهٔ زیر را بزنید. ارسال در دسته‌های ۵۰‌تایی است
            و این صفحه باید تا پایان باز بماند.
        </p>

        <p>
            <button type="button" id="tcs-start-orders" class="button button-primary"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    data-total="<?php echo (int) $total; ?>"
                    <?php disabled($total === 0); ?>>
                شروع سینک کامل سفارش‌ها
            </button>
            <button type="button" id="tcs-cancel-orders" class="button" style="display:none;">لغو</button>
            <span id="tcs-orders-progress" style="margin-inline-start:10px;font-weight:bold;"></span>
        </p>

        <progress id="tcs-orders-bar" max="<?php echo (int) $total; ?>" value="0"
                  style="width:100%;height:25px;display:none;"></progress>

        <div id="tcs-orders-log"
             style="display:none;max-height:300px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;margin-top:10px;font-family:Menlo,Consolas,monospace;font-size:12px;direction:ltr;text-align:left;"></div>

        <script>
        (function ($) {
            var $btn    = $('#tcs-start-orders');
            var $cancel = $('#tcs-cancel-orders');
            var $bar    = $('#tcs-orders-bar');
            var $log    = $('#tcs-orders-log');
            var $msg    = $('#tcs-orders-progress');
            var batchSize = 50;
            var delayMs   = 200;
            var state = null;

            function log(s) {
                var ts = new Date().toLocaleTimeString();
                $log.prepend('<div>[' + ts + '] ' + s + '</div>');
            }
            function fmt(t) {
                return 'created=' + t.created + ' updated=' + t.updated +
                       ' skipped=' + t.skipped + ' errors=' + t.errors;
            }
            function tick() {
                if (state.cancelled) {
                    finish('🛑 لغو شد. ' + fmt(state.totals));
                    return;
                }
                $.post(ajaxurl, {
                    action: 'tcs_sync_orders_batch',
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
                    state.totals.created += (b.created || 0);
                    state.totals.updated += (b.updated || 0);
                    state.totals.skipped += (b.skipped || 0);
                    state.totals.errors  += (b.errors  || 0);
                    state.offset = d.next_offset;

                    var done = Math.min(state.offset, state.total);
                    $bar.val(done);
                    $msg.text(done + ' / ' + state.total + ' — ' + fmt(state.totals));
                    log('offset=' + state.offset + ' +created=' + (b.created || 0) +
                        ' +updated=' + (b.updated || 0) + ' +errors=' + (b.errors || 0) +
                        ' +skipped=' + (b.skipped || 0));

                    if (d.done) {
                        finish('✅ پایان یافت. ' + fmt(state.totals));
                    } else {
                        setTimeout(tick, delayMs);
                    }
                }).fail(function (xhr) {
                    finish('❌ خطای شبکه: HTTP ' + xhr.status + ' — می‌توانید با همین دکمه ادامه دهید (sync idempotent است).');
                });
            }
            function finish(msg) {
                log(msg);
                $btn.prop('disabled', false).text('شروع سینک کامل سفارش‌ها');
                $cancel.hide();
                state = null;
            }
            $btn.on('click', function () {
                var total = parseInt($btn.data('total'), 10) || 0;
                if (total === 0) return;
                if (!confirm('شروع سینک ' + total + ' سفارش در دسته‌های ' + batchSize + '‌تایی؟')) return;
                state = {
                    offset: 0,
                    total: total,
                    cancelled: false,
                    totals: { created:0, updated:0, skipped:0, errors:0 }
                };
                $bar.val(0).attr('max', total).show();
                $log.empty().show();
                $msg.text('0 / ' + total);
                $btn.prop('disabled', true).text('در حال سینک...');
                $cancel.show();
                log('🚀 شروع — کل: ' + total);
                tick();
            });
            $cancel.on('click', function () {
                if (state) { state.cancelled = true; log('در حال لغو پس از پایان دسته…'); }
            });
        })(jQuery);
        </script>
        <?php
    }

    // ─────────────────────── helpers ───────────────────────────────

    protected function count_orders(): int
    {
        $c = wp_count_posts('orders');
        if (! $c) {
            return 0;
        }
        $n = 0;
        foreach (['publish', 'pending', 'draft', 'private', 'future'] as $s) {
            $n += (int) ($c->{$s} ?? 0);
        }
        return $n;
    }

    protected function first_term_id(int $post_id, string $taxonomy): ?int
    {
        $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
        if (is_wp_error($terms) || empty($terms)) {
            return null;
        }
        return (int) reset($terms);
    }

    protected function meta_str($value): ?string
    {
        if (is_array($value)) {
            return null;
        }
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    protected function meta_int($value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (is_array($value)) {
            return null;
        }
        return is_numeric($value) ? (int) $value : null;
    }

    protected function meta_bool($value): bool
    {
        if (is_string($value)) {
            $v = trim(strtolower($value));
            if (in_array($v, ['true', '1', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array($v, ['false', '0', '', 'no', 'off'], true)) {
                return false;
            }
        }
        return (bool) $value;
    }

    /** قیمت می‌تواند با کاما/فاصله ذخیره شده باشد. */
    protected function price_int($value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $clean = str_replace([',', '،', ' '], '', (string) $value);
        return is_numeric($clean) ? (int) $clean : null;
    }

    /** تبدیل serialized/json/array به آرایه. در غیر این صورت []. */
    protected function meta_array($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            // JSON
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            // serialized
            if (preg_match('/^a:\d+:/', $value)) {
                $unsr = @unserialize($value);
                if (is_array($unsr)) {
                    return $unsr;
                }
            }
        }
        return [];
    }

    /** جمع‌آوری جفت کلید/مقدار از دو متای موازی به یک نگاشت. */
    protected function collect_kv(int $post_id, string $key_meta, string $val_meta): array
    {
        $keys_raw = get_post_meta($post_id, $key_meta);
        $vals_raw = get_post_meta($post_id, $val_meta);
        if (empty($keys_raw) || empty($vals_raw)) {
            return [];
        }

        // اگر تنها یک مقدار وجود دارد و آن خود آرایه است، unwrap
        $keys = (count($keys_raw) === 1 && is_array($keys_raw[0])) ? $keys_raw[0] : $keys_raw;
        $vals = (count($vals_raw) === 1 && is_array($vals_raw[0])) ? $vals_raw[0] : $vals_raw;

        $out = [];
        $count = min(count($keys), count($vals));
        for ($i = 0; $i < $count; $i++) {
            $k = trim((string) $keys[$i]);
            if ($k !== '') {
                $out[$k] = (string) $vals[$i];
            }
        }
        return $out;
    }

    /** اگر مقدار attachment ID بود به URL تبدیل می‌کند، در غیر این صورت همان URL. */
    protected function resolve_image_url($value): ?string
    {
        if (! $value) {
            return null;
        }
        if (is_numeric($value)) {
            $url = wp_get_attachment_url((int) $value);
            return $url ?: null;
        }
        if (is_string($value)) {
            $v = trim($value);
            if ($v === '') {
                return null;
            }
            if (strpos($v, 'http') === 0 || strpos($v, '/') === 0) {
                return $v;
            }
        }
        return null;
    }
}
