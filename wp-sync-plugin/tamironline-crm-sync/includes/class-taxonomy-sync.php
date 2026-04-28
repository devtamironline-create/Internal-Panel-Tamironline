<?php
/**
 * سینک تاکسونومی‌های CRM وردپرسی به پنل لاراول.
 *
 * تاکسونومی‌های پشتیبانی‌شده (با مرجع libs/taxonomy.php):
 *   - brand   → /api/crm/sync/taxonomy/brand
 *   - device  → /api/crm/sync/taxonomy/device
 *   - state   → /api/crm/sync/taxonomy/province  (نام Laravel: province)
 *   - city    → /api/crm/sync/taxonomy/city
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Taxonomy_Sync
{
    /**
     * نگاشت WP taxonomy slug → نوع endpoint سمت لاراول.
     * توجه: WP از 'state' استفاده می‌کند، Laravel از 'province'.
     */
    public const TAXONOMIES = [
        'brand'  => 'brand',
        'device' => 'device',
        'state'  => 'province',
        'city'   => 'city',
    ];

    public function __construct()
    {
        // hookهای real-time برای هر چهار taxonomy
        foreach (array_keys(self::TAXONOMIES) as $tax) {
            add_action("created_{$tax}", [$this, 'on_term_change'], 10, 3);
            add_action("edited_{$tax}",  [$this, 'on_term_change'], 10, 3);
        }

        // backfill با AJAX (برای هر taxonomy یک endpoint)
        add_action('wp_ajax_tcs_sync_taxonomy_batch', [$this, 'ajax_sync_batch']);

        // افزودن باکس به صفحهٔ تنظیمات
        add_action('tcs_settings_after_form', [$this, 'render_box']);
    }

    /**
     * Hook فراخوانده‌شده هنگام ساخت/ویرایش term.
     *
     * @param int    $term_id
     * @param int    $tt_id
     * @param string $taxonomy
     */
    public function on_term_change($term_id, $tt_id, $taxonomy = ''): void
    {
        // در WordPress hookهای created_/edited_{taxonomy} عمدتاً
        // اسم taxonomy در نام hook است، نه پارامتر؛ اما به‌خاطر امنیت
        // از get_term استفاده می‌کنیم.
        $term = get_term((int) $term_id);
        if (! $term || is_wp_error($term)) {
            return;
        }
        if (! isset(self::TAXONOMIES[$term->taxonomy])) {
            return;
        }

        $this->push_one($term);
    }

    /**
     * ارسال یک term به لاراول.
     */
    protected function push_one(\WP_Term $term): ?array
    {
        $type = self::TAXONOMIES[$term->taxonomy] ?? null;
        if (! $type) {
            return null;
        }

        return TCS_API_Client::send_or_queue("taxonomy/{$type}", [
            'wp_id' => (int) $term->term_id,
            'name'  => (string) $term->name,
            'slug'  => (string) $term->slug,
        ]);
    }

    /**
     * AJAX: ارسال یک batch از یک taxonomy.
     * مرورگر این endpoint را به ازای هر taxonomy جداگانه و در صورت نیاز
     * چندبار (با offset متفاوت) فراخوانی می‌کند.
     */
    public function ajax_sync_batch(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
        }
        check_ajax_referer('tcs_sync_taxonomy_batch', '_ajax_nonce');

        $wp_tax    = sanitize_key($_POST['taxonomy'] ?? '');
        $offset    = max(0, intval($_POST['offset'] ?? 0));
        $batch_size = max(1, min(500, intval($_POST['batch_size'] ?? 100)));

        if (! isset(self::TAXONOMIES[$wp_tax])) {
            wp_send_json_error(['message' => 'taxonomy ناشناخته.'], 400);
        }

        @set_time_limit(60);

        $type = self::TAXONOMIES[$wp_tax];
        $total = (int) wp_count_terms(['taxonomy' => $wp_tax, 'hide_empty' => false]);

        $terms = get_terms([
            'taxonomy'   => $wp_tax,
            'hide_empty' => false,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
            'number'     => $batch_size,
            'offset'     => $offset,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            wp_send_json_success([
                'taxonomy' => $wp_tax,
                'type'     => $type,
                'batch'    => ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0],
                'next_offset' => $offset,
                'done'     => true,
                'total_terms' => $total,
            ]);
        }

        $items = [];
        foreach ($terms as $term) {
            $items[] = [
                'wp_id' => (int) $term->term_id,
                'name'  => (string) $term->name,
                'slug'  => (string) $term->slug,
            ];
        }

        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];

        if (! empty($items)) {
            $result = TCS_API_Client::post("taxonomy/{$type}/batch", ['items' => $items]);

            if (! empty($result['ok']) && isset($result['body'])) {
                $stats['total']   = (int) ($result['body']['total']   ?? count($items));
                $stats['created'] = (int) ($result['body']['created'] ?? 0);
                $stats['updated'] = (int) ($result['body']['updated'] ?? 0);
                $stats['errors']  = isset($result['body']['errors']) ? count((array) $result['body']['errors']) : 0;
            } else {
                TCS_Sync_Queue::push("taxonomy/{$type}/batch", ['items' => $items], $result['error'] ?? '');
                $stats['errors'] = count($items);
            }
        }

        $next_offset = $offset + $batch_size;
        $done = count($terms) < $batch_size;

        wp_send_json_success([
            'taxonomy'   => $wp_tax,
            'type'       => $type,
            'batch'      => $stats,
            'next_offset' => $next_offset,
            'done'       => $done,
            'total_terms' => $total,
        ]);
    }

    public function render_box(): void
    {
        $nonce  = wp_create_nonce('tcs_sync_taxonomy_batch');
        $counts = [];
        foreach (self::TAXONOMIES as $wp_tax => $_type) {
            $counts[$wp_tax] = (int) wp_count_terms(['taxonomy' => $wp_tax, 'hide_empty' => false]);
        }
        $labels = [
            'brand'  => 'برندها',
            'device' => 'دستگاه‌ها',
            'state'  => 'استان‌ها',
            'city'   => 'شهرها',
        ];
        ?>
        <hr>
        <h2>سینک داده‌های پایه (برند / دستگاه / استان / شهر)</h2>
        <p class="description">
            از این به بعد هر بار که یکی از این تاکسونومی‌ها در WP تغییر کند به‌صورت خودکار سینک می‌شود.<br>
            برای ارسال یک‌بارهٔ همهٔ مقادیر فعلی، دکمهٔ زیر را بزنید. ارسال در دسته‌های ۱۰۰‌تایی است.
        </p>

        <table class="widefat striped" style="max-width:520px;">
            <thead><tr><th>تاکسونومی</th><th>تعداد</th></tr></thead>
            <tbody>
                <?php foreach ($labels as $k => $label): ?>
                <tr>
                    <td><?php echo esc_html($label); ?> (<code><?php echo esc_html($k); ?></code>)</td>
                    <td><?php echo number_format_i18n($counts[$k]); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:12px;">
            <button type="button" id="tcs-start-tax-sync" class="button button-primary"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                شروع سینک کامل داده‌های پایه
            </button>
            <span id="tcs-tax-progress" style="margin-inline-start:10px;font-weight:bold;"></span>
        </p>

        <progress id="tcs-tax-bar" max="100" value="0" style="display:none;width:100%;height:22px;"></progress>

        <div id="tcs-tax-log"
             style="display:none;max-height:240px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;margin-top:10px;font-family:Menlo,Consolas,monospace;font-size:12px;direction:ltr;text-align:left;"></div>

        <script>
        (function ($) {
            var $btn  = $('#tcs-start-tax-sync');
            var $bar  = $('#tcs-tax-bar');
            var $log  = $('#tcs-tax-log');
            var $msg  = $('#tcs-tax-progress');
            var batchSize = 100;

            // ترتیب: استان قبل از شهر تا اگر در آینده ربط بدیم سایدار باشه
            var taxonomies = ['brand', 'device', 'state', 'city'];
            var labels = {
                brand: 'برندها', device: 'دستگاه‌ها', state: 'استان‌ها', city: 'شهرها'
            };
            var totals = { brand: 0, device: 0, state: 0, city: 0 };

            function log(s) {
                var ts = new Date().toLocaleTimeString();
                $log.prepend('<div>[' + ts + '] ' + s + '</div>');
            }

            function syncOne(tax, offset, done) {
                $.post(ajaxurl, {
                    action: 'tcs_sync_taxonomy_batch',
                    _ajax_nonce: $btn.data('nonce'),
                    taxonomy: tax,
                    offset: offset,
                    batch_size: batchSize
                }).done(function (res) {
                    if (!res || !res.success) {
                        var em = (res && res.data && res.data.message) ? res.data.message : 'پاسخ نامعتبر';
                        log('❌ ' + tax + ': ' + em);
                        return done(false);
                    }
                    var d = res.data;
                    var b = d.batch || {};
                    totals[tax] += (b.created || 0) + (b.updated || 0);
                    log(tax + ' offset=' + offset + ' +' + ((b.created || 0) + (b.updated || 0)) +
                        ' (created=' + (b.created || 0) + ' updated=' + (b.updated || 0) +
                        ' errors=' + (b.errors || 0) + ')');
                    $msg.text('در حال سینک ' + labels[tax] + ' — کل تا الان: ' + totals[tax]);
                    if (d.done) {
                        log('✅ ' + labels[tax] + ' تمام شد. مجموع: ' + totals[tax]);
                        done(true);
                    } else {
                        setTimeout(function () { syncOne(tax, d.next_offset, done); }, 100);
                    }
                }).fail(function (xhr) {
                    log('❌ HTTP ' + xhr.status + ' روی ' + tax);
                    done(false);
                });
            }

            function syncAll(idx) {
                if (idx >= taxonomies.length) {
                    var grand = taxonomies.reduce(function (s, t) { return s + totals[t]; }, 0);
                    $msg.css('color', 'green').text('✅ پایان — مجموع کل: ' + grand);
                    $btn.prop('disabled', false).text('شروع سینک کامل داده‌های پایه');
                    return;
                }
                var tax = taxonomies[idx];
                log('▶ شروع ' + labels[tax] + '...');
                syncOne(tax, 0, function (_ok) { syncAll(idx + 1); });
            }

            $btn.on('click', function () {
                if (!confirm('شروع سینک ۴ تاکسونومی (برند، دستگاه، استان، شهر)؟')) return;
                totals = { brand: 0, device: 0, state: 0, city: 0 };
                $bar.show();
                $log.empty().show();
                $msg.css('color', '').text('در حال شروع...');
                $btn.prop('disabled', true).text('در حال سینک...');
                syncAll(0);
            });
        })(jQuery);
        </script>
        <?php
    }
}
