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
        'wc-delivered' => [
            'label'       => 'تحویل شده',
            'label_count' => 'تحویل شده <span class="count">(%s)</span>',
            'color'       => '#10b981', // emerald
            'background'  => '#ecfdf5',
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
        'delivered'   => 'delivered',
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
            // تغییر لیبل "در حال انجام" به "در حال پردازش"
            if ($key === 'wc-processing') {
                $new_statuses[$key] = 'در حال پردازش';
            } else {
                $new_statuses[$key] = $label;
            }

            // بعد از processing وضعیت‌های جدید رو اضافه کن
            if ($key === 'wc-processing') {
                $new_statuses['wc-supply-wait'] = self::$custom_statuses['wc-supply-wait']['label'];
                $new_statuses['wc-packed']      = self::$custom_statuses['wc-packed']['label'];
                $new_statuses['wc-shipped']     = self::$custom_statuses['wc-shipped']['label'];
                $new_statuses['wc-delivered']   = self::$custom_statuses['wc-delivered']['label'];
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
        // استایل‌ها بدون وابستگی به get_current_screen - روی همه صفحات ادمین لود میشه
        echo '<style id="tamironline-order-styles">';

        foreach (self::$custom_statuses as $slug => $data) {
            $clean = str_replace('wc-', '', $slug);
            echo "
                /* بج وضعیت - همه حالت‌های ممکن WC */
                .order-status.status-{$clean},
                mark.order-status.status-{$clean},
                .order-status.status-wc-{$clean},
                td.column-order_status mark.{$clean}::after,
                td mark.order-status.status-{$clean}::after,
                .wc-orders-list-table mark.order-status.status-{$clean},
                .wc-order-status--{$clean},
                span.{$clean},
                .order-status-{$clean} {
                    background: {$data['background']} !important;
                    color: {$data['color']} !important;
                    font-weight: 700 !important;
                    border-radius: 5px !important;
                    padding: 4px 12px !important;
                    line-height: 1.6em !important;
                    display: inline-block !important;
                    border: 1px solid {$data['color']}40 !important;
                    box-shadow: 0 1px 2px {$data['color']}15 !important;
                }
            ";
        }

        // استایل نوار فیلتر وضعیت - بدون شرط صفحه
        echo "
            /* نوار فیلتر وضعیت بالای لیست */
            .edit-shop_order .subsubsub,
            .woocommerce_page_wc-orders .subsubsub,
            .post-type-shop_order .subsubsub {
                width: 100% !important;
                background: #fff !important;
                padding: 14px 18px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 10px !important;
                margin-bottom: 14px !important;
                box-shadow: 0 1px 4px rgba(0,0,0,0.05) !important;
                line-height: 2.4em !important;
                box-sizing: border-box !important;
            }
            .edit-shop_order .subsubsub li,
            .woocommerce_page_wc-orders .subsubsub li,
            .post-type-shop_order .subsubsub li {
                display: inline-block !important;
                margin: 2px 0 !important;
            }
            .edit-shop_order .subsubsub li a,
            .woocommerce_page_wc-orders .subsubsub li a,
            .post-type-shop_order .subsubsub li a {
                text-decoration: none !important;
                padding: 5px 10px !important;
                border-radius: 6px !important;
                transition: all 0.2s !important;
            }
            .edit-shop_order .subsubsub li a:hover,
            .woocommerce_page_wc-orders .subsubsub li a:hover,
            .post-type-shop_order .subsubsub li a:hover {
                background: #f0f4f8 !important;
            }
            .edit-shop_order .subsubsub li a.current,
            .woocommerce_page_wc-orders .subsubsub li a.current,
            .post-type-shop_order .subsubsub li a.current {
                background: #edf2f7 !important;
                font-weight: 700 !important;
                color: #1a202c !important;
            }
            .edit-shop_order .subsubsub li a .count,
            .woocommerce_page_wc-orders .subsubsub li a .count,
            .post-type-shop_order .subsubsub li a .count {
                color: #a0aec0 !important;
                font-size: 0.88em !important;
            }
            /* جدا کننده | بین وضعیت‌ها */
            .edit-shop_order .subsubsub li::after,
            .woocommerce_page_wc-orders .subsubsub li::after,
            .post-type-shop_order .subsubsub li::after {
                color: #e2e8f0 !important;
            }
        ";

        // استایل تب‌ها
        echo "
            /* تب‌های فیلتر وضعیت */
            .tamironline-tabs-wrap {
                margin-bottom: 14px !important;
            }
            .tamironline-tab-buttons {
                display: flex !important;
                gap: 0 !important;
                margin-bottom: 0 !important;
                border-bottom: 2px solid #e2e8f0 !important;
            }
            .tamironline-tab-btn {
                padding: 10px 20px !important;
                border: none !important;
                background: transparent !important;
                cursor: pointer !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                color: #64748b !important;
                border-bottom: 2px solid transparent !important;
                margin-bottom: -2px !important;
                transition: all 0.2s !important;
                font-family: Tahoma, sans-serif !important;
            }
            .tamironline-tab-btn:hover {
                color: #334155 !important;
                background: #f8fafc !important;
            }
            .tamironline-tab-btn.active {
                color: #1e40af !important;
                border-bottom-color: #1e40af !important;
                background: #eff6ff !important;
            }
            .tamironline-tab-content {
                background: #fff !important;
                border: 1px solid #e2e8f0 !important;
                border-top: none !important;
                border-radius: 0 0 10px 10px !important;
                padding: 14px 18px !important;
                box-shadow: 0 1px 4px rgba(0,0,0,0.05) !important;
            }
            .tamironline-tab-panel {
                display: none !important;
            }
            .tamironline-tab-panel.active {
                display: block !important;
            }
            .tamironline-tab-panel .subsubsub {
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
                width: 100% !important;
                line-height: 2.4em !important;
            }
            .tamironline-dot {
                display: inline-block !important;
                width: 10px !important;
                height: 10px !important;
                border-radius: 50% !important;
                margin-left: 6px !important;
                margin-right: 2px !important;
                vertical-align: middle !important;
            }
        ";

        echo '</style>';

        // JSON داده وضعیت‌ها برای جاوااسکریپت
        $status_json = wp_json_encode(
            array_combine(
                array_map(function($slug) { return str_replace('wc-', '', $slug); }, array_keys(self::$custom_statuses)),
                array_values(self::$custom_statuses)
            )
        );

        // لیست وضعیت‌های اصلی (تب اول)
        $main_statuses = ['all', 'pending', 'processing', 'on-hold',
            'supply-wait', 'packed', 'shipped', 'delivered',
            'completed', 'cancelled', 'refunded', 'failed', 'returned', 'checkout-draft', 'trash'];

        $main_json = wp_json_encode($main_statuses);

        echo '<script id="tamironline-order-js">
            document.addEventListener("DOMContentLoaded", function() {
                var statusMap = ' . $status_json . ';
                var mainStatuses = ' . $main_json . ';

                var subsubsub = document.querySelector(".subsubsub");
                if (!subsubsub) return;

                // اضافه کردن نقطه رنگی به لینک‌های وضعیت کاستوم
                subsubsub.querySelectorAll("li a").forEach(function(link) {
                    var href = link.getAttribute("href") || "";
                    var text = link.textContent || "";

                    Object.keys(statusMap).forEach(function(status) {
                        var data = statusMap[status];
                        if (href.indexOf("post_status=wc-" + status) !== -1 ||
                            href.indexOf("status=" + status) !== -1 ||
                            href.indexOf("&status=" + status) !== -1 ||
                            text.indexOf(data.label) !== -1) {

                            if (!link.querySelector(".tamironline-dot")) {
                                var dot = document.createElement("span");
                                dot.className = "tamironline-dot";
                                dot.style.background = data.color;
                                dot.style.boxShadow = "0 0 0 2px " + data.color + "25";
                                link.insertBefore(dot, link.firstChild);
                            }
                            link.style.color = data.color;
                            link.style.fontWeight = "700";
                        }
                    });
                });

                // جداسازی آیتم‌ها به دو گروه: اصلی و سایر
                var allItems = Array.from(subsubsub.querySelectorAll("li"));
                var mainItems = [];
                var otherItems = [];

                allItems.forEach(function(li) {
                    var link = li.querySelector("a");
                    if (!link) return;
                    var href = link.getAttribute("href") || "";
                    var isMain = false;

                    mainStatuses.forEach(function(st) {
                        if (st === "all" && (href.indexOf("all_posts=1") !== -1 || href.indexOf("post_type=shop_order") !== -1 && href.indexOf("post_status") === -1 && href.indexOf("status=") === -1)) {
                            isMain = true;
                        } else if (st === "trash" && href.indexOf("post_status=trash") !== -1) {
                            isMain = true;
                        } else if (href.indexOf("post_status=wc-" + st) !== -1 || href.indexOf("status=" + st) !== -1) {
                            isMain = true;
                        }
                    });

                    // اگر لینک current هست و "همه" داره، اصلیه
                    if (link.classList.contains("current") && link.textContent.indexOf("\u0647\u0645\u0647") !== -1) {
                        isMain = true;
                    }

                    if (isMain) {
                        mainItems.push(li.cloneNode(true));
                    } else {
                        otherItems.push(li.cloneNode(true));
                    }
                });

                // اگر سایر خالیه، تب نذار
                if (otherItems.length === 0) {
                    return;
                }

                // ساختار تب‌ها
                var wrap = document.createElement("div");
                wrap.className = "tamironline-tabs-wrap";

                var btnBar = document.createElement("div");
                btnBar.className = "tamironline-tab-buttons";

                var btn1 = document.createElement("button");
                btn1.type = "button";
                btn1.className = "tamironline-tab-btn active";
                btn1.textContent = "\u0648\u0636\u0639\u06cc\u062a\u200c\u0647\u0627\u06cc \u0633\u0641\u0627\u0631\u0634";

                var btn2 = document.createElement("button");
                btn2.type = "button";
                btn2.className = "tamironline-tab-btn";
                btn2.textContent = "\u0633\u0627\u06cc\u0631 (" + otherItems.length + ")";

                btnBar.appendChild(btn1);
                btnBar.appendChild(btn2);

                var content = document.createElement("div");
                content.className = "tamironline-tab-content";

                // تب ۱: وضعیت‌های اصلی
                var panel1 = document.createElement("div");
                panel1.className = "tamironline-tab-panel active";
                var ul1 = document.createElement("ul");
                ul1.className = "subsubsub";
                mainItems.forEach(function(item, i) {
                    // حذف جداکننده | از آخرین آیتم
                    if (i === mainItems.length - 1) {
                        var nodes = item.childNodes;
                        for (var n = nodes.length - 1; n >= 0; n--) {
                            if (nodes[n].nodeType === 3 && nodes[n].textContent.indexOf("|") !== -1) {
                                nodes[n].textContent = nodes[n].textContent.replace("|", "");
                            }
                        }
                    }
                    ul1.appendChild(item);
                });
                panel1.appendChild(ul1);

                // تب ۲: سایر وضعیت‌ها
                var panel2 = document.createElement("div");
                panel2.className = "tamironline-tab-panel";
                var ul2 = document.createElement("ul");
                ul2.className = "subsubsub";
                otherItems.forEach(function(item, i) {
                    if (i === otherItems.length - 1) {
                        var nodes = item.childNodes;
                        for (var n = nodes.length - 1; n >= 0; n--) {
                            if (nodes[n].nodeType === 3 && nodes[n].textContent.indexOf("|") !== -1) {
                                nodes[n].textContent = nodes[n].textContent.replace("|", "");
                            }
                        }
                    }
                    ul2.appendChild(item);
                });
                panel2.appendChild(ul2);

                content.appendChild(panel1);
                content.appendChild(panel2);
                wrap.appendChild(btnBar);
                wrap.appendChild(content);

                // جایگزین کردن subsubsub قبلی
                subsubsub.parentNode.insertBefore(wrap, subsubsub);
                subsubsub.style.display = "none";

                // تغییر تب
                btn1.addEventListener("click", function() {
                    btn1.classList.add("active");
                    btn2.classList.remove("active");
                    panel1.classList.add("active");
                    panel2.classList.remove("active");
                });
                btn2.addEventListener("click", function() {
                    btn2.classList.add("active");
                    btn1.classList.remove("active");
                    panel2.classList.add("active");
                    panel1.classList.remove("active");
                });

                // اگه وضعیت فعال تو تب سایر هست، اون تب رو فعال کن
                if (panel2.querySelector("a.current")) {
                    btn2.click();
                }

                // استایل بج‌های وضعیت در جدول
                document.querySelectorAll("mark.order-status").forEach(function(el) {
                    var cls = el.className || "";
                    Object.keys(statusMap).forEach(function(status) {
                        if (cls.indexOf("status-" + status) !== -1) {
                            var data = statusMap[status];
                            el.style.cssText += "background:" + data.background + " !important;color:" + data.color + " !important;font-weight:700 !important;border-radius:5px !important;padding:4px 12px !important;border:1px solid " + data.color + "40 !important;";
                        }
                    });
                });
            });
        </script>';
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
        <div class="wrap" dir="rtl" style="font-family: Tahoma, 'Segoe UI', sans-serif; max-width: 900px;">

            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px; padding: 20px; background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8e 100%); border-radius: 12px; color: #fff;">
                <div>
                    <h1 style="margin: 0; color: #fff; font-size: 22px;">پنل تمیرآنلاین</h1>
                    <p style="margin: 6px 0 0; opacity: 0.85; font-size: 13px;">مدیریت وضعیت‌های سفارش و سینک دوطرفه با پنل داخلی</p>
                </div>
                <span style="margin-right: auto; background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px; font-size: 12px;">نسخه ۱.۰.۰</span>
            </div>

            <!-- بخش تنظیمات اتصال -->
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                <h2 style="margin-top: 0; font-size: 16px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f1;">تنظیمات اتصال</h2>

                <form method="post" action="options.php">
                    <?php settings_fields('tamironline_settings'); ?>

                    <div style="display: grid; gap: 20px; margin-top: 16px;">
                        <div style="display: grid; grid-template-columns: 160px 1fr; align-items: center; gap: 12px;">
                            <label style="font-weight: 700; font-size: 13px;">آدرس پنل داخلی</label>
                            <div>
                                <input type="url" name="<?php echo self::OPTION_PANEL_URL; ?>"
                                       value="<?php echo esc_attr($panel_url); ?>"
                                       style="width: 100%; max-width: 420px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px;"
                                       dir="ltr" placeholder="https://panel.tamironline.com">
                                <p style="margin: 4px 0 0; color: #888; font-size: 12px;">آدرس کامل پنل داخلی (بدون / انتهایی)</p>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 160px 1fr; align-items: center; gap: 12px;">
                            <label style="font-weight: 700; font-size: 13px;">کلید API</label>
                            <div>
                                <input type="text" name="<?php echo self::OPTION_API_KEY; ?>"
                                       value="<?php echo esc_attr($api_key); ?>"
                                       style="width: 100%; max-width: 420px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; font-family: monospace;"
                                       dir="ltr" placeholder="کلید امن تصادفی">
                                <p style="margin: 4px 0 0; color: #888; font-size: 12px;">
                                    این کلید باید در تنظیمات پنل داخلی هم وارد شود.
                                    <?php if (empty($api_key)): ?>
                                        <br><span style="color: #1e3a5f; font-weight: 700;">پیشنهادی:</span>
                                        <code style="background: #f0f6ff; padding: 3px 8px; border-radius: 4px; font-size: 12px; user-select: all; cursor: pointer;" title="کلیک برای کپی"><?php echo wp_generate_password(32, false); ?></code>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #f0f0f1;">
                        <?php submit_button('ذخیره تنظیمات', 'primary', 'submit', false); ?>

                        <?php if (!empty($api_key) && !empty($panel_url)): ?>
                            <span style="display: inline-flex; align-items: center; gap: 6px; margin-right: 16px; color: #16a34a; font-size: 13px; font-weight: 700;">
                                &#10003; تنظیمات ثبت شده
                            </span>
                        <?php elseif (empty($api_key) || empty($panel_url)): ?>
                            <span style="display: inline-flex; align-items: center; gap: 6px; margin-right: 16px; color: #dc2626; font-size: 13px; font-weight: 700;">
                                &#9888; تنظیمات ناقص است
                            </span>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- بخش وضعیت‌ها -->
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                <h2 style="margin-top: 0; font-size: 16px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f1;">نگاشت وضعیت‌ها (پنل &#8596; ووکامرس)</h2>

                <table style="width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 12px; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th style="padding: 10px 14px; text-align: right; font-size: 13px; font-weight: 700; border-bottom: 2px solid #e5e7eb; color: #374151;">وضعیت پنل</th>
                            <th style="padding: 10px 14px; text-align: right; font-size: 13px; font-weight: 700; border-bottom: 2px solid #e5e7eb; color: #374151;">وضعیت ووکامرس</th>
                            <th style="padding: 10px 14px; text-align: right; font-size: 13px; font-weight: 700; border-bottom: 2px solid #e5e7eb; color: #374151;">نمایش</th>
                            <th style="padding: 10px 14px; text-align: center; font-size: 13px; font-weight: 700; border-bottom: 2px solid #e5e7eb; color: #374151;">نوع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        foreach (self::$panel_to_wc_map as $panel => $wc):
                            $wc_slug = 'wc-' . $wc;
                            $is_custom = isset(self::$custom_statuses[$wc_slug]);
                            $bg = $i % 2 === 0 ? '#fff' : '#fafbfc';
                            $i++;
                        ?>
                        <tr style="background: <?php echo $bg; ?>;">
                            <td style="padding: 10px 14px; border-bottom: 1px solid #f0f0f1;">
                                <code style="background: #f3f4f6; padding: 3px 8px; border-radius: 4px; font-size: 12px;"><?php echo $panel; ?></code>
                            </td>
                            <td style="padding: 10px 14px; border-bottom: 1px solid #f0f0f1;">
                                <code style="background: #f3f4f6; padding: 3px 8px; border-radius: 4px; font-size: 12px;"><?php echo $wc; ?></code>
                            </td>
                            <td style="padding: 10px 14px; border-bottom: 1px solid #f0f0f1;">
                                <?php if ($is_custom):
                                    $s = self::$custom_statuses[$wc_slug]; ?>
                                    <span style="background: <?php echo $s['background']; ?>; color: <?php echo $s['color']; ?>; padding: 4px 12px; border-radius: 6px; font-weight: 700; font-size: 13px; border: 1px solid <?php echo $s['color']; ?>30; display: inline-block;"><?php echo $s['label']; ?></span>
                                <?php else: ?>
                                    <span style="background: #f0f0f1; color: #555; padding: 4px 12px; border-radius: 6px; font-size: 13px; display: inline-block;"><?php echo wc_get_order_status_name($wc); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px 14px; border-bottom: 1px solid #f0f0f1; text-align: center;">
                                <?php if ($is_custom): ?>
                                    <span style="background: #dbeafe; color: #1d4ed8; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;">کاستوم</span>
                                <?php else: ?>
                                    <span style="background: #f3f4f6; color: #6b7280; padding: 3px 10px; border-radius: 20px; font-size: 11px;">پیش‌فرض</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- فلوچارت وضعیت‌ها -->
                <div style="margin-top: 20px; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e5e7eb;">
                    <h3 style="margin: 0 0 12px; font-size: 14px; color: #374151;">مسیر تغییر وضعیت سفارش:</h3>
                    <div style="display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 8px; direction: ltr; font-size: 13px;">
                        <span style="background: #dbeafe; color: #1d4ed8; padding: 6px 14px; border-radius: 6px; font-weight: 700;">در حال پردازش</span>
                        <span style="color: #9ca3af; font-size: 18px;">&#8594;</span>
                        <span style="background: <?php echo self::$custom_statuses['wc-supply-wait']['background']; ?>; color: <?php echo self::$custom_statuses['wc-supply-wait']['color']; ?>; padding: 6px 14px; border-radius: 6px; font-weight: 700; border: 1px solid <?php echo self::$custom_statuses['wc-supply-wait']['color']; ?>30;"><?php echo self::$custom_statuses['wc-supply-wait']['label']; ?></span>
                        <span style="color: #9ca3af; font-size: 18px;">&#8594;</span>
                        <span style="background: <?php echo self::$custom_statuses['wc-packed']['background']; ?>; color: <?php echo self::$custom_statuses['wc-packed']['color']; ?>; padding: 6px 14px; border-radius: 6px; font-weight: 700; border: 1px solid <?php echo self::$custom_statuses['wc-packed']['color']; ?>30;"><?php echo self::$custom_statuses['wc-packed']['label']; ?></span>
                        <span style="color: #9ca3af; font-size: 18px;">&#8594;</span>
                        <span style="background: <?php echo self::$custom_statuses['wc-shipped']['background']; ?>; color: <?php echo self::$custom_statuses['wc-shipped']['color']; ?>; padding: 6px 14px; border-radius: 6px; font-weight: 700; border: 1px solid <?php echo self::$custom_statuses['wc-shipped']['color']; ?>30;"><?php echo self::$custom_statuses['wc-shipped']['label']; ?></span>
                        <span style="color: #9ca3af; font-size: 18px;">&#8594;</span>
                        <span style="background: <?php echo self::$custom_statuses['wc-delivered']['background']; ?>; color: <?php echo self::$custom_statuses['wc-delivered']['color']; ?>; padding: 6px 14px; border-radius: 6px; font-weight: 700; border: 1px solid <?php echo self::$custom_statuses['wc-delivered']['color']; ?>30;"><?php echo self::$custom_statuses['wc-delivered']['label']; ?></span>
                        <span style="color: #9ca3af; font-size: 18px;">&#8594;</span>
                        <span style="background: #dcfce7; color: #16a34a; padding: 6px 14px; border-radius: 6px; font-weight: 700;">تکمیل شده</span>
                    </div>
                    <div style="text-align: center; margin-top: 10px; direction: ltr;">
                        <span style="color: #9ca3af; font-size: 12px;">&#8595; در هر مرحله امکان</span>
                        <span style="background: <?php echo self::$custom_statuses['wc-returned']['background']; ?>; color: <?php echo self::$custom_statuses['wc-returned']['color']; ?>; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; border: 1px solid <?php echo self::$custom_statuses['wc-returned']['color']; ?>30;"><?php echo self::$custom_statuses['wc-returned']['label']; ?></span>
                        <span style="color: #9ca3af; font-size: 12px;">وجود دارد</span>
                    </div>
                </div>
            </div>

            <!-- بخش API -->
            <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                <h2 style="margin-top: 0; font-size: 16px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f1;">API Endpoints</h2>

                <div style="margin-top: 12px;">
                    <p style="margin: 0 0 12px; font-size: 13px; color: #6b7280;">آدرس پایه: <code dir="ltr" style="background: #f3f4f6; padding: 3px 8px; border-radius: 4px;"><?php echo home_url('/wp-json/tamironline/v1/'); ?></code></p>

                    <div style="display: grid; gap: 10px;">
                        <?php
                        $endpoints = [
                            ['GET', '/ping', 'تست اتصال', '#16a34a'],
                            ['GET', '/statuses', 'لیست وضعیت‌ها', '#16a34a'],
                            ['GET', '/statuses/counts', 'آمار تعداد سفارشات', '#16a34a'],
                            ['GET', '/orders/{id}/status', 'دریافت وضعیت سفارش', '#16a34a'],
                            ['PUT', '/orders/{id}/status', 'تغییر وضعیت سفارش', '#d97706'],
                        ];
                        foreach ($endpoints as $ep):
                        ?>
                        <div style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #fafbfc; border-radius: 6px; border: 1px solid #f0f0f1;">
                            <span style="background: <?php echo $ep[3]; ?>15; color: <?php echo $ep[3]; ?>; padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: 700; font-family: monospace; min-width: 40px; text-align: center;"><?php echo $ep[0]; ?></span>
                            <code dir="ltr" style="font-size: 13px; color: #374151;"><?php echo $ep[1]; ?></code>
                            <span style="margin-right: auto; color: #9ca3af; font-size: 12px;"><?php echo $ep[2]; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 16px; padding: 12px; background: #fffbeb; border-radius: 6px; border: 1px solid #fde68a; font-size: 12px; color: #92400e;">
                        <strong>نکته:</strong> تمام درخواست‌ها نیاز به هدر <code dir="ltr" style="background: #fff; padding: 2px 6px; border-radius: 3px;">Authorization: Bearer YOUR_API_KEY</code> دارند.
                    </div>
                </div>
            </div>
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
