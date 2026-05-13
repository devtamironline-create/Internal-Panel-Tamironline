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

        // نرمالایز موبایل — هم برای match (find_user_by_mobile) هم برای
        // ذخیره. اگر whitespace/digits فارسی داشت، تمیز نگه‌می‌داریم.
        $mobile = $this->normalize_mobile((string) ($fields['mobile'] ?? ''));
        if ($mobile !== '') {
            $fields['mobile'] = $mobile;
        }
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

    /**
     * یافتن کاربر بر اساس موبایل — robust به whitespace و ارقام فارسی.
     *
     * WP CRM موبایل را با فضاهای padding ذخیره می‌کند (مثل ' 09017304923 ')
     * و گاهی ارقام فارسی (۰۹...). تطابق exact شکست می‌خورد. اینجا با
     * TRIM و LIKE انجام می‌شود. nickname هم (که از مبدا login تکنسین
     * = tech_<digits> ساخته می‌شود) به‌عنوان fallback چک می‌شود تا
     * duplicateهایی که از race condition قبلی به وجود آمده‌اند نیز
     * detect شوند.
     */
    protected function find_user_by_mobile(string $mobile): int
    {
        global $wpdb;
        $clean = $this->normalize_mobile($mobile);
        if ($clean === '') return 0;

        // ۱) tightest: meta exact یا با whitespace padding
        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
             WHERE meta_key IN ('mobile','phone_force','phone')
               AND TRIM(meta_value) = %s
             ORDER BY user_id ASC LIMIT 1",
            $clean
        ));
        if ($row) return (int) $row;

        // ۲) fallback: nickname یا user_login حاوی digits موبایل
        //    (نسخهٔ قدیمی این پلاگین nickname=tech_<digits> ساخته)
        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT u.ID FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key='nickname'
             WHERE u.user_login LIKE %s OR m.meta_value LIKE %s
             ORDER BY u.ID ASC LIMIT 1",
            '%' . $wpdb->esc_like($clean) . '%',
            '%' . $wpdb->esc_like($clean) . '%'
        ));
        return (int) $row;
    }

    /** نرمالایز موبایل: ارقام فارسی→انگلیسی، حذف فاصله/خط/پیشوند بین‌المللی. */
    protected function normalize_mobile(string $mobile): string
    {
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $arabic  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $latin   = ['0','1','2','3','4','5','6','7','8','9'];
        $mobile = str_replace($persian, $latin, $mobile);
        $mobile = str_replace($arabic, $latin, $mobile);
        $mobile = trim($mobile);
        // فقط ارقام را نگه دار
        $mobile = preg_replace('/\D/', '', $mobile);
        return (string) $mobile;
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
