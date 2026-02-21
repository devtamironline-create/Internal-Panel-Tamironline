<?php
/**
 * Plugin Name: TamirOnline Order Statuses
 * Plugin URI: https://tamironline.com
 * Description: وضعیت‌های سفارش سفارشی تمیرآنلاین + سینک دوطرفه با پنل داخلی
 * Version: 1.0.0
 * Author: TamirOnline
 * Text Domain: tamironline-statuses
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

defined('ABSPATH') || exit;

class TamirOnline_Order_Statuses {

    /** @var string کلید API برای احراز هویت پنل */
    const OPTION_API_KEY = 'tamironline_panel_api_key';

    /** @var string آدرس پنل داخلی */
    const OPTION_PANEL_URL = 'tamironline_panel_url';

    /** @var array وضعیت‌های کاستوم با لیبل فارسی و رنگ */
    private static $custom_statuses = [
        'wc-supply-wait' => [
            'label'       => 'در انتظار تامین',
            'label_count' => 'در انتظار تامین <span class="count">(%s)</span>',
            'color'       => '#f59e0b', // amber
            'background'  => '#fffbeb',
        ],
        'wc-packed' => [
            'label'       => 'در صف ارسال (اسکن)',
            'label_count' => 'در صف ارسال <span class="count">(%s)</span>',
            'color'       => '#06b6d4', // cyan
            'background'  => '#ecfeff',
        ],
        'wc-shipped' => [
            'label'       => 'ارسال شده',
            'label_count' => 'ارسال شده <span class="count">(%s)</span>',
            'color'       => '#6366f1', // indigo
            'background'  => '#eef2ff',
        ],
        'wc-returned' => [
            'label'       => 'مرجوعی',
            'label_count' => 'مرجوعی <span class="count">(%s)</span>',
            'color'       => '#ef4444', // red
            'background'  => '#fef2f2',
        ],
    ];

    /**
     * نگاشت وضعیت پنل به وضعیت ووکامرس (و بالعکس)
     */
    private static $panel_to_wc_map = [
        'pending'     => 'processing',
        'supply_wait' => 'supply-wait',
        'packed'      => 'packed',
        'shipped'     => 'shipped',
        'delivered'   => 'completed',
        'returned'    => 'returned',
    ];

    public static function init() {
        // ثبت وضعیت‌ها
        add_action('init', [__CLASS__, 'register_statuses']);
        add_filter('wc_order_statuses', [__CLASS__, 'add_statuses_to_wc']);

        // استایل ادمین
        add_action('admin_head', [__CLASS__, 'admin_styles']);

        // ایمیل‌ها و اکشن‌های bulk
        add_filter('woocommerce_bulk_action_ids', [__CLASS__, 'register_bulk_actions'], 10, 2);
        add_filter('bulk_actions-edit-shop_order', [__CLASS__, 'add_bulk_actions']);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [__CLASS__, 'add_bulk_actions']);

        // REST API endpoints
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

        // Webhook: وقتی وضعیت در WC تغییر کرد → به پنل اطلاع بده
        add_action('woocommerce_order_status_changed', [__CLASS__, 'on_status_changed'], 10, 4);

        // صفحه تنظیمات
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    // =========================================================================
    //  ثبت وضعیت‌های کاستوم
    // =========================================================================

    public static function register_statuses() {
        foreach (self::$custom_statuses as $slug => $data) {
            register_post_status($slug, [
                'label'                     => $data['label'],
                'public'                    => true,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop($data['label_count'], $data['label_count']),
            ]);
        }
    }

    public static function add_statuses_to_wc($statuses) {
        $new_statuses = [];

        foreach ($statuses as $key => $label) {
            $new_statuses[$key] = $label;

            // بعد از processing وضعیت‌های جدید رو اضافه کن
            if ($key === 'wc-processing') {
                $new_statuses['wc-supply-wait'] = self::$custom_statuses['wc-supply-wait']['label'];
                $new_statuses['wc-packed']      = self::$custom_statuses['wc-packed']['label'];
                $new_statuses['wc-shipped']     = self::$custom_statuses['wc-shipped']['label'];
            }
        }

        // مرجوعی رو آخر اضافه کن
        $new_statuses['wc-returned'] = self::$custom_statuses['wc-returned']['label'];

        return $new_statuses;
    }

    // =========================================================================
    //  استایل ادمین (رنگ‌ها)
    // =========================================================================

    public static function admin_styles() {
        echo '<style>';
        foreach (self::$custom_statuses as $slug => $data) {
            $clean = str_replace('wc-', '', $slug);
            echo "
                .order-status.status-{$clean} {
                    background: {$data['background']};
                    color: {$data['color']};
                }
                .widefat .column-order_status mark.{$clean}::after,
                mark.order-status.status-{$clean} {
                    background: {$data['background']};
                    color: {$data['color']};
                    font-weight: 700;
                }
            ";
        }
        echo '</style>';
    }

    // =========================================================================
    //  Bulk Actions
    // =========================================================================

    public static function add_bulk_actions($actions) {
        foreach (self::$custom_statuses as $slug => $data) {
            $clean = str_replace('wc-', '', $slug);
            $actions["mark_{$clean}"] = 'تغییر وضعیت به ' . $data['label'];
        }
        return $actions;
    }

    // =========================================================================
    //  Webhook: سینک تغییرات WC → پنل
    // =========================================================================

    public static function on_status_changed($order_id, $old_status, $new_status, $order) {
        $panel_url = get_option(self::OPTION_PANEL_URL);
        $api_key   = get_option(self::OPTION_API_KEY);

        if (empty($panel_url) || empty($api_key)) {
            return;
        }

        // نگاشت وضعیت WC به پنل
        $wc_to_panel = array_flip(self::$panel_to_wc_map);
        $panel_status = $wc_to_panel[$new_status] ?? null;

        if (!$panel_status) {
            return; // وضعیت‌هایی مثل on-hold, cancelled و... رو skip کن
        }

        $endpoint = rtrim($panel_url, '/') . '/api/warehouse/webhook/status-changed';

        wp_remote_post($endpoint, [
            'timeout' => 10,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'wc_order_id'  => $order_id,
                'old_status'   => $old_status,
                'new_status'   => $new_status,
                'panel_status' => $panel_status,
                'source'       => 'woocommerce',
                'timestamp'    => current_time('mysql'),
            ]),
        ]);
    }

    // =========================================================================
    //  REST API: پنل → WC (سینک وضعیت + دریافت اطلاعات)
    // =========================================================================

    public static function register_rest_routes() {
        $namespace = 'tamironline/v1';

        // دریافت لیست وضعیت‌ها
        register_rest_route($namespace, '/statuses', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'api_get_statuses'],
            'permission_callback' => [__CLASS__, 'api_auth'],
        ]);

        // تغییر وضعیت سفارش
        register_rest_route($namespace, '/orders/(?P<id>\d+)/status', [
            'methods'             => 'PUT',
            'callback'            => [__CLASS__, 'api_update_status'],
            'permission_callback' => [__CLASS__, 'api_auth'],
        ]);

        // گرفتن وضعیت سفارش
        register_rest_route($namespace, '/orders/(?P<id>\d+)/status', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'api_get_status'],
            'permission_callback' => [__CLASS__, 'api_auth'],
        ]);

        // گرفتن آمار وضعیت‌ها
        register_rest_route($namespace, '/statuses/counts', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'api_get_status_counts'],
            'permission_callback' => [__CLASS__, 'api_auth'],
        ]);

        // تست اتصال
        register_rest_route($namespace, '/ping', [
            'methods'             => 'GET',
            'callback'            => function() {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'پلاگین تمیرآنلاین فعال است',
                    'version' => '1.0.0',
                    'time'    => current_time('mysql'),
                ], 200);
            },
            'permission_callback' => [__CLASS__, 'api_auth'],
        ]);
    }

    /**
     * احراز هویت API با کلید
     */
    public static function api_auth($request) {
        $api_key = get_option(self::OPTION_API_KEY);

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'کلید API تنظیم نشده', ['status' => 500]);
        }

        // از هدر Authorization بخون
        $auth = $request->get_header('Authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            $token = substr($auth, 7);
            if (hash_equals($api_key, $token)) {
                return true;
            }
        }

        // یا از query parameter
        $token = $request->get_param('api_key');
        if ($token && hash_equals($api_key, $token)) {
            return true;
        }

        return new WP_Error('unauthorized', 'کلید API نامعتبر', ['status' => 401]);
    }

    /**
     * GET /tamironline/v1/statuses
     * لیست همه وضعیت‌ها با نگاشت پنل
     */
    public static function api_get_statuses() {
        $wc_statuses = wc_get_order_statuses();
        $result = [];

        foreach ($wc_statuses as $slug => $label) {
            $clean = str_replace('wc-', '', $slug);
            $panel_map = array_flip(self::$panel_to_wc_map);

            $result[] = [
                'wc_slug'      => $clean,
                'wc_label'     => $label,
                'panel_status' => $panel_map[$clean] ?? null,
                'is_custom'    => isset(self::$custom_statuses[$slug]),
            ];
        }

        return new WP_REST_Response([
            'success'  => true,
            'statuses' => $result,
        ], 200);
    }

    /**
     * PUT /tamironline/v1/orders/{id}/status
     * تغییر وضعیت سفارش از طرف پنل
     */
    public static function api_update_status($request) {
        $order_id = (int) $request['id'];
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'سفارش یافت نشد',
            ], 404);
        }

        $body = $request->get_json_params();
        $panel_status = $body['panel_status'] ?? null;
        $wc_status    = $body['wc_status'] ?? null;
        $note         = $body['note'] ?? null;
        $tracking     = $body['tracking_code'] ?? null;

        // اگه panel_status داده شده، تبدیل کن
        if ($panel_status && !$wc_status) {
            $wc_status = self::$panel_to_wc_map[$panel_status] ?? null;
        }

        if (!$wc_status) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'وضعیت نامعتبر',
            ], 400);
        }

        $old_status = $order->get_status();

        // جلوگیری از فراخوانی webhook (چون خود پنل داره آپدیت می‌کنه)
        remove_action('woocommerce_order_status_changed', [__CLASS__, 'on_status_changed'], 10);

        $order->update_status($wc_status, $note ?: 'آپدیت از پنل تمیرآنلاین');

        // ذخیره کد رهگیری
        if ($tracking) {
            $order->update_meta_data('_tracking_code', $tracking);
            $order->save();
        }

        // hook رو برگردون
        add_action('woocommerce_order_status_changed', [__CLASS__, 'on_status_changed'], 10, 4);

        return new WP_REST_Response([
            'success'    => true,
            'message'    => 'وضعیت آپدیت شد',
            'old_status' => $old_status,
            'new_status' => $wc_status,
            'order_id'   => $order_id,
        ], 200);
    }

    /**
     * GET /tamironline/v1/orders/{id}/status
     * دریافت وضعیت فعلی سفارش
     */
    public static function api_get_status($request) {
        $order_id = (int) $request['id'];
        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'سفارش یافت نشد',
            ], 404);
        }

        $wc_status = $order->get_status();
        $wc_to_panel = array_flip(self::$panel_to_wc_map);

        return new WP_REST_Response([
            'success'      => true,
            'order_id'     => $order_id,
            'wc_status'    => $wc_status,
            'wc_label'     => wc_get_order_status_name($wc_status),
            'panel_status' => $wc_to_panel[$wc_status] ?? null,
            'tracking'     => $order->get_meta('_tracking_code') ?: null,
        ], 200);
    }

    /**
     * GET /tamironline/v1/statuses/counts
     * تعداد سفارشات هر وضعیت
     */
    public static function api_get_status_counts() {
        $counts = [];
        $wc_statuses = wc_get_order_statuses();

        foreach ($wc_statuses as $slug => $label) {
            $clean = str_replace('wc-', '', $slug);
            $count = wc_orders_count($clean);
            if ($count > 0) {
                $counts[$clean] = [
                    'label' => $label,
                    'count' => $count,
                ];
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'counts'  => $counts,
        ], 200);
    }

    // =========================================================================
    //  صفحه تنظیمات
    // =========================================================================

    public static function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            'تنظیمات تمیرآنلاین',
            'پنل تمیرآنلاین',
            'manage_woocommerce',
            'tamironline-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() {
        register_setting('tamironline_settings', self::OPTION_API_KEY);
        register_setting('tamironline_settings', self::OPTION_PANEL_URL);
    }

    public static function render_settings_page() {
        $api_key   = get_option(self::OPTION_API_KEY, '');
        $panel_url = get_option(self::OPTION_PANEL_URL, '');
        ?>
        <div class="wrap" dir="rtl" style="font-family: Tahoma, sans-serif;">
            <h1>تنظیمات پنل تمیرآنلاین</h1>

            <form method="post" action="options.php">
                <?php settings_fields('tamironline_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">آدرس پنل داخلی</th>
                        <td>
                            <input type="url" name="<?php echo self::OPTION_PANEL_URL; ?>"
                                   value="<?php echo esc_attr($panel_url); ?>"
                                   class="regular-text" dir="ltr"
                                   placeholder="https://panel.tamironline.com">
                            <p class="description">آدرس کامل پنل داخلی (بدون / انتهایی)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">کلید API</th>
                        <td>
                            <input type="text" name="<?php echo self::OPTION_API_KEY; ?>"
                                   value="<?php echo esc_attr($api_key); ?>"
                                   class="regular-text" dir="ltr"
                                   placeholder="یک کلید امن تصادفی">
                            <p class="description">
                                این کلید باید در تنظیمات پنل داخلی هم وارد شود.
                                <?php if (empty($api_key)): ?>
                                    <br><strong>پیشنهادی:</strong>
                                    <code><?php echo wp_generate_password(32, false); ?></code>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>

            <hr>

            <h2>وضعیت‌های ثبت شده</h2>
            <table class="widefat striped" style="max-width: 600px;">
                <thead>
                    <tr>
                        <th>وضعیت پنل</th>
                        <th>وضعیت ووکامرس</th>
                        <th>لیبل فارسی</th>
                        <th>نوع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (self::$panel_to_wc_map as $panel => $wc): ?>
                    <tr>
                        <td><code><?php echo $panel; ?></code></td>
                        <td><code><?php echo $wc; ?></code></td>
                        <td>
                            <?php
                            $wc_slug = 'wc-' . $wc;
                            if (isset(self::$custom_statuses[$wc_slug])) {
                                $s = self::$custom_statuses[$wc_slug];
                                echo '<span style="background:' . $s['background'] . ';color:' . $s['color'] . ';padding:2px 8px;border-radius:4px;font-weight:bold;">' . $s['label'] . '</span>';
                            } else {
                                echo wc_get_order_status_name($wc);
                            }
                            ?>
                        </td>
                        <td><?php echo isset(self::$custom_statuses[$wc_slug]) ? 'کاستوم' : 'پیش‌فرض WC'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr>

            <h2>تست اتصال API</h2>
            <p>
                <code dir="ltr"><?php echo home_url('/wp-json/tamironline/v1/ping?api_key=YOUR_KEY'); ?></code>
            </p>
            <p>
                <strong>Endpoints موجود:</strong>
            </p>
            <ul style="list-style: disc; padding-right: 20px;">
                <li><code>GET /wp-json/tamironline/v1/ping</code> — تست اتصال</li>
                <li><code>GET /wp-json/tamironline/v1/statuses</code> — لیست وضعیت‌ها</li>
                <li><code>GET /wp-json/tamironline/v1/statuses/counts</code> — آمار تعداد سفارشات</li>
                <li><code>GET /wp-json/tamironline/v1/orders/{id}/status</code> — وضعیت سفارش</li>
                <li><code>PUT /wp-json/tamironline/v1/orders/{id}/status</code> — تغییر وضعیت سفارش</li>
            </ul>
        </div>
        <?php
    }
}

// راه‌اندازی پلاگین
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>پلاگین تمیرآنلاین نیاز به ووکامرس دارد.</p></div>';
        });
        return;
    }
    TamirOnline_Order_Statuses::init();
});
