<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\CrmSetting;

/**
 * سرویس درگاه پرداخت زیبال.
 *
 * Endpoints:
 *   POST https://gateway.zibal.ir/v1/request          → trackId + paymentUrl
 *   GET  https://gateway.zibal.ir/start/{trackId}     → صفحه پرداخت
 *   POST https://gateway.zibal.ir/v1/verify           → تایید پرداخت
 *
 * تنظیمات از جدول crm_settings خوانده می‌شوند:
 *   - zibal_merchant      → merchant ID (یا "zibal" برای سندباکس)
 *   - zibal_sandbox       → '1' / '0'
 */
class ZibalService
{
    protected string $baseUrl = 'https://gateway.zibal.ir';

    public function merchant(): string
    {
        $sandbox = CrmSetting::get('zibal_sandbox') === '1';

        return $sandbox ? 'zibal' : (CrmSetting::get('zibal_merchant') ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->merchant() !== '';
    }

    /**
     * درخواست پرداخت جدید. در موفقیت، trackId و URL پرداخت برگردانده می‌شود.
     *
     * @param  int  $amount  مبلغ به تومان (داخلاً به ریال تبدیل می‌شود)
     * @return array{success:bool, trackId?:string, paymentUrl?:string, message?:string, raw?:array}
     */
    public function request(int $amount, string $callbackUrl, ?string $orderId = null, ?string $mobile = null, ?string $description = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'درگاه زیبال تنظیم نشده.'];
        }

        try {
            $response = Http::timeout(15)->asJson()->post("{$this->baseUrl}/v1/request", [
                'merchant' => $this->merchant(),
                'amount' => $amount * 10, // تبدیل تومان به ریال
                'callbackUrl' => $callbackUrl,
                'orderId' => $orderId,
                'mobile' => $mobile,
                'description' => $description,
            ]);

            $body = $response->json();

            if (isset($body['result']) && $body['result'] == 100) {
                return [
                    'success' => true,
                    'trackId' => (string) $body['trackId'],
                    'paymentUrl' => "{$this->baseUrl}/start/{$body['trackId']}",
                    'raw' => $body,
                ];
            }

            Log::warning('Zibal request failed', ['response' => $body]);

            return [
                'success' => false,
                'message' => $body['message'] ?? 'خطا در ایجاد درخواست پرداخت.',
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Zibal request exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'خطا در اتصال به درگاه زیبال: ' . $e->getMessage()];
        }
    }

    /**
     * تایید پرداخت بعد از بازگشت از زیبال.
     *
     * @return array{success:bool, refNumber?:string, cardNumber?:string, hashedCardNumber?:string, result?:int, message?:string, amount?:int, raw?:array}
     */
    public function verify(string $trackId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'درگاه زیبال تنظیم نشده.'];
        }

        try {
            $response = Http::timeout(15)->asJson()->post("{$this->baseUrl}/v1/verify", [
                'merchant' => $this->merchant(),
                'trackId' => $trackId,
            ]);

            $body = $response->json();
            $result = (int) ($body['result'] ?? 0);

            // 100: تایید موفق، 201: قبلاً تایید شده
            if (in_array($result, [100, 201], true)) {
                return [
                    'success' => true,
                    'refNumber' => (string) ($body['refNumber'] ?? ''),
                    'cardNumber' => (string) ($body['cardNumber'] ?? ''),
                    'hashedCardNumber' => (string) ($body['hashedCardNumber'] ?? ''),
                    'amount' => isset($body['amount']) ? intdiv((int) $body['amount'], 10) : null,
                    'result' => $result,
                    'message' => $body['message'] ?? null,
                    'raw' => $body,
                ];
            }

            Log::warning('Zibal verify failed', ['trackId' => $trackId, 'response' => $body]);

            return [
                'success' => false,
                'result' => $result,
                'message' => $body['message'] ?? 'تایید پرداخت ناموفق.',
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Zibal verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'خطا در تایید پرداخت: ' . $e->getMessage()];
        }
    }
}
