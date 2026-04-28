<?php
/**
 * سینک تنظیمات CRM وردپرسی (wp_options) به پنل لاراول.
 *
 * مرجع گزینه‌ها: libs/setting.php
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Settings_Sync
{
    /**
     * گزینه‌های wp_options که باید سینک شوند.
     * هر تغییر در این لیست به‌صورت خودکار به لاراول push می‌شود.
     */
    public const SYNCED_OPTIONS = [
        // SMS و Kavenegar
        'sms',

        // درگاه پرداخت و کیف پول
        'payment',

        // لیست‌های پویا
        'introductionList',  // مسیر آشنایی مشتری
        'cost_list',         // دسته‌های هزینه
        'objectionsList',    // اعتراض‌ها
        'shipping_list',     // انواع حمل و نقل
        'cancel',            // ۱۵ دلیل کنسلی

        // حسابداری
        'type_of_desc_acc',
        'title_of_desc_acc',

        // فاکتور
        'invoice_descritpion_show',          // (با تایپوی WP)
        'print_invoice_descritpion_show',    // (با تایپوی WP)

        // HappyCall
        'HappyCallTech',
        'HappyCallCustomer',
        'HappyCallTech_Count',
        'HappyCallCustomer_Count',
    ];

    public function __construct()
    {
        // hookهای real-time
        add_action('updated_option', [$this, 'on_updated_option'], 10, 3);
        add_action('added_option', [$this, 'on_added_option'], 10, 2);

        // backfill با AJAX (همهٔ گزینه‌ها در یک ریکوست — لیست کوچک است)
        add_action('wp_ajax_tcs_sync_settings_all', [$this, 'ajax_sync_all']);

        // افزودن باکس به صفحهٔ تنظیمات
        add_action('tcs_settings_after_form', [$this, 'render_box']);
    }

    public function on_updated_option($option, $_old_value, $value): void
    {
        if (! in_array($option, self::SYNCED_OPTIONS, true)) {
            return;
        }
        $this->push_one($option, $value);
    }

    public function on_added_option($option, $value): void
    {
        if (! in_array($option, self::SYNCED_OPTIONS, true)) {
            return;
        }
        $this->push_one($option, $value);
    }

    /**
     * ارسال یک گزینه به لاراول.
     */
    protected function push_one(string $option, $value): ?array
    {
        return TCS_API_Client::send_or_queue('setting', [
            'key' => $option,
            'value' => $value,
        ]);
    }

    /**
     * AJAX: ارسال یک‌بارهٔ همهٔ گزینه‌ها به لاراول.
     * چون تعداد ثابت و کوچک است (~۱۵)، نیازی به batching مرورگری نیست.
     */
    public function ajax_sync_all(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
        }
        check_ajax_referer('tcs_sync_settings_all', '_ajax_nonce');

        @set_time_limit(60);

        $items = [];
        foreach (self::SYNCED_OPTIONS as $opt) {
            $val = get_option($opt, null);
            if ($val === false || $val === null) {
                // گزینه‌هایی که اصلاً ست نشده‌اند را نمی‌فرستیم.
                continue;
            }
            $items[] = ['key' => $opt, 'value' => $val];
        }

        if (empty($items)) {
            wp_send_json_success([
                'total' => 0,
                'saved' => 0,
                'message' => 'هیچ تنظیمی برای سینک پیدا نشد.',
            ]);
        }

        $result = TCS_API_Client::post('settings/batch', ['items' => $items]);

        if (! empty($result['ok']) && isset($result['body'])) {
            wp_send_json_success([
                'total' => (int) ($result['body']['total'] ?? count($items)),
                'saved' => (int) ($result['body']['saved'] ?? 0),
                'errors' => isset($result['body']['errors']) ? count((array) $result['body']['errors']) : 0,
                'sent_keys' => array_column($items, 'key'),
            ]);
        }

        // در صورت خطای سرور، روی صف بگذار
        TCS_Sync_Queue::push('settings/batch', ['items' => $items], $result['error'] ?? '');
        wp_send_json_error([
            'message' => 'ارسال ناموفق بود — payload روی صف retry قرار گرفت. '
                       . ($result['error'] ?? ''),
        ], 502);
    }

    public function render_box(): void
    {
        $nonce = wp_create_nonce('tcs_sync_settings_all');
        $set_count = 0;
        foreach (self::SYNCED_OPTIONS as $opt) {
            if (get_option($opt, null) !== null && get_option($opt, null) !== false) {
                $set_count++;
            }
        }
        $total = count(self::SYNCED_OPTIONS);
        ?>
        <hr>
        <h2>سینک تنظیمات</h2>
        <p>
            تعداد گزینه‌های قابل سینک: <strong><?php echo (int) $total; ?></strong> ـ
            تعداد گزینه‌های موجود (ست‌شده): <strong><?php echo (int) $set_count; ?></strong>
        </p>
        <p class="description">
            از این به بعد هر بار که هر یک از گزینه‌های CRM در وردپرس تغییر کند، به‌صورت خودکار سینک می‌شود.<br>
            برای ارسال یک‌بارهٔ همهٔ تنظیمات فعلی، دکمهٔ زیر را بزنید (سریع است — همه در یک ریکوست).
        </p>

        <p>
            <button type="button" id="tcs-start-settings-sync" class="button button-primary"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                ارسال همهٔ تنظیمات الان
            </button>
            <span id="tcs-settings-result" style="margin-inline-start:10px;font-weight:bold;"></span>
        </p>

        <details style="margin-top:10px;">
            <summary style="cursor:pointer;color:#2271b1;">لیست گزینه‌های سینک‌شونده</summary>
            <ul style="columns:2;margin-top:8px;font-family:monospace;font-size:12px;direction:ltr;text-align:left;padding-inline-start:20px;">
                <?php foreach (self::SYNCED_OPTIONS as $opt): ?>
                <li><?php echo esc_html($opt); ?></li>
                <?php endforeach; ?>
            </ul>
        </details>

        <script>
        (function ($) {
            $('#tcs-start-settings-sync').on('click', function () {
                var $btn = $(this);
                var $msg = $('#tcs-settings-result');
                if (!confirm('ارسال همهٔ تنظیمات CRM به پنل لاراول؟')) return;
                $btn.prop('disabled', true).text('در حال ارسال...');
                $msg.text('');
                $.post(ajaxurl, {
                    action: 'tcs_sync_settings_all',
                    _ajax_nonce: $btn.data('nonce')
                }).done(function (res) {
                    if (res && res.success) {
                        $msg.css('color', 'green').text(
                            '✅ ' + (res.data.saved || 0) + ' / ' + (res.data.total || 0) + ' گزینه ذخیره شد' +
                            (res.data.errors ? ' (' + res.data.errors + ' خطا)' : '')
                        );
                    } else {
                        var em = (res && res.data && res.data.message) ? res.data.message : 'پاسخ نامعتبر';
                        $msg.css('color', 'red').text('❌ ' + em);
                    }
                }).fail(function (xhr) {
                    $msg.css('color', 'red').text('❌ HTTP ' + xhr.status);
                }).always(function () {
                    $btn.prop('disabled', false).text('ارسال همهٔ تنظیمات الان');
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}
