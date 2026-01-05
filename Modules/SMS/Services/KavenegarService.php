<?php

namespace Modules\SMS\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KavenegarService
{
    protected string $apiKey;
    protected string $sender;
    protected string $baseUrl = 'https://api.kavenegar.com/v1';

    public function __construct()
    {
        $this->apiKey = config('sms.kavenegar.api_key') ?? '';
        $this->sender = config('sms.kavenegar.sender') ?? '';
    }

    public function send(string $receptor, string $message): array
    {
        if (empty($this->apiKey) || empty($this->sender)) {
            return [
                'success' => false,
                'message' => 'API Key یا شماره فرستنده تنظیم نشده است'
            ];
        }

        $url = "{$this->baseUrl}/{$this->apiKey}/sms/send.json";

        try {
            $response = Http::timeout(30)->asForm()->post($url, [
                'sender' => $this->sender,
                'receptor' => $receptor,
                'message' => $message,
            ]);

            $body = $response->json();

            if (isset($body['return']['status']) && $body['return']['status'] == 200) {
                Log::info('SMS sent successfully', ['receptor' => $receptor]);
                return [
                    'success' => true,
                    'message' => 'پیامک با موفقیت ارسال شد',
                    'data' => $body['entries'] ?? null
                ];
            }

            Log::warning('SMS send failed', ['receptor' => $receptor, 'response' => $body]);
            return [
                'success' => false,
                'message' => $body['return']['message'] ?? 'خطا در ارسال پیامک'
            ];

        } catch (\Exception $e) {
            Log::error('SMS Exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function sendTemplate(string $receptor, string $template, array $tokens): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'message' => 'API Key تنظیم نشده است'
            ];
        }

        $url = "{$this->baseUrl}/{$this->apiKey}/verify/lookup.json";

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

        try {
            $response = Http::timeout(30)->asForm()->post($url, $data);
            $body = $response->json();

            if (isset($body['return']['status']) && $body['return']['status'] == 200) {
                Log::info('Template SMS sent', ['receptor' => $receptor, 'template' => $template]);
                return [
                    'success' => true,
                    'message' => 'پیامک با موفقیت ارسال شد',
                    'data' => $body['entries'] ?? null
                ];
            }

            Log::warning('Template SMS failed', ['receptor' => $receptor, 'response' => $body]);
            return [
                'success' => false,
                'message' => $body['return']['message'] ?? 'خطا در ارسال پیامک'
            ];

        } catch (\Exception $e) {
            Log::error('Template SMS Exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function sendOTP(string $receptor, string $code, string $template = 'verify'): array
    {
        return $this->sendTemplate($receptor, $template, ['token' => $code]);
    }
}
