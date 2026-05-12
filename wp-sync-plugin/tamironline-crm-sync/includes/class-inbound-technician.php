<?php
/**
 * Endpoint REST برای دریافت آپدیت‌های تکنسین از سمت Laravel.
 *
 * - wp_id موجود → فقط update_user_meta روی کاربر
 * - wp_id null  → wp_insert_user + ست meta role=technician + برگرداندن user_id
 *
 * Loop prevention: transient tcs_suppress_user_{id} برای ۱۰ ثانیه.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) exit;

class TCS_Inbound_Technician
{
    use TCS_Inbound_Hmac;

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('tcs/v1', '/technician-upsert', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_upsert'],
            'permission_callback' => [$this, 'verify_signature'],
        ]);
    }

    public function handle_upsert(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $wpId   = (int) ($params['wp_id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);

        $mobile = (string) ($fields['mobile'] ?? '');
        if ($mobile === '' && $wpId <= 0) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'mobile required for new user'], 400);
        }

        // اگر wp_id داریم، همان را به‌روز کن. در غیر این صورت ابتدا بر اساس
        // mobile جستجو کن تا کاربر تکراری ایجاد نکنیم.
        if ($wpId <= 0 && $mobile !== '') {
            $existing = $this->find_user_by_mobile($mobile);
            if ($existing) $wpId = $existing;
        }

        if ($wpId <= 0) {
            // ساخت کاربر جدید با login بر اساس موبایل
            $login = $this->unique_login('tech_' . preg_replace('/\D/', '', $mobile));
            $userId = wp_insert_user([
                'user_login' => $login,
                'user_pass'  => wp_generate_password(16),
                'user_email' => $login . '@local',
                'first_name' => $fields['first_name'] ?? '',
                'last_name'  => '',
                'display_name' => $fields['first_name'] ?? $login,
                'role'       => 'subscriber',
            ]);
            if (is_wp_error($userId)) {
                return new \WP_REST_Response(['ok' => false, 'message' => $userId->get_error_message()], 500);
            }
            $wpId = (int) $userId;
            update_user_meta($wpId, 'role', 'technician');
        }

        set_transient('tcs_suppress_user_' . $wpId, 1, 10);

        $allowed = [
            'first_name', 'firstname_tech', 'technician_id', 'national_code',
            'mobile', 'phone', 'phone_force', 'address', 'description',
            'percent', 'max_order', 'max_price', 'status', 'type_tech',
            'cart_img', 'province', 'specialty', 'ready_for_derliver',
            'type_of_calc_tech', 'tech_per_of_all', 'img_Personal', 'role',
        ];
        foreach ($fields as $key => $value) {
            if (! in_array($key, $allowed, true)) continue;
            if ($value === null || $value === '') {
                delete_user_meta($wpId, $key);
            } else {
                update_user_meta($wpId, $key, $value);
            }
        }

        return new \WP_REST_Response(['ok' => true, 'wp_id' => $wpId], 200);
    }

    protected function find_user_by_mobile(string $mobile): int
    {
        // متاهای mobile / phone_force می‌توانند شماره را داشته باشند
        global $wpdb;
        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key IN ('mobile','phone_force','phone') AND meta_value = %s LIMIT 1",
            $mobile
        ));
        return (int) $row;
    }

    protected function unique_login(string $base): string
    {
        $base = $base ?: 'tech_' . wp_rand(1000, 9999);
        $login = $base;
        $i = 0;
        while (username_exists($login)) {
            $i++;
            $login = $base . '_' . $i;
            if ($i > 99) { $login = $base . '_' . wp_rand(10000, 99999); break; }
        }
        return $login;
    }
}
