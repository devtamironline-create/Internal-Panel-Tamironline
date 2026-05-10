<?php
/**
 * Endpoint REST برای دریافت آپدیت‌های سفارش از سمت Laravel.
 *
 * Laravel هر بار که اپراتور/تکنسین فیلدی روی سفارش تغییر می‌دهد، یک
 * درخواست POST به این endpoint می‌فرستد و WP postmeta متناظر را
 * به‌روزرسانی می‌کند. هم‌چنین برای جلوگیری از حلقهٔ بی‌نهایت
 * (یعنی WP این تغییر را به خود Laravel echo کند) یک transient
 * `tcs_suppress_hooks_{post_id}` به‌مدت ۱۰ ثانیه ست می‌شود که هوک‌های
 * real-time روی همین سفارش را skip کند.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_Inbound_Order
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('tcs/v1', '/order-update', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_update'],
            'permission_callback' => [$this, 'verify_signature'],
        ]);
    }

    /** اعتبارسنجی HMAC — جلوگیری از پذیرش درخواست از منابع غیرمجاز. */
    public function verify_signature(\WP_REST_Request $request)
    {
        $secret = trim((string) get_option('tcs_inbound_secret', ''));
        if ($secret === '') {
            return new \WP_Error(
                'tcs_no_secret',
                'inbound secret تنظیم نشده — به صفحهٔ تنظیمات پلاگین بروید',
                ['status' => 503]
            );
        }

        $signatureHeader = $request->get_header('x-tcs-signature') ?? '';
        $body = $request->get_body();
        $expected = hash_hmac('sha256', (string) $body, $secret);

        if (! hash_equals($expected, $signatureHeader)) {
            return new \WP_Error('tcs_bad_sig', 'invalid signature', ['status' => 401]);
        }

        return true;
    }

    public function handle_update(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $wpId = (int) ($params['wp_id'] ?? 0);
        $fields = (array) ($params['fields'] ?? []);

        if ($wpId <= 0) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'wp_id required'], 400);
        }
        $post = get_post($wpId);
        if (! $post || $post->post_type !== 'orders') {
            return new \WP_REST_Response(['ok' => false, 'message' => 'order not found'], 404);
        }

        // فعال کردن قفل suppress تا هوک‌های real-time این سفارش را برای
        // ۱۰ ثانیه echo نکنند به Laravel.
        set_transient('tcs_suppress_hooks_' . $wpId, 1, 10);

        // مَپ فیلدهای Laravel → کلیدهای postmeta WP
        $applied = [];
        $skipped = [];
        foreach ($fields as $key => $value) {
            $metaKey = $this->map_field($key);
            if (! $metaKey) {
                $skipped[] = $key;
                continue;
            }
            // null = حذف postmeta
            if ($value === null || $value === '') {
                delete_post_meta($wpId, $metaKey);
            } else {
                update_post_meta($wpId, $metaKey, $value);
            }
            $applied[$metaKey] = $value;
        }

        return new \WP_REST_Response([
            'ok' => true,
            'wp_id' => $wpId,
            'applied' => array_keys($applied),
            'skipped' => $skipped,
        ], 200);
    }

    /**
     * نگاشت نام فیلد Laravel به meta_key وردپرس.
     * فیلدهای ناشناس عمداً null برمی‌گردانند تا اطلاعات بی‌ربط
     * به‌صورت postmeta در WP ذخیره نشوند.
     */
    protected function map_field(string $key): ?string
    {
        $map = [
            'status'              => 'status',                 // عدد ۰..۱۰
            'technician'          => 'technician',             // user_id WP
            'visit_date'          => 'scheduled_date',         // قالب v3
            'visit_time'          => 'scheduled_time',
            'description_tech'    => 'description_tech',
            'description_tech1'   => 'description_tech1',
            'description_tech2'   => 'description_tech2',
            'piece_list'          => 'piece_list',
            'buy_price_list'      => 'buy_price_list',
            'customer_price_list' => 'customer_price_list',
            'price_customer'      => 'price_customer',
            'cost_price'          => 'cost_price',
            'total_invoice'       => 'total_invoice',
            'hire'                => 'hire',
            'transportation'      => 'transportation',
            'discount'            => 'discount',
            'invoice_descripotion'=> 'invoice_descripotion',   // typo از WP
            'cancel_reason'       => 'cancel_desc',
            'return_type'         => 'return_type',
            'return_description'  => 'return_description',
            'save_as_draft'       => 'save_as_draft',
        ];
        return $map[$key] ?? null;
    }
}
