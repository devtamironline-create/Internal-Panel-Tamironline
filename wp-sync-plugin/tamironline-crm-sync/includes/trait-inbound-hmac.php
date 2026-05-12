<?php
/**
 * Trait اعتبارسنجی HMAC SHA256 برای endpointهای inbound. درخواست‌ها
 * باید X-TCS-Signature معتبر داشته باشند که با tcs_inbound_secret
 * محاسبه شده. مشترک بین Inbound_Order/Technician/Customer/Financial.
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) exit;

trait TCS_Inbound_Hmac
{
    /** اعتبارسنجی HMAC — جلوگیری از پذیرش درخواست از منابع غیرمجاز. */
    public function verify_signature(\WP_REST_Request $request)
    {
        $secret = trim((string) get_option('tcs_inbound_secret', ''));
        if ($secret === '') {
            return new \WP_Error('tcs_no_secret',
                'inbound secret تنظیم نشده — به صفحهٔ تنظیمات پلاگین بروید',
                ['status' => 503]);
        }

        $signatureHeader = $request->get_header('x-tcs-signature') ?? '';
        $body = $request->get_body();
        $expected = hash_hmac('sha256', (string) $body, $secret);

        if (! hash_equals($expected, $signatureHeader)) {
            return new \WP_Error('tcs_bad_sig', 'invalid signature', ['status' => 401]);
        }
        return true;
    }
}
