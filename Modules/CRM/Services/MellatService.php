<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\CRM\Models\CrmSetting;

/**
 * سرویس درگاه پرداخت «به‌پرداخت ملت» (BehPardakht Mellat).
 *
 * بر خلاف Zibal، ملت SOAP-محور است و جریان آن سه مرحله دارد:
 *   ۱) bpPayRequest  → RefId (توکن پرداخت)
 *   ۲) redirect با POST به startpay.mellat با RefId
 *   ۳) callback (POST) شامل RefId, ResCode, SaleOrderId, SaleReferenceId
 *   ۴) bpVerifyRequest + bpSettleRequest برای نهایی‌سازی
 *
 * برای استقلال از SoapClient extension، از SOAP-over-HTTP خام
 * (Http::withBody با XML envelope) استفاده می‌شود.
 *
 * تنظیمات (crm_settings):
 *   mellat_terminal_id, mellat_username, mellat_password
 *
 * نکات:
 *   - مبلغ به ریال (تومان × ۱۰)
 *   - orderId باید عددی (long) باشد — برخلاف Zibal که string می‌پذیرد
 */
class MellatService
{
    protected string $wsdlEndpoint = 'https://bpm.shaparak.ir/pgwchannel/services/pgw';
    protected string $startPayUrl = 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat';
    protected string $namespace = 'http://interfaces.core.sw.bps.com/';

    public function terminalId(): string
    {
        return (string) (CrmSetting::get('mellat_terminal_id') ?? '');
    }

    public function username(): string
    {
        return (string) (CrmSetting::get('mellat_username') ?? '');
    }

    public function password(): string
    {
        return (string) (CrmSetting::get('mellat_password') ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->terminalId() !== '' && $this->username() !== '' && $this->password() !== '';
    }

    public function startPayUrl(): string
    {
        return $this->startPayUrl;
    }

    /**
     * درخواست پرداخت (bpPayRequest).
     *
     * @param  int  $amount  مبلغ به تومان (داخلاً به ریال تبدیل می‌شود)
     * @param  int  $orderId  شناسهٔ عددی یکتای سفارش (saleOrderId)
     * @return array{success:bool, refId?:string, orderId?:int, startPayUrl?:string, resCode?:string, message?:string, raw?:string}
     */
    public function request(int $amount, string $callbackUrl, int $orderId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'درگاه ملت تنظیم نشده.'];
        }

        $localDate = now()->format('Ymd');
        $localTime = now()->format('His');
        $amountRial = $amount * 10;

        $params = [
            'terminalId' => $this->terminalId(),
            'userName' => $this->username(),
            'userPassword' => $this->password(),
            'orderId' => $orderId,
            'amount' => $amountRial,
            'localDate' => $localDate,
            'localTime' => $localTime,
            'additionalData' => '',
            'callBackUrl' => $callbackUrl,
            'payerId' => 0,
        ];

        $result = $this->soapCall('bpPayRequest', $params);
        if (! $result['ok']) {
            return ['success' => false, 'message' => $result['message'], 'raw' => $result['raw'] ?? null];
        }

        // پاسخ موفق: "0,RefId" — کد ۰ یعنی موفق، بقیه خطا
        $parts = explode(',', trim((string) $result['return']));
        $resCode = $parts[0] ?? '';

        if ($resCode === '0' && isset($parts[1])) {
            return [
                'success' => true,
                'refId' => trim($parts[1]),
                'orderId' => $orderId,
                'startPayUrl' => $this->startPayUrl,
                'resCode' => '0',
                'raw' => $result['return'],
            ];
        }

        Log::warning('Mellat bpPayRequest failed', ['return' => $result['return'], 'orderId' => $orderId]);

        return [
            'success' => false,
            'resCode' => $resCode,
            'message' => $this->resCodeMessage($resCode),
            'raw' => $result['return'],
        ];
    }

    /**
     * تایید + نهایی‌سازی پرداخت (bpVerifyRequest سپس bpSettleRequest).
     *
     * @return array{success:bool, resCode?:string, message?:string}
     */
    public function verifyAndSettle(int $saleOrderId, int $saleReferenceId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'درگاه ملت تنظیم نشده.'];
        }

        $params = [
            'terminalId' => $this->terminalId(),
            'userName' => $this->username(),
            'userPassword' => $this->password(),
            'orderId' => $saleOrderId,
            'saleOrderId' => $saleOrderId,
            'saleReferenceId' => $saleReferenceId,
        ];

        // ۱) verify
        $verify = $this->soapCall('bpVerifyRequest', $params);
        if (! $verify['ok']) {
            return ['success' => false, 'message' => $verify['message']];
        }
        $verifyCode = trim((string) $verify['return']);
        if ($verifyCode !== '0') {
            Log::warning('Mellat verify failed', ['code' => $verifyCode, 'saleOrderId' => $saleOrderId]);
            return ['success' => false, 'resCode' => $verifyCode, 'message' => $this->resCodeMessage($verifyCode)];
        }

        // ۲) settle
        $settle = $this->soapCall('bpSettleRequest', $params);
        if (! $settle['ok']) {
            return ['success' => false, 'message' => $settle['message']];
        }
        $settleCode = trim((string) $settle['return']);
        // 0 یا 45 (قبلاً settle شده) موفق محسوب می‌شوند
        if (in_array($settleCode, ['0', '45'], true)) {
            return ['success' => true, 'resCode' => $settleCode];
        }

        Log::warning('Mellat settle failed', ['code' => $settleCode, 'saleOrderId' => $saleOrderId]);
        return ['success' => false, 'resCode' => $settleCode, 'message' => $this->resCodeMessage($settleCode)];
    }

    /**
     * فراخوانی SOAP خام با Http facade.
     *
     * @return array{ok:bool, return?:string, message?:string, raw?:string}
     */
    protected function soapCall(string $method, array $params): array
    {
        $body = $this->buildEnvelope($method, $params);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '""',
                ])
                ->withBody($body, 'text/xml; charset=utf-8')
                ->post($this->wsdlEndpoint);

            $xml = $response->body();

            // استخراج <return>...</return> از پاسخ SOAP
            if (preg_match('/<return[^>]*>(.*?)<\/return>/s', $xml, $m)) {
                return ['ok' => true, 'return' => $m[1], 'raw' => $xml];
            }

            Log::warning("Mellat {$method} — no <return> in response", ['xml' => mb_substr($xml, 0, 500)]);
            return ['ok' => false, 'message' => 'پاسخ نامعتبر از درگاه ملت.', 'raw' => $xml];
        } catch (\Throwable $e) {
            Log::error("Mellat {$method} exception", ['error' => $e->getMessage()]);
            return ['ok' => false, 'message' => 'خطا در اتصال به درگاه ملت: ' . $e->getMessage()];
        }
    }

    /** ساخت SOAP envelope برای متد و پارامترها. */
    protected function buildEnvelope(string $method, array $params): string
    {
        $inner = '';
        foreach ($params as $key => $value) {
            $val = htmlspecialchars((string) $value, ENT_XML1);
            $inner .= "<{$key}>{$val}</{$key}>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:int="{$this->namespace}">
  <soapenv:Header/>
  <soapenv:Body>
    <int:{$method}>{$inner}</int:{$method}>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /** پیام فارسی برای کدهای خطای ملت. */
    public function resCodeMessage(string $code): string
    {
        $map = [
            '0' => 'تراکنش موفق',
            '11' => 'شماره کارت نامعتبر است',
            '12' => 'موجودی کافی نیست',
            '13' => 'رمز نادرست است',
            '14' => 'تعداد دفعات وارد کردن رمز بیش از حد است',
            '15' => 'کارت نامعتبر است',
            '16' => 'دفعات برداشت وجه بیش از حد مجاز است',
            '17' => 'کاربر از انجام تراکنش منصرف شده است',
            '18' => 'تاریخ انقضای کارت گذشته است',
            '19' => 'مبلغ برداشت وجه بیش از حد مجاز است',
            '111' => 'صادرکننده کارت نامعتبر است',
            '112' => 'خطای سوییچ صادرکننده کارت',
            '113' => 'پاسخی از صادرکننده کارت دریافت نشد',
            '114' => 'دارنده کارت مجاز به انجام این تراکنش نیست',
            '21' => 'پذیرنده نامعتبر است',
            '23' => 'خطای امنیتی رخ داده است',
            '24' => 'اطلاعات کاربری پذیرنده نامعتبر است',
            '25' => 'مبلغ نامعتبر است',
            '31' => 'پاسخ نامعتبر است',
            '32' => 'فرمت اطلاعات وارد شده صحیح نمی‌باشد',
            '33' => 'حساب نامعتبر است',
            '34' => 'خطای سیستمی',
            '35' => 'تاریخ نامعتبر است',
            '41' => 'شماره درخواست تکراری است',
            '42' => 'تراکنش Sale یافت نشد',
            '43' => 'قبلاً درخواست Verify داده شده است',
            '44' => 'درخواست Verify یافت نشد',
            '45' => 'تراکنش Settle شده است',
            '46' => 'تراکنش Settle نشده است',
            '47' => 'تراکنش Settle یافت نشد',
            '48' => 'تراکنش Reverse شده است',
            '49' => 'تراکنش Refund یافت نشد',
            '412' => 'شناسه قبض نادرست است',
            '413' => 'شناسه پرداخت نادرست است',
            '414' => 'سازمان صادرکننده قبض نامعتبر است',
            '415' => 'زمان جلسه کاری به پایان رسیده است',
            '416' => 'خطا در ثبت اطلاعات',
            '417' => 'شناسه پرداخت‌کننده نامعتبر است',
            '418' => 'اشکال در تعریف اطلاعات مشتری',
            '419' => 'تعداد دفعات ورود اطلاعات از حد مجاز گذشته است',
            '421' => 'IP نامعتبر است',
            '51' => 'تراکنش تکراری است',
            '54' => 'تراکنش مرجع موجود نیست',
            '55' => 'تراکنش نامعتبر است',
            '61' => 'خطا در واریز',
        ];

        return $map[$code] ?? ('خطای درگاه ملت — کد: ' . $code);
    }
}
