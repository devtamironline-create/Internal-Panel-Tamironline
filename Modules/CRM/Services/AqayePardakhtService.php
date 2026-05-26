<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\CrmSetting;

/**
 * سرویس درگاه پرداخت «آقای پرداخت» (Aqaye Pardakht).
 * مستندات: https://aqayepardakht.ir/api/pingateway/
 *
 * REST/JSON ساده (نه SOAP). جریان:
 *   ۱) POST /api/v2/create  → transid
 *   ۲) redirect GET به /startpay/{transid}
 *   ۳) callback شامل transid, status (1=موفق), tracking_number
 *   ۴) POST /api/v2/verify  → code "1" موفق، "2" قبلاً وریفای
 *
 * نکات:
 *   - مبلغ به **تومان** است (بدون تبدیل به ریال)
 *   - pin = کد پین درگاه (در sandbox مقدار "sandbox")
 *   - آدرس callback باید روی همان دامنهٔ تاییدشدهٔ درگاه باشد
 *
 * تنظیمات (crm_settings):
 *   aqp_pin, aqp_sandbox
 */
class AqayePardakhtService
{
    protected string $baseUrl = 'https://panel.aqayepardakht.ir';

    public function isSandbox(): bool
    {
        return CrmSetting::get('aqp_sandbox') === '1';
    }

    public function pin(): string
    {
        return $this->isSandbox() ? 'sandbox' : (string) (CrmSetting::get('aqp_pin') ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->pin() !== '';
    }

    /**
     * ایجاد تراکنش (create).
     *
     * @param  int  $amount  مبلغ به تومان (۱٬۰۰۰ تا ۴۰۰٬۰۰۰٬۰۰۰)
     * @return array{success:bool, transid?:string, paymentUrl?:string, code?:string, message?:string, raw?:array}
     */
    public function request(int $amount, string $callbackUrl, ?string $invoiceId = null, ?string $mobile = null, ?string $description = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'درگاه آقای پرداخت تنظیم نشده.'];
        }

        try {
            $response = Http::timeout(20)->asForm()->post("{$this->baseUrl}/api/v2/create", array_filter([
                'pin' => $this->pin(),
                'amount' => $amount,
                'callback' => $callbackUrl,
                'callback_method' => 'GET', // بازگشت با GET (توصیهٔ داکیومنت)
                'invoice_id' => $invoiceId,
                'mobile' => $mobile,
                'description' => $description,
            ], fn ($v) => $v !== null && $v !== ''));

            $body = $response->json() ?? [];

            if (($body['status'] ?? '') === 'success' && ! empty($body['transid'])) {
                $transid = (string) $body['transid'];
                return [
                    'success' => true,
                    'transid' => $transid,
                    'paymentUrl' => $this->isSandbox()
                        ? "{$this->baseUrl}/startpay/sandbox/{$transid}"
                        : "{$this->baseUrl}/startpay/{$transid}",
                    'raw' => $body,
                ];
            }

            $code = (string) ($body['code'] ?? '');
            Log::warning('AqayePardakht create failed', ['response' => $body]);
            return [
                'success' => false,
                'code' => $code,
                'message' => $this->errorMessage($code),
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('AqayePardakht create exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در اتصال به درگاه آقای پرداخت: ' . $e->getMessage()];
        }
    }

    /**
     * وریفای تراکنش (verify).
     *
     * @param  int  $amount  همان مبلغ تومان تراکنش
     * @return array{success:bool, code?:string, alreadyVerified?:bool, message?:string, raw?:array}
     */
    public function verify(string $transid, int $amount): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'درگاه آقای پرداخت تنظیم نشده.'];
        }

        try {
            $response = Http::timeout(20)->asForm()->post("{$this->baseUrl}/api/v2/verify", [
                'pin' => $this->pin(),
                'amount' => $amount,
                'transid' => $transid,
            ]);

            $body = $response->json() ?? [];
            $code = (string) ($body['code'] ?? '');

            // code 1 = موفق، code 2 = قبلاً وریفای و پرداخت شده
            if (($body['status'] ?? '') === 'success' && in_array($code, ['1', '2'], true)) {
                return [
                    'success' => true,
                    'code' => $code,
                    'alreadyVerified' => $code === '2',
                    'raw' => $body,
                ];
            }

            Log::warning('AqayePardakht verify failed', ['transid' => $transid, 'response' => $body]);
            return [
                'success' => false,
                'code' => $code,
                'message' => $this->errorMessage($code),
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('AqayePardakht verify exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطا در وریفای آقای پرداخت: ' . $e->getMessage()];
        }
    }

    /** پیام فارسی برای کدهای خطا. */
    public function errorMessage(string $code): string
    {
        $map = [
            '0' => 'پرداخت انجام نشد',
            '1' => 'پرداخت با موفقیت انجام شد',
            '2' => 'تراکنش قبلاً وریفای و پرداخت شده است',
            '-1' => 'مبلغ نمی‌تواند خالی باشد',
            '-2' => 'کد پین درگاه نمی‌تواند خالی باشد',
            '-3' => 'آدرس callback نمی‌تواند خالی باشد',
            '-4' => 'مبلغ باید عددی باشد',
            '-5' => 'مبلغ باید بین ۱٬۰۰۰ تا ۴۰۰٬۰۰۰٬۰۰۰ تومان باشد',
            '-6' => 'کد پین درگاه اشتباه است',
            '-7' => 'transid نمی‌تواند خالی باشد',
            '-8' => 'تراکنش مورد نظر وجود ندارد',
            '-9' => 'کد پین درگاه با درگاه تراکنش مطابقت ندارد',
            '-10' => 'مبلغ با مبلغ تراکنش مطابقت ندارد',
            '-11' => 'درگاه در انتظار تایید و یا غیرفعال است',
            '-12' => 'امکان ارسال درخواست برای این پذیرنده وجود ندارد',
            '-13' => 'شماره کارت باید ۱۶ رقم چسبیده به‌هم باشد',
            '-14' => 'درگاه روی سایت دیگری در حال استفاده است',
            '-15' => 'آدرس callback با دامنهٔ تاییدشدهٔ درگاه مغایرت دارد',
            '-16' => 'ارجاع‌دهنده نامعتبر است (Referer ارسال نشده)',
            '-17' => 'مقدار callback_method باید POST یا GET باشد',
        ];

        return $map[$code] ?? ('خطای درگاه آقای پرداخت — کد: ' . $code);
    }
}
