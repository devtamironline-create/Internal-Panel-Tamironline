<?php

namespace Modules\Identity\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Modules\Identity\Support\PhoneNormalizer;
use Modules\SMS\Services\KavenegarService;
use Modules\SMS\Services\OTPService;

/**
 * Single source of truth for unified phone+OTP authentication.
 *
 * Flow (web/app):
 *   1. sendOtp($mobile)              → کاربر را پیدا/ایجاد، OTP ارسال
 *   2. verifyOtp($mobile, $code)     → User + token جدید
 *   3. completeProfile($u, names)    → اولین بار نام را ست می‌کند
 *   4. logout($token)                → توکن را revoke می‌کند
 *
 * تمام شماره‌ها قبل از هر کاری از PhoneNormalizer می‌گذرند.
 * OTPService موجود (Modules/SMS) برای generation/validation استفاده می‌شود.
 */
final class IdentityService
{
    public const TOKEN_NAME = 'identity-token';

    /**
     * Rate-limit per phone — حداکثر در ساعت چند OTP می‌توان فرستاد.
     * مکمل rate-limit IP-based در routes.
     */
    public const MAX_OTP_PER_HOUR_PER_PHONE = 5;

    public function __construct(
        private OTPService $otp,
        private KavenegarService $sms,
    ) {}

    /**
     * ارسال OTP به شماره موبایل.
     *
     *
     * @return array{ok: bool, expires_in: int, can_resend_in: int}
     *
     * @throws ValidationException اگر شماره نامعتبر باشد یا rate limit رد شده باشد
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
     * تأیید OTP — اگر کاربر نباشد ساخته می‌شود، توکن Sanctum برمی‌گرداند.
     *
     *
     * @return array{user: User, token: NewAccessToken, is_new: bool}
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

        $user = User::query()->byMobile($normalized)->first();
        $isNew = false;

        if (! $user) {
            $user = User::query()->create([
                'mobile' => $normalized,
                'mobile_verified_at' => now(),
                'is_active' => true,
                'is_staff' => false,
            ]);
            $isNew = true;

            $this->assignDefaultCustomerRole($user);
        } elseif (! $user->mobile_verified_at) {
            $user->forceFill(['mobile_verified_at' => now()])->save();
        }

        $user->recordLogin(request()?->ip());

        $token = $user->createToken(self::TOKEN_NAME, ['*']);

        return [
            'user' => $user->fresh(),
            'token' => $token,
            'is_new' => $isNew,
        ];
    }

    /**
     * ست کردن first_name / last_name (اولین بار یا تغییر).
     */
    public function completeProfile(User $user, string $firstName, ?string $lastName = null): User
    {
        $user->forceFill([
            'first_name' => trim($firstName),
            'last_name' => $lastName ? trim($lastName) : null,
        ])->save();

        return $user->fresh();
    }

    /**
     * آیا پروفایل کاربر کامل است؟ (حداقل first_name)
     */
    public function isProfileComplete(User $user): bool
    {
        return ! empty($user->first_name);
    }

    /**
     * نقش پیش‌فرض customer را به کاربر می‌دهد (در صورت وجود).
     * این به‌صورت silent fail است تا اگر spatie permission setup نباشد، ثبت‌نام نشکند.
     */
    private function assignDefaultCustomerRole(User $user): void
    {
        try {
            if (\Spatie\Permission\Models\Role::query()->where('name', 'customer')->exists()) {
                $user->assignRole('customer');
            }
        } catch (\Throwable $e) {
            // ignore — role optional
        }
    }
}
