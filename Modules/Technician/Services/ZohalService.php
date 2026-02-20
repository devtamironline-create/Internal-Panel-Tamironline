<?php

namespace Modules\Technician\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohalService
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('zohal.base_url');
        $this->token = config('zohal.api_token', '');
        $this->timeout = config('zohal.timeout', 30);
    }

    /**
     * استعلام شاهکار: تطابق شماره موبایل و کد ملی
     */
    public function shahkar(string $mobile, string $nationalCode): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->token,
                ])
                ->post($this->baseUrl . '/services/inquiry/shahkar', [
                    'mobile' => $mobile,
                    'national_code' => $nationalCode,
                ]);

            if ($response->failed()) {
                Log::error('Zohal Shahkar API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'message' => 'خطا در ارتباط با سرویس شاهکار',
                ];
            }

            $data = $response->json();

            if (($data['result'] ?? 0) == 1 && ($data['response_body']['data']['matched'] ?? false)) {
                return [
                    'success' => true,
                    'matched' => true,
                    'message' => 'شماره موبایل و کد ملی مطابقت دارند',
                ];
            }

            return [
                'success' => true,
                'matched' => false,
                'message' => 'شماره موبایل و کد ملی مطابقت ندارند',
            ];
        } catch (\Exception $e) {
            Log::error('Zohal Shahkar exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'خطا در ارتباط با سرویس احراز هویت',
            ];
        }
    }

    /**
     * استعلام هویت: دریافت اطلاعات شناسنامه‌ای با کد ملی و تاریخ تولد
     */
    public function nationalIdentityInquiry(string $nationalCode, string $birthDate): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->token,
                ])
                ->post($this->baseUrl . '/services/inquiry/national_identity_inquiry', [
                    'national_code' => $nationalCode,
                    'birth_date' => $birthDate,
                ]);

            if ($response->failed()) {
                Log::error('Zohal Identity API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'message' => 'خطا در ارتباط با سرویس استعلام هویت',
                ];
            }

            $data = $response->json();
            $body = $data['response_body']['data'] ?? null;

            if (($data['result'] ?? 0) == 1 && ($body['matched'] ?? false)) {
                return [
                    'success' => true,
                    'matched' => true,
                    'first_name' => $body['first_name'] ?? null,
                    'last_name' => $body['last_name'] ?? null,
                    'father_name' => $body['father_name'] ?? null,
                    'message' => 'اطلاعات هویتی تایید شد',
                ];
            }

            return [
                'success' => true,
                'matched' => false,
                'message' => 'کد ملی و تاریخ تولد مطابقت ندارند',
            ];
        } catch (\Exception $e) {
            Log::error('Zohal Identity exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'خطا در ارتباط با سرویس استعلام هویت',
            ];
        }
    }

    /**
     * آپلود مدیا بایومتریک (ویدیو سلفی)
     */
    public function uploadBiometricMedia(string $filePath): array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])
                ->attach('file', file_get_contents($filePath), 'selfie.webm')
                ->post($this->baseUrl . '/services/biometric/media/', [
                    'type' => 'selfie_video',
                ]);

            if ($response->failed()) {
                Log::error('Zohal Biometric Media upload failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'message' => 'خطا در آپلود ویدیو به سرویس احراز هویت',
                ];
            }

            $data = $response->json();

            if (($data['result'] ?? 0) == 1 && isset($data['response_body']['id'])) {
                return [
                    'success' => true,
                    'media_id' => $data['response_body']['id'],
                    'message' => 'ویدیو با موفقیت آپلود شد',
                ];
            }

            return [
                'success' => false,
                'message' => 'پاسخ نامعتبر از سرویس احراز هویت',
            ];
        } catch (\Exception $e) {
            Log::error('Zohal Biometric Media exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'خطا در ارتباط با سرویس احراز هویت',
            ];
        }
    }

    /**
     * ایجاد جلسه Liveness بایومتریک
     */
    public function createLivenessSession(string $mediaId, string $nationalCode, string $birthDate, string $nationalCardSerial, string $callbackUrl): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->token,
                ])
                ->post($this->baseUrl . '/services/biometric/session/liveness/', [
                    'national_code' => $nationalCode,
                    'birth_date' => $birthDate,
                    'national_card_serial' => $nationalCardSerial,
                    'media' => [
                        'selfie_video' => $mediaId,
                    ],
                    'callback_url' => $callbackUrl,
                ]);

            if ($response->failed()) {
                Log::error('Zohal Liveness Session failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'message' => 'خطا در ایجاد جلسه احراز هویت',
                ];
            }

            $data = $response->json();

            if (($data['result'] ?? 0) == 1 && isset($data['response_body']['session_id'])) {
                return [
                    'success' => true,
                    'session_id' => $data['response_body']['session_id'],
                    'status' => $data['response_body']['status'] ?? 'pending',
                    'message' => 'جلسه احراز هویت ایجاد شد',
                ];
            }

            return [
                'success' => false,
                'message' => 'پاسخ نامعتبر از سرویس احراز هویت',
            ];
        } catch (\Exception $e) {
            Log::error('Zohal Liveness Session exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'خطا در ارتباط با سرویس احراز هویت',
            ];
        }
    }

    /**
     * دریافت نتیجه جلسه Liveness
     */
    public function getLivenessResult(string $sessionId): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])
                ->get($this->baseUrl . '/services/biometric/session/' . $sessionId . '/result');

            if ($response->failed()) {
                Log::error('Zohal Liveness Result failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [
                    'success' => false,
                    'message' => 'خطا در دریافت نتیجه احراز هویت',
                ];
            }

            $data = $response->json();

            if (($data['result'] ?? 0) == 1 && isset($data['response_body'])) {
                $body = $data['response_body'];
                return [
                    'success' => true,
                    'status' => $body['status'] ?? 'pending',
                    'result' => $body['result'] ?? null,
                    'reason' => $body['reason'] ?? null,
                    'completed_at' => $body['completed_at'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => 'پاسخ نامعتبر از سرویس احراز هویت',
            ];
        } catch (\Exception $e) {
            Log::error('Zohal Liveness Result exception', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'خطا در ارتباط با سرویس احراز هویت',
            ];
        }
    }
}
