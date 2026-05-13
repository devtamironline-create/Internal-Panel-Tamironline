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

        // نرمالایز نام فیلدها — لاراول ممکن است املای صحیح را بفرستد
        // (ready_for_delivery / img_personal) و باید به املای WP CRM
        // (ready_for_derliver / img_Personal) ترجمه شود.
        $fields = $this->normalize_field_keys($fields);

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

        // نام نمایشی: ترجیحاً firstname_tech (فارسی)، در نبود از first_name،
        // در نهایت از login. این برای ساخت اول و آپدیت بعدی استفاده می‌شود.
        $displayName = trim((string) ($fields['firstname_tech'] ?? '')) !== ''
            ? (string) $fields['firstname_tech']
            : (trim((string) ($fields['first_name'] ?? '')) !== '' ? (string) $fields['first_name'] : '');

        if ($wpId <= 0) {
            // ساخت کاربر جدید با login بر اساس موبایل
            $login = $this->unique_login('tech_' . preg_replace('/\D/', '', $mobile));
            $userId = wp_insert_user([
                'user_login'   => $login,
                'user_pass'    => wp_generate_password(16),
                'user_email'   => $login . '@local',
                'first_name'   => $fields['first_name'] ?? '',
                'last_name'    => '',
                'display_name' => $displayName !== '' ? $displayName : $login,
                'role'         => 'subscriber',
            ]);
            if (is_wp_error($userId)) {
                return new \WP_REST_Response(['ok' => false, 'message' => $userId->get_error_message()], 500);
            }
            $wpId = (int) $userId;
            update_user_meta($wpId, 'role', 'technician');
        } else {
            // برای کاربر موجود: اگر firstname_tech/first_name تغییر کرده،
            // display_name هم به‌روز شود تا در لیست WP CRM دیده شود.
            if ($displayName !== '') {
                $update = ['ID' => $wpId, 'display_name' => $displayName];
                if (isset($fields['first_name'])) {
                    $update['first_name'] = (string) $fields['first_name'];
                }
                wp_update_user($update);
            }
        }

        set_transient('tcs_suppress_user_' . $wpId, 1, 10);

        $allowed = [
            'first_name', 'firstname_tech', 'technician_id', 'national_code',
            'mobile', 'phone', 'phone_force', 'address', 'description',
            'percent', 'max_order', 'max_price', 'status', 'type_tech',
            'cart_img', 'province', 'specialty', 'ready_for_derliver',
            'type_of_calc_tech', 'tech_per_of_all', 'img_Personal', 'role',
        ];
        $written = 0;
        foreach ($fields as $key => $value) {
            if (! in_array($key, $allowed, true)) continue;
            if ($value === null || $value === '') {
                delete_user_meta($wpId, $key);
            } else {
                update_user_meta($wpId, $key, $value);
                $written++;
            }
        }

        return new \WP_REST_Response([
            'ok' => true,
            'wp_id' => $wpId,
            'meta_written' => $written,
            'display_name' => $displayName,
        ], 200);
    }

    /**
     * نگاشت نام فیلدها بین Laravel و WP. Laravel نام صحیح می‌فرستد، WP
     * CRM با نام‌های تاریخی (تایپو/Capital) ذخیره می‌کند.
     */
    protected function normalize_field_keys(array $fields): array
    {
        $map = [
            'ready_for_delivery' => 'ready_for_derliver',
            'img_personal'       => 'img_Personal',
        ];
        foreach ($map as $from => $to) {
            if (array_key_exists($from, $fields) && ! array_key_exists($to, $fields)) {
                $fields[$to] = $fields[$from];
                unset($fields[$from]);
            }
        }
        return $fields;
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
