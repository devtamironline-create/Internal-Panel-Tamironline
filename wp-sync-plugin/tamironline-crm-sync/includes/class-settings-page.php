<?php
/**
 * صفحهٔ تنظیمات پلاگین در پیشخوان وردپرس.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Settings_Page
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_tcs_test_connection', [$this, 'test_connection']);
    }

    public function add_menu(): void
    {
        add_options_page(
            'CRM Sync',
            'CRM Sync',
            'manage_options',
            'tcs-settings',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting('tcs_settings_group', 'tcs_api_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);
        register_setting('tcs_settings_group', 'tcs_api_token', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $api_url    = (string) get_option('tcs_api_url', '');
        $api_token  = (string) get_option('tcs_api_token', '');
        $queue_size = TCS_Sync_Queue::size();
        $test_msg   = isset($_GET['tcs_test']) ? sanitize_text_field(wp_unslash($_GET['tcs_test'])) : '';
        $test_ok    = isset($_GET['tcs_ok']) ? (bool) intval($_GET['tcs_ok']) : false;
        ?>
        <div class="wrap" dir="rtl">
            <h1>تنظیمات سینک Tamironline CRM</h1>

            <?php if ($test_msg) : ?>
                <div class="notice <?php echo $test_ok ? 'notice-success' : 'notice-error'; ?>">
                    <p><?php echo esc_html($test_msg); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('tcs_settings_group'); ?>
                <?php do_settings_sections('tcs_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tcs_api_url">Base URL پنل لاراول</label></th>
                        <td>
                            <input name="tcs_api_url" id="tcs_api_url" type="url" dir="ltr"
                                   value="<?php echo esc_attr($api_url); ?>"
                                   class="regular-text" placeholder="https://panel.tamironline.com/api/crm/sync">
                            <p class="description">
                                دقیقاً همان آدرسی که در صفحهٔ «تنظیمات سینک وردپرس» پنل لاراول نشان داده می‌شود.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tcs_api_token">Bearer Token</label></th>
                        <td>
                            <input name="tcs_api_token" id="tcs_api_token" type="text" dir="ltr"
                                   value="<?php echo esc_attr($api_token); ?>"
                                   class="regular-text code" autocomplete="off">
                            <p class="description">
                                توکن را از پنل لاراول کپی کنید. در صورت بازتولید، توکن جدید را اینجا وارد کنید.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('ذخیرهٔ تنظیمات'); ?>
            </form>

            <hr>

            <h2>تست اتصال</h2>
            <p>این دکمه با Bearer token فعلی به endpoint <code>/ping</code> پنل لاراول وصل می‌شود.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('tcs_test_connection'); ?>
                <input type="hidden" name="action" value="tcs_test_connection">
                <?php submit_button('تست اتصال', 'secondary', 'submit', false); ?>
            </form>

            <hr>

            <h2>وضعیت صف ارسال</h2>
            <p>تعداد payloadهای منتظر retry: <strong><?php echo (int) $queue_size; ?></strong></p>
            <p class="description">
                اگر پنل لاراول موقتاً در دسترس نباشد، payloadهای ناموفق در این صف ذخیره می‌شوند و cron داخلی هر ۵ دقیقه دوباره تلاش می‌کند.
            </p>
        </div>
        <?php
    }

    public function test_connection(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('tcs_test_connection');

        $result = TCS_API_Client::get('ping');

        $url = admin_url('options-general.php?page=tcs-settings');

        if (! empty($result['ok'])) {
            $msg = '✅ اتصال برقرار است. سرور پاسخ داد: ' . (isset($result['body']['message']) ? $result['body']['message'] : 'OK');
            $url = add_query_arg(['tcs_test' => $msg, 'tcs_ok' => 1], $url);
        } else {
            $err = isset($result['error']) ? $result['error'] : 'خطای ناشناخته';
            $st  = isset($result['status']) ? ' (HTTP ' . (int) $result['status'] . ')' : '';
            $msg = '❌ اتصال ناموفق: ' . $err . $st;
            $url = add_query_arg(['tcs_test' => $msg, 'tcs_ok' => 0], $url);
        }

        wp_safe_redirect($url);
        exit;
    }
}
