<?php

namespace Modules\SMS\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KavenegarService
{
    protected string $apiKey;
    protected string $sender;
    protected string $baseUrl = 'https://api.kavenegar.com/v1';
    protected bool $proxyEnabled;
    protected string $proxyUrl;
    protected string $proxySecret;

    public function __construct()
    {
        $this->apiKey = config('sms.kavenegar.api_key') ?? '';
        $this->sender = config('sms.kavenegar.sender') ?? '';
        $this->proxyEnabled = (bool) config('sms.proxy.enabled', false);
        $this->proxyUrl = rtrim(config('sms.proxy.url', ''), '/');
        $this->proxySecret = config('sms.proxy.secret', '');
    }

    public function send(string $receptor, string $message): array
    {
        if (empty($this->apiKey) || empty($this->sender)) {
            return ['success' => false, 'message' => 'API Key یا شماره فرستنده تنظیم نشده است'];
        }

        if ($this->proxyEnabled && !empty($this->proxyUrl)) {
            return $this->sendViaProxy('send', [
                'api_key' => $this->apiKey,
                'receptor' => $receptor,
                'message' => $message,
                'sender' => $this->sender,
            ]);
        }

        $url = "{$this->baseUrl}/{$this->apiKey}/sms/send.json";

        try {
            $response = Http::timeout(30)->asForm()->post($url, [
                'sender' => $this->sender,
                'receptor' => $receptor,
                'message' => $message,
            ]);

            return $this->parseKavenegarResponse($response->json(), $receptor, 'send');
        } catch (\Exception $e) {
            Log::error('SMS Exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function sendTemplate(string $receptor, string $template, array $tokens): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'message' => 'API Key تنظیم نشده است'];
        }

        $data = [
            'receptor' => $receptor,
            'template' => $template,
        ];

        $tokenKeys = ['token', 'token2', 'token3', 'token10', 'token20'];
        foreach ($tokenKeys as $key) {
            if (isset($tokens[$key]) && !empty($tokens[$key])) {
                $data[$key] = str_replace(' ', '.', $tokens[$key]);
            }
        }

        if ($this->proxyEnabled && !empty($this->proxyUrl)) {
            $data['api_key'] = $this->apiKey;
            return $this->sendViaProxy('verify', $data);
        }

        $url = "{$this->baseUrl}/{$this->apiKey}/verify/lookup.json";

        try {
            $response = Http::timeout(30)->asForm()->post($url, $data);
            return $this->parseKavenegarResponse($response->json(), $receptor, 'template:' . $template);
        } catch (\Exception $e) {
            Log::error('Template SMS Exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function sendOTP(string $receptor, string $code, string $template = 'verify'): array
    {
        return $this->sendTemplate($receptor, $template, ['token' => $code]);
    }

    /**
     * ارسال از طریق سرور پروکسی
     */
    protected function sendViaProxy(string $action, array $params): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Proxy-Secret' => $this->proxySecret,
                ])
                ->post($this->proxyUrl . '/sms-proxy.php', [
                    'action' => $action,
                    'params' => $params,
                ]);

            $body = $response->json();

            if (isset($body['success']) && $body['success'] === true) {
                Log::info('SMS sent via proxy', [
                    'action' => $action,
                    'receptor' => $params['receptor'] ?? '',
                ]);
                return [
                    'success' => true,
                    'message' => 'پیامک از طریق پروکسی ارسال شد',
                    'data' => $body['data'] ?? null,
                ];
            }

            $message = $body['message'] ?? 'خطا در ارسال از طریق پروکسی';
            Log::warning('SMS proxy failed', [
                'action' => $action,
                'receptor' => $params['receptor'] ?? '',
                'message' => $message,
            ]);
            return ['success' => false, 'message' => $message];

        } catch (\Exception $e) {
            Log::error('SMS Proxy Exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'خطای پروکسی: ' . $e->getMessage()];
        }
    }

    protected function parseKavenegarResponse(?array $body, string $receptor, string $type): array
    {
        if (isset($body['return']['status']) && $body['return']['status'] == 200) {
            Log::info('SMS sent', ['receptor' => $receptor, 'type' => $type]);
            return [
                'success' => true,
                'message' => 'پیامک با موفقیت ارسال شد',
                'data' => $body['entries'] ?? null,
            ];
        }

        Log::warning('SMS failed', ['receptor' => $receptor, 'type' => $type, 'response' => $body]);
        return [
            'success' => false,
            'message' => $body['return']['message'] ?? 'خطا در ارسال پیامک',
        ];
    }
}
