<?php
/**
 * Endpoint REST برای دریافت تراکنش‌های مالی از سمت Laravel.
 *
 * WP CRM رکوردهای مالی را به‌صورت post_type=financial نگه می‌دارد.
 * هر تراکنش (شارژ کیف‌پول، جریمه، پاداش، کمیسیون، تعدیل) یک پست است.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) exit;

class TCS_Inbound_Financial
{
    use TCS_Inbound_Hmac;

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('tcs/v1', '/financial-upsert', [
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

        $technicianWpId = (int) ($fields['technician_wp_id'] ?? 0);
        if ($technicianWpId <= 0) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'technician_wp_id required'], 400);
        }

        $title = (string) ($fields['post_title'] ?? 'تراکنش مالی');
        $postArr = [
            'post_type'   => 'financial',
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_author' => $technicianWpId,
        ];

        if ($wpId > 0 && get_post($wpId)) {
            $postArr['ID'] = $wpId;
            $result = wp_update_post($postArr, true);
        } else {
            $result = wp_insert_post($postArr, true);
        }

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['ok' => false, 'message' => $result->get_error_message()], 500);
        }
        $wpId = (int) $result;

        set_transient('tcs_suppress_financial_' . $wpId, 1, 10);

        // متاهای رایج CRM وردپرسی
        $allowed = [
            'technician_id', 'technician',
            'wallet', 'wallet_pay', 'wallet_charge',
            'refid', 'reward_type', 'rewardDesc',
            'price_customer', 'cost_price', 'total_invoice',
            'description', 'invoice_descripotion', 'payment_status',
            'order_id',
        ];
        foreach ($fields as $key => $value) {
            if (! in_array($key, $allowed, true)) continue;
            if ($value === null || $value === '') {
                delete_post_meta($wpId, $key);
            } else {
                update_post_meta($wpId, $key, $value);
            }
        }

        // technician در WP CRM با چند نام مختلف ست می‌شود؛ همه را پر می‌کنیم
        update_post_meta($wpId, 'technician_id', $technicianWpId);
        update_post_meta($wpId, 'technician', $technicianWpId);

        return new \WP_REST_Response(['ok' => true, 'wp_id' => $wpId], 200);
    }
}
