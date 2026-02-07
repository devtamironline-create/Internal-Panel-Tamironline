<?php
/**
 * Plugin Name: Tamir Warehouse Connector
 * Plugin URI: https://tamironline.ir
 * Description: اتصال فروشگاه ووکامرس به پنل مدیریت انبار تامیر
 * Version: 1.0.0
 * Author: Tamir Team
 * Author URI: https://tamironline.ir
 * Text Domain: tamir-warehouse
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>Tamir Warehouse Connector</strong> نیاز به نصب و فعال بودن ووکامرس دارد.</p></div>';
    });
    return;
}

define('TWC_VERSION', '1.0.0');
define('TWC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TWC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class Tamir_Warehouse_Connector {

    private static $instance = null;
    private $panel_url = '';
    private $webhook_secret = '';

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->panel_url = get_option('twc_panel_url', '');
        $this->webhook_secret = get_option('twc_webhook_secret', '');

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);

        // WooCommerce order hooks
        add_action('woocommerce_new_order', [$this, 'on_new_order'], 10, 1);
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 4);
        add_action('woocommerce_payment_complete', [$this, 'on_payment_complete'], 10, 1);

        // Add order meta box
        add_action('add_meta_boxes', [$this, 'add_order_meta_box']);

        // Admin styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
    }

    /**
     * Add settings page to admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'اتصال به پنل انبار',
            'اتصال پنل انبار',
            'manage_woocommerce',
            'tamir-warehouse',
            [$this, 'settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('twc_settings', 'twc_panel_url');
        register_setting('twc_settings', 'twc_webhook_secret');
        register_setting('twc_settings', 'twc_auto_sync');
        register_setting('twc_settings', 'twc_sync_on_new_order');
        register_setting('twc_settings', 'twc_sync_on_status_change');
    }

    /**
     * Settings page HTML
     */
    public function settings_page() {
        if (isset($_POST['twc_test_connection'])) {
            $result = $this->test_connection();
            $test_message = $result['success'] ?
                '<div class="notice notice-success"><p>اتصال برقرار شد!</p></div>' :
                '<div class="notice notice-error"><p>خطا: ' . esc_html($result['message']) . '</p></div>';
        }

        if (isset($_POST['twc_manual_sync'])) {
            $result = $this->manual_sync();
            $sync_message = $result['success'] ?
                '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>' :
                '<div class="notice notice-error"><p>خطا: ' . esc_html($result['message']) . '</p></div>';
        }
        ?>
        <div class="wrap twc-settings">
            <h1>اتصال به پنل مدیریت انبار</h1>

            <?php if (isset($test_message)) echo $test_message; ?>
            <?php if (isset($sync_message)) echo $sync_message; ?>

            <form method="post" action="options.php">
                <?php settings_fields('twc_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">آدرس پنل</th>
                        <td>
                            <input type="url" name="twc_panel_url"
                                   value="<?php echo esc_attr(get_option('twc_panel_url')); ?>"
                                   class="regular-text"
                                   placeholder="https://panel.example.com">
                            <p class="description">آدرس کامل پنل مدیریت انبار</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">کلید امنیتی Webhook</th>
                        <td>
                            <input type="text" name="twc_webhook_secret"
                                   value="<?php echo esc_attr(get_option('twc_webhook_secret')); ?>"
                                   class="regular-text">
                            <p class="description">این کلید باید با تنظیمات پنل یکسان باشد</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">همگام‌سازی خودکار</th>
                        <td>
                            <label>
                                <input type="checkbox" name="twc_sync_on_new_order" value="1"
                                       <?php checked(get_option('twc_sync_on_new_order'), 1); ?>>
                                ارسال سفارش جدید به پنل
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="twc_sync_on_status_change" value="1"
                                       <?php checked(get_option('twc_sync_on_status_change'), 1); ?>>
                                ارسال تغییر وضعیت سفارش به پنل
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>

            <hr>

            <h2>ابزارها</h2>
            <form method="post" style="display: inline;">
                <input type="hidden" name="twc_test_connection" value="1">
                <?php submit_button('تست اتصال', 'secondary', 'test', false); ?>
            </form>

            <form method="post" style="display: inline; margin-right: 10px;">
                <input type="hidden" name="twc_manual_sync" value="1">
                <?php submit_button('همگام‌سازی دستی سفارشات اخیر', 'secondary', 'sync', false); ?>
            </form>

            <hr>

            <h2>راهنمای نصب</h2>
            <ol>
                <li>در پنل مدیریت انبار، به بخش تنظیمات بروید</li>
                <li>کلیدهای API ووکامرس را از WooCommerce > تنظیمات > پیشرفته > REST API دریافت کنید</li>
                <li>آدرس پنل و کلید Webhook را در این صفحه وارد کنید</li>
                <li>گزینه‌های همگام‌سازی خودکار را فعال کنید</li>
                <li>با دکمه "تست اتصال" صحت اتصال را بررسی کنید</li>
            </ol>

            <h2>وضعیت اتصال</h2>
            <table class="widefat" style="max-width: 500px;">
                <tr>
                    <th>آدرس پنل</th>
                    <td><?php echo esc_html(get_option('twc_panel_url') ?: 'تنظیم نشده'); ?></td>
                </tr>
                <tr>
                    <th>Webhook Secret</th>
                    <td><?php echo get_option('twc_webhook_secret') ? '••••••••' : 'تنظیم نشده'; ?></td>
                </tr>
                <tr>
                    <th>آخرین همگام‌سازی</th>
                    <td><?php
                        $last_sync = get_option('twc_last_sync');
                        echo $last_sync ? date_i18n('Y/m/d H:i:s', $last_sync) : 'هرگز';
                    ?></td>
                </tr>
            </table>
        </div>

        <style>
            .twc-settings { direction: rtl; }
            .twc-settings h1, .twc-settings h2 { font-family: Vazirmatn, Tahoma, sans-serif; }
            .twc-settings .form-table th { text-align: right; }
        </style>
        <?php
    }

    /**
     * Test connection to panel
     */
    private function test_connection() {
        $panel_url = get_option('twc_panel_url');
        if (empty($panel_url)) {
            return ['success' => false, 'message' => 'آدرس پنل تنظیم نشده است'];
        }

        $response = wp_remote_get($panel_url . '/api/warehouse/ping', [
            'timeout' => 10,
            'headers' => [
                'X-Webhook-Secret' => get_option('twc_webhook_secret'),
            ],
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'کد پاسخ: ' . $code];
    }

    /**
     * Manual sync recent orders
     */
    private function manual_sync() {
        $panel_url = get_option('twc_panel_url');
        if (empty($panel_url)) {
            return ['success' => false, 'message' => 'آدرس پنل تنظیم نشده است'];
        }

        $orders = wc_get_orders([
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_created' => '>' . (time() - 7 * DAY_IN_SECONDS),
        ]);

        $synced = 0;
        foreach ($orders as $order) {
            if ($this->send_order_to_panel($order)) {
                $synced++;
            }
        }

        update_option('twc_last_sync', time());

        return [
            'success' => true,
            'message' => sprintf('%d سفارش همگام‌سازی شد', $synced)
        ];
    }

    /**
     * Hook: New order created
     */
    public function on_new_order($order_id) {
        if (!get_option('twc_sync_on_new_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if ($order) {
            $this->send_order_to_panel($order, 'new_order');
        }
    }

    /**
     * Hook: Order status changed
     */
    public function on_order_status_changed($order_id, $old_status, $new_status, $order) {
        if (!get_option('twc_sync_on_status_change')) {
            return;
        }

        $this->send_order_to_panel($order, 'status_changed', [
            'old_status' => $old_status,
            'new_status' => $new_status,
        ]);
    }

    /**
     * Hook: Payment complete
     */
    public function on_payment_complete($order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->send_order_to_panel($order, 'payment_complete');
        }
    }

    /**
     * Send order data to panel
     */
    private function send_order_to_panel($order, $event = 'sync', $extra_data = []) {
        $panel_url = get_option('twc_panel_url');
        $webhook_secret = get_option('twc_webhook_secret');

        if (empty($panel_url)) {
            return false;
        }

        $order_data = $this->prepare_order_data($order);
        $order_data['event'] = $event;
        $order_data['extra'] = $extra_data;

        $response = wp_remote_post($panel_url . '/api/warehouse/webhook/order', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Webhook-Secret' => $webhook_secret,
                'X-WC-Webhook-Event' => $event,
            ],
            'body' => json_encode($order_data),
        ]);

        if (is_wp_error($response)) {
            $this->log_error('Webhook failed: ' . $response->get_error_message(), $order->get_id());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log_error('Webhook returned code: ' . $code, $order->get_id());
            return false;
        }

        // Update order meta to track sync
        $order->update_meta_data('_twc_last_sync', time());
        $order->save();

        return true;
    }

    /**
     * Prepare order data for API
     */
    private function prepare_order_data($order) {
        $line_items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $line_items[] = [
                'id' => $item_id,
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name' => $item->get_name(),
                'sku' => $product ? $product->get_sku() : '',
                'quantity' => $item->get_quantity(),
                'price' => $order->get_item_subtotal($item, false, true),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'total_tax' => $item->get_total_tax(),
                'meta_data' => $this->get_item_meta($item),
                'image' => $product ? ['src' => wp_get_attachment_url($product->get_image_id())] : null,
            ];
        }

        return [
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'currency' => $order->get_currency(),
            'total' => $order->get_total(),
            'subtotal' => $order->get_subtotal(),
            'total_tax' => $order->get_total_tax(),
            'shipping_total' => $order->get_shipping_total(),
            'discount_total' => $order->get_discount_total(),
            'customer_id' => $order->get_customer_id(),
            'billing' => $order->get_address('billing'),
            'shipping' => $order->get_address('shipping'),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
            'customer_note' => $order->get_customer_note(),
            'date_created' => $order->get_date_created() ? $order->get_date_created()->format('c') : null,
            'date_modified' => $order->get_date_modified() ? $order->get_date_modified()->format('c') : null,
            'date_paid' => $order->get_date_paid() ? $order->get_date_paid()->format('c') : null,
            'date_completed' => $order->get_date_completed() ? $order->get_date_completed()->format('c') : null,
            'line_items' => $line_items,
            'meta_data' => $order->get_meta_data(),
        ];
    }

    /**
     * Get item meta data
     */
    private function get_item_meta($item) {
        $meta_data = [];
        foreach ($item->get_meta_data() as $meta) {
            if (strpos($meta->key, '_') !== 0) {
                $meta_data[] = [
                    'key' => $meta->key,
                    'value' => $meta->value,
                    'display_key' => wc_attribute_label($meta->key, $item->get_product()),
                    'display_value' => $meta->value,
                ];
            }
        }
        return $meta_data;
    }

    /**
     * Add meta box to order page
     */
    public function add_order_meta_box() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'twc_order_sync',
            'پنل انبار',
            [$this, 'render_order_meta_box'],
            $screen,
            'side',
            'default'
        );
    }

    /**
     * Render order meta box
     */
    public function render_order_meta_box($post_or_order) {
        $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order($post_or_order->ID);
        if (!$order) return;

        $last_sync = $order->get_meta('_twc_last_sync');
        ?>
        <p>
            <strong>آخرین همگام‌سازی:</strong><br>
            <?php echo $last_sync ? date_i18n('Y/m/d H:i:s', $last_sync) : 'همگام‌سازی نشده'; ?>
        </p>
        <button type="button" class="button" onclick="twcSyncOrder(<?php echo $order->get_id(); ?>)">
            همگام‌سازی با پنل
        </button>
        <script>
        function twcSyncOrder(orderId) {
            jQuery.post(ajaxurl, {
                action: 'twc_sync_order',
                order_id: orderId,
                nonce: '<?php echo wp_create_nonce('twc_sync'); ?>'
            }, function(response) {
                alert(response.success ? 'همگام‌سازی انجام شد' : 'خطا: ' + response.data);
                location.reload();
            });
        }
        </script>
        <?php
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if (strpos($hook, 'tamir-warehouse') !== false) {
            wp_enqueue_style('twc-admin', TWC_PLUGIN_URL . 'assets/admin.css', [], TWC_VERSION);
        }
    }

    /**
     * Log error
     */
    private function log_error($message, $order_id = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Tamir Warehouse] ' . $message . ($order_id ? ' (Order: ' . $order_id . ')' : ''));
        }
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    Tamir_Warehouse_Connector::get_instance();
});

// AJAX handler for manual sync
add_action('wp_ajax_twc_sync_order', function() {
    check_ajax_referer('twc_sync', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('دسترسی ندارید');
    }

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error('سفارش یافت نشد');
    }

    $connector = Tamir_Warehouse_Connector::get_instance();
    $result = $connector->send_order_to_panel($order, 'manual_sync');

    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('خطا در همگام‌سازی');
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    add_option('twc_panel_url', '');
    add_option('twc_webhook_secret', '');
    add_option('twc_sync_on_new_order', 1);
    add_option('twc_sync_on_status_change', 1);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Keep options for potential reactivation
});
