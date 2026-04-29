<?php
/**
 * سینک رکوردهای مالی (post_type=financial) به پنل لاراول.
 *
 * در WP CRM این post_type چندمنظوره است: همان پست بسته به متاهای
 * wallet/refid/reward_type/total_invoice یکی از این‌ها است:
 *  - فاکتور سفارش (order_id + total_invoice)
 *  - شارژ کیف‌پول (wallet=1)
 *  - جایزه/جریمه (reward_type)
 *
 * پلاگین همه را بدون تفکیک می‌فرستد؛ روتر سمت لاراول
 * (SyncFinancialController) نوع را تشخیص و به جدول مناسب می‌نویسد.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Financial_Sync
{
    public function __construct()
    {
        add_action('save_post_financial', [$this, 'on_save_post'], 20, 3);
        add_action('updated_post_meta',   [$this, 'on_meta_change'], 20, 4);
        add_action('added_post_meta',     [$this, 'on_meta_change'], 20, 4);

        add_action('wp_ajax_tcs_sync_financials_batch', [$this, 'ajax_sync_batch']);
        add_action('tcs_settings_after_form', [$this, 'render_box']);
    }

    public function on_save_post($post_id, $post, $update): void
    {
        if (wp_is_post_revision($post_id)) {
            return;
        }
        if (! $post instanceof WP_Post || $post->post_type !== 'financial') {
            return;
        }
        if (in_array($post->post_status, ['auto-draft', 'trash', 'inherit'], true)) {
            return;
        }
        $this->sync_post((int) $post_id);
    }

    public function on_meta_change($meta_id, $object_id, $meta_key, $_value): void
    {
        if (get_post_type($object_id) !== 'financial') {
            return;
        }
        $this->sync_post((int) $object_id);
    }

    public function sync_post(int $post_id): ?array
    {
        $post = get_post($post_id);
        if (! $post || $post->post_type !== 'financial') {
            return null;
        }
        if (in_array($post->post_status, ['auto-draft', 'trash', 'inherit'], true)) {
            return null;
        }

        $payload = $this->build_payload($post);
        if (! $payload) {
            return null;
        }

        return TCS_API_Client::send_or_queue('financial', $payload);
    }

    protected function build_payload(WP_Post $post): ?array
    {
        $get = function ($key) use ($post) {
            return get_post_meta($post->ID, $key, true);
        };

        $orderWpId = $this->meta_int($get('order_id'));
        $customerWpId = $this->meta_int($get('customer_id'));

        // technician در WP postmeta گاهی با کلیدهای متفاوت نگه‌داشته می‌شود.
        // اولویت: technician، سپس tech_id، سپس از سفارش resolve می‌شود سمت لاراول.
        $technicianWpId = $this->meta_int($get('technician')) ?: $this->meta_int($get('tech_id'));

        $payload = [
            'wp_id' => (int) $post->ID,
            'order_wp_id' => $orderWpId,
            'customer_wp_id' => $customerWpId,
            'technician_wp_id' => $technicianWpId,

            'price_customer' => $this->price_int($get('price_customer')),
            'cost_price'     => $this->price_int($get('cost_price')),
            'total_invoice'  => $this->price_int($get('total_invoice')),

            'wallet'      => $this->meta_bool($get('wallet')),
            'wallet_pay'  => $this->price_int($get('wallet_pay')),
            'refid'       => $this->meta_str($get('refid')),

            'reward_type' => $this->meta_int($get('reward_type')),
            'reward_desc' => $this->meta_str($get('rewardDesc')),

            'description'          => $this->meta_str($get('description')),
            'invoice_descripotion' => $this->meta_str($get('invoice_descripotion')),
            'payment_status'       => $this->meta_int($get('payment_status')),

            'post_title' => (string) $post->post_title,
            'post_date'  => (string) $post->post_date_gmt,
        ];

        return array_filter($payload, function ($v) {
            return $v !== null && $v !== '';
        });
    }

    public function ajax_sync_batch(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
        }
        check_ajax_referer('tcs_sync_financials_batch', '_ajax_nonce');

        $offset     = max(0, intval($_POST['offset'] ?? 0));
        $batch_size = max(1, min(200, intval($_POST['batch_size'] ?? 100)));

        @set_time_limit(120);

        $total = $this->count_financials();

        $posts = get_posts([
            'post_type'        => 'financial',
            'post_status'      => ['publish', 'pending', 'draft', 'private', 'future'],
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'numberposts'      => $batch_size,
            'offset'           => $offset,
            'suppress_filters' => true,
        ]);

        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        if (empty($posts)) {
            wp_send_json_success([
                'batch'          => $stats,
                'next_offset'    => $offset,
                'done'           => true,
                'total_records'  => $total,
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

        $detail = [
            'skipped_items' => [],
            'errors'        => [],
            'http_status'   => null,
            'http_error'    => null,
        ];

        if (! empty($items)) {
            $result = TCS_API_Client::post('financials/batch', ['items' => $items]);

            if (! empty($result['ok']) && isset($result['body'])) {
                $stats['total']   = (int) ($result['body']['total']   ?? count($items));
                $stats['created'] = (int) ($result['body']['created'] ?? 0);
                $stats['updated'] = (int) ($result['body']['updated'] ?? 0);
                $stats['skipped'] += (int) ($result['body']['skipped'] ?? 0);
                $stats['errors']  = isset($result['body']['errors']) ? count((array) $result['body']['errors']) : 0;

                // detail تا ۵ تای اول برای دیباگ سمت مرورگر
                $detail['skipped_items'] = array_slice((array) ($result['body']['skipped_items'] ?? []), 0, 5);
                $detail['errors']        = array_slice((array) ($result['body']['errors']        ?? []), 0, 5);
            } else {
                TCS_Sync_Queue::push('financials/batch', ['items' => $items], $result['error'] ?? '');
                $stats['errors'] = count($items);
                $detail['http_status'] = $result['status'] ?? null;
                $detail['http_error']  = $result['error']  ?? null;
            }
        }

        $next_offset = $offset + $batch_size;
        $done        = count($posts) < $batch_size;

        wp_send_json_success([
            'batch'         => $stats,
            'detail'        => $detail,
            'next_offset'   => $next_offset,
            'done'          => $done,
            'total_records' => $total,
        ]);
    }

    public function render_box(): void
    {
        $total = $this->count_financials();
        $nonce = wp_create_nonce('tcs_sync_financials_batch');
        ?>
        <hr>
        <h2>سینک رکوردهای مالی (فاکتور / کیف‌پول / جایزه و جریمه)</h2>
        <p>تعداد کل رکوردهای مالی: <strong><?php echo number_format_i18n($total); ?></strong></p>
        <p class="description">
            از این به بعد هر بار که رکورد مالی‌ای ساخته/ویرایش شود، خودکار سینک می‌شود.
            پلاگین فقط داده را می‌فرستد — تشخیص اینکه فاکتور است یا تراکنش کیف‌پول
            (شارژ/جایزه/جریمه) سمت لاراول انجام می‌شود.<br>
            برای backfill همهٔ رکوردهای فعلی، دکمهٔ زیر را بزنید (دسته‌های ۱۰۰‌تایی).
        </p>

        <p>
            <button type="button" id="tcs-start-fin" class="button button-primary"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    data-total="<?php echo (int) $total; ?>"
                    <?php disabled($total === 0); ?>>
                شروع سینک کامل مالی
            </button>
            <button type="button" id="tcs-cancel-fin" class="button" style="display:none;">لغو</button>
            <span id="tcs-fin-progress" style="margin-inline-start:10px;font-weight:bold;"></span>
        </p>

        <progress id="tcs-fin-bar" max="<?php echo (int) $total; ?>" value="0"
                  style="width:100%;height:25px;display:none;"></progress>

        <div id="tcs-fin-log"
             style="display:none;max-height:300px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;margin-top:10px;font-family:Menlo,Consolas,monospace;font-size:12px;direction:ltr;text-align:left;"></div>

        <script>
        (function ($) {
            var $btn    = $('#tcs-start-fin');
            var $cancel = $('#tcs-cancel-fin');
            var $bar    = $('#tcs-fin-bar');
            var $log    = $('#tcs-fin-log');
            var $msg    = $('#tcs-fin-progress');
            var batchSize = 100;
            var delayMs = 200;
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
                    action: 'tcs_sync_financials_batch',
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
                    state.totals.created += (b.created || 0);
                    state.totals.updated += (b.updated || 0);
                    state.totals.skipped += (b.skipped || 0);
                    state.totals.errors  += (b.errors  || 0);
                    state.offset = d.next_offset;
                    var done = Math.min(state.offset, state.total);
                    $bar.val(done);
                    $msg.text(done + ' / ' + state.total + ' — ' + fmt(state.totals));
                    log('offset=' + state.offset + ' +created=' + (b.created || 0) +
                        ' +updated=' + (b.updated || 0) + ' +skipped=' + (b.skipped || 0) +
                        ' +errors=' + (b.errors || 0));

                    // فقط برای ۵ آیتم اول هر دسته detail را در لاگ نمایش بده
                    if (det.skipped_items && det.skipped_items.length) {
                        det.skipped_items.forEach(function (s) {
                            log('  ⊘ skipped wp_id=' + s.wp_id + ' reason=' + s.reason +
                                ' snapshot=' + JSON.stringify(s.snapshot || {}));
                        });
                    }
                    if (det.errors && det.errors.length) {
                        det.errors.forEach(function (e) {
                            log('  ✗ error wp_id=' + e.wp_id + ' → ' + e.error);
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
                    finish('❌ خطای شبکه: HTTP ' + xhr.status + '. می‌توانید با همین دکمه ادامه دهید.');
                });
            }
            function finish(msg) {
                log(msg);
                $btn.prop('disabled', false).text('شروع سینک کامل مالی');
                $cancel.hide();
                state = null;
            }
            $btn.on('click', function () {
                var total = parseInt($btn.data('total'), 10) || 0;
                if (total === 0) return;
                if (!confirm('شروع سینک ' + total + ' رکورد مالی در دسته‌های ' + batchSize + '‌تایی؟')) return;
                state = {
                    offset: 0, total: total, cancelled: false,
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

    // ─────────────── helpers ───────────────────────────────────────

    protected function count_financials(): int
    {
        $c = wp_count_posts('financial');
        if (! $c) {
            return 0;
        }
        $n = 0;
        foreach (['publish', 'pending', 'draft', 'private', 'future'] as $s) {
            $n += (int) ($c->{$s} ?? 0);
        }
        return $n;
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
        if ($value === '' || $value === null || is_array($value)) {
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
}
