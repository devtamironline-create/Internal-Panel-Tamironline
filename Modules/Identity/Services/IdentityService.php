<?php

namespace Modules\Identity\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Modules\CRM\Models\Customer;
use Modules\Identity\Support\PhoneNormalizer;
use Modules\SMS\Services\KavenegarService;
use Modules\SMS\Services\OTPService;

/**
 * Single source of truth for unified phone+OTP authentication of customers.
 *
 * Auth subject: Modules\CRM\Models\Customer (crm_customers جدول)
 * Admin/staff: همچنان از App\Models\User
 * تکنسین: همچنان از Modules\CRM\Models\Technician
 *
 * Flow:
 *   1. sendOtp($mobile)              → OTP ارسال + cache rate limit
 *   2. verifyOtp($mobile, $code)     → Customer + Sanctum token جدید
 *   3. completeProfile($c, $first, $last) → ست کردن نام و نام خانوادگی
 *   4. logout($token)                → revoke توکن
 *
 * تمام شماره‌ها قبل از هر کاری از PhoneNormalizer می‌گذرند.
 */
final class IdentityService
{
    public const TOKEN_NAME = 'customer-token';

    /** Rate-limit per phone — حداکثر در ساعت چند OTP می‌توان فرستاد. */
    public const MAX_OTP_PER_HOUR_PER_PHONE = 5;

    public function __construct(
        private OTPService $otp,
        private KavenegarService $sms,
    ) {}

    /**
     * ارسال OTP به شماره موبایل.
     *
     * @return array{ok: bool, expires_in: int, can_resend_in: int}
     *
     * @throws ValidationException
     */
    public function sendOtp(string $mobile): array
    {
        $normalized = PhoneNormalizer::normalize($mobile);
        if ($normalized === null) {
            throw ValidationException::withMessages(['mobile' => 'شماره موبایل نامعتبر است.']);
        }

        $hourKey = 'identity:otp_hourly:'.$normalized;
        $hourCount = (int) Cache::get($hourKey, 0);
        if ($hourCount >= self::MAX_OTP_PER_HOUR_PER_PHONE) {
            throw ValidationException::withMessages([
                'mobile' => 'تعداد درخواست OTP زیاد است. لطفاً یک ساعت دیگر تلاش کنید.',
            ]);
        }

        $result = $this->otp->sendOTP($normalized);

        if (! ($result['success'] ?? false)) {
            throw ValidationException::withMessages([
                'mobile' => $result['message'] ?? 'ارسال OTP ناموفق بود.',
            ]);
        }

        Cache::put($hourKey, $hourCount + 1, now()->addHour());

        return [
            'ok' => true,
            'expires_in' => (int) config('sms.otp.expires', 120),
            'can_resend_in' => (int) config('sms.otp.resend_delay', 60),
        ];
    }

    /**
     * تأیید OTP — اگر مشتری نباشد ساخته می‌شود، توکن Sanctum برمی‌گرداند.
     *
     * @return array{customer: Customer, token: NewAccessToken, is_new: bool}
     *
     * @throws ValidationException
     */
    public function verifyOtp(string $mobile, string $code): array
    {
        $normalized = PhoneNormalizer::normalize($mobile);
        if ($normalized === null) {
            throw ValidationException::withMessages(['mobile' => 'شماره موبایل نامعتبر است.']);
        }

        $verification = $this->otp->verifyOTP($normalized, trim($code));
        if (! ($verification['success'] ?? false)) {
            throw ValidationException::withMessages([
                'code' => $verification['message'] ?? 'کد تأیید نامعتبر است.',
            ]);
        }

        $customer = Customer::query()->byMobile($normalized)->first();
        $isNew = false;

        if (! $customer) {
            $customer = Customer::query()->create([
                'mobile' => $normalized,
                'mobile_verified_at' => now(),
                'is_active' => true,
            ]);
            $isNew = true;
        } elseif (! $customer->mobile_verified_at) {
            $customer->forceFill(['mobile_verified_at' => now()])->save();
        }

        if (! $customer->isActive()) {
            throw ValidationException::withMessages([
                'mobile' => 'حساب شما غیرفعال است. با پشتیبانی تماس بگیرید.',
            ]);
        }

        $customer->recordLogin(request()?->ip());

        $token = $customer->createToken(self::TOKEN_NAME, ['*']);

        return [
            'customer' => $customer->fresh(),
            'token' => $token,
            'is_new' => $isNew,
        ];
    }

    /**
     * ست کردن first_name / last_name (اولین بار یا تغییر).
     */
    public function completeProfile(Customer $customer, string $firstName, ?string $lastName = null): Customer
    {
        $customer->forceFill([
            'first_name' => trim($firstName),
            'last_name' => $lastName ? trim($lastName) : null,
        ])->save();

        return $customer->fresh();
    }

    /**
     * آیا پروفایل مشتری کامل است؟ (حداقل first_name)
     */
    public function isProfileComplete(Customer $customer): bool
    {
        return ! empty($customer->first_name);
    }
}
