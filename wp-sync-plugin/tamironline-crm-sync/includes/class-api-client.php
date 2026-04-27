<?php
/**
 * کلاینت HTTP برای ارسال داده به پنل لاراول.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

class TCS_API_Client
{
    /**
     * ارسال POST به یک endpoint سینک. در صورت خطای قابل‌retry،
     * payload روی صف ذخیره می‌شود تا cron بعداً دوباره تلاش کند.
     *
     * @param string $endpoint مسیر نسبی، مثلاً "customer" یا "customers/batch"
     * @param array  $data     payload (آرایه — در ارسال JSON می‌شود)
     * @return array { ok: bool, status?: int, body?: array, error?: string, retryable?: bool }
     */
    public static function post(string $endpoint, array $data): array
    {
        $base_url = trim((string) get_option('tcs_api_url', ''));
        $token    = trim((string) get_option('tcs_api_token', ''));

        if ($base_url === '' || $token === '') {
            return [
                'ok'        => false,
                'error'     => 'تنظیمات پلاگین کامل نیست (Base URL یا Token خالی است).',
                'retryable' => false,
            ];
        }

        $url = rtrim($base_url, '/') . '/' . ltrim($endpoint, '/');

        $response = wp_remote_post($url, [
            'timeout'   => 15,
            'sslverify' => true,
            'headers'   => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'body'      => wp_json_encode($data),
        ]);

        if (is_wp_error($response)) {
            return [
                'ok'        => false,
                'error'     => $response->get_error_message(),
                'retryable' => true,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        $ok        = $code >= 200 && $code < 300;
        $retryable = $code >= 500 || $code === 0;

        return [
            'ok'        => $ok,
            'status'    => $code,
            'body'      => is_array($body) ? $body : null,
            'error'     => $ok ? null : (is_array($body) && isset($body['message']) ? (string) $body['message'] : 'HTTP ' . $code),
            'retryable' => $retryable,
        ];
    }

    /**
     * ارسال GET ساده — برای تست اتصال.
     *
     * @param string $endpoint
     * @return array
     */
    public static function get(string $endpoint): array
    {
        $base_url = trim((string) get_option('tcs_api_url', ''));
        $token    = trim((string) get_option('tcs_api_token', ''));

        if ($base_url === '' || $token === '') {
            return [
                'ok'    => false,
                'error' => 'تنظیمات پلاگین کامل نیست.',
            ];
        }

        $url = rtrim($base_url, '/') . '/' . ltrim($endpoint, '/');

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'ok'     => $code >= 200 && $code < 300,
            'status' => $code,
            'body'   => is_array($body) ? $body : null,
        ];
    }

    /**
     * ارسال با retry: اگر خطای قابل‌retry برگشت، روی صف ذخیره می‌شود.
     */
    public static function send_or_queue(string $endpoint, array $data): array
    {
        $result = self::post($endpoint, $data);

        if (! $result['ok'] && ! empty($result['retryable'])) {
            TCS_Sync_Queue::push($endpoint, $data, $result['error'] ?? '');
        }

        return $result;
    }
}
