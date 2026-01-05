<?php

namespace Modules\SMS\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OTPService
{
    protected KavenegarService $sms;
    protected int $length;
    protected int $expiresIn;
    protected int $maxAttempts;
    protected int $resendDelay;

    public function __construct(KavenegarService $sms)
    {
        $this->sms = $sms;
        $this->length = config('sms.otp.length', 6);
        $this->expiresIn = config('sms.otp.expires_in', 120);
        $this->maxAttempts = config('sms.otp.max_attempts', 5);
        $this->resendDelay = config('sms.otp.resend_delay', 60);
    }

    public function generate(): string
    {
        return str_pad(random_int(0, pow(10, $this->length) - 1), $this->length, '0', STR_PAD_LEFT);
    }

    public function send(string $mobile): array
    {
        $mobile = $this->normalizeMobile($mobile);
        
        $lastSentKey = "otp_last_sent_{$mobile}";
        $lastSent = Cache::get($lastSentKey);
        
        if ($lastSent) {
            $waitTime = $this->resendDelay - (time() - $lastSent);
            if ($waitTime > 0) {
                return [
                    'success' => false,
                    'message' => "لطفاً {$waitTime} ثانیه صبر کنید",
                    'wait_time' => $waitTime
                ];
            }
        }

        $code = $this->generate();
        $cacheKey = "otp_{$mobile}";
        
        Cache::put($cacheKey, [
            'code' => $code,
            'attempts' => 0,
            'created_at' => time()
        ], $this->expiresIn);
        
        Cache::put($lastSentKey, time(), $this->resendDelay);

        // در محیط توسعه، کد رو لاگ کن
        if (app()->environment('local')) {
            Log::info("OTP Code for {$mobile}: {$code}");
        }

        $template = config('sms.templates.otp', 'verify');
        $result = $this->sms->sendTemplate($mobile, $template, ['token' => $code]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'کد تایید ارسال شد',
                'expires_in' => $this->expiresIn,
                // در حالت debug کد رو برگردون
                'debug_code' => app()->environment('local') ? $code : null
            ];
        }

        Cache::forget($cacheKey);
        
        return [
            'success' => false,
            'message' => $result['message'] ?? 'خطا در ارسال کد تایید'
        ];
    }

    public function verify(string $mobile, string $code): array
    {
        $mobile = $this->normalizeMobile($mobile);
        $cacheKey = "otp_{$mobile}";
        
        $otpData = Cache::get($cacheKey);
        
        if (!$otpData) {
            return [
                'success' => false,
                'message' => 'کد تایید منقضی شده است'
            ];
        }

        if ($otpData['attempts'] >= $this->maxAttempts) {
            Cache::forget($cacheKey);
            return [
                'success' => false,
                'message' => 'تعداد تلاش‌های مجاز تمام شد'
            ];
        }

        if ($otpData['code'] !== $code) {
            $otpData['attempts']++;
            Cache::put($cacheKey, $otpData, $this->expiresIn);
            
            $remaining = $this->maxAttempts - $otpData['attempts'];
            return [
                'success' => false,
                'message' => "کد تایید اشتباه است ({$remaining} تلاش باقی‌مانده)"
            ];
        }

        Cache::forget($cacheKey);

        return [
            'success' => true,
            'message' => 'تایید موفق'
        ];
    }

    protected function normalizeMobile(string $mobile): string
    {
        $mobile = preg_replace('/[^0-9]/', '', $mobile);
        
        if (strlen($mobile) === 10 && str_starts_with($mobile, '9')) {
            $mobile = '0' . $mobile;
        } elseif (str_starts_with($mobile, '98')) {
            $mobile = '0' . substr($mobile, 2);
        } elseif (str_starts_with($mobile, '+98')) {
            $mobile = '0' . substr($mobile, 3);
        }
        
        return $mobile;
    }

    public function canResend(string $mobile): array
    {
        $mobile = $this->normalizeMobile($mobile);
        $lastSentKey = "otp_last_sent_{$mobile}";
        $lastSent = Cache::get($lastSentKey);
        
        if (!$lastSent) {
            return ['can_resend' => true, 'wait_time' => 0];
        }
        
        $waitTime = $this->resendDelay - (time() - $lastSent);
        
        return [
            'can_resend' => $waitTime <= 0,
            'wait_time' => max(0, $waitTime)
        ];
    }
}
