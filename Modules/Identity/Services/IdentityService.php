<?php

namespace Modules\Identity\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     * توجه امنیتی: کد OTP هرگز در response برنمی‌گردد — حتی در local. برای
     * dev/test از storage/logs/laravel.log استفاده کنید (OTPService در local
     * هر کد را با Log::info می‌نویسد). این جلوگیری از حادثه‌ی APP_ENV اشتباه
     * در deploy تولید است.
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

        // ─── حالت تست — Kavenegar صدا نمی‌خورد، فقط success برمی‌گردد ──
        // فقط زمانی فعال که env CUSTOMER_APP_TEST_MODE=true و شماره در
        // allowlist (در صورت تعریف) باشد. در production همیشه false.
        if ($this->isTestModeFor($normalized)) {
            \Illuminate\Support\Facades\Log::warning('identity.test_mode_send_otp', [
                'mobile' => $normalized,
                'master_otp' => config('customerapp.test-mode.master_otp'),
            ]);

            return [
                'ok' => true,
                'expires_in' => (int) config('sms.otp.expires_in', 120),
                'can_resend_in' => (int) config('sms.otp.resend_delay', 60),
                'test_mode' => true,
            ];
        }

        $hourKey = 'identity:otp_hourly:'.$normalized;
        $hourCount = (int) Cache::get($hourKey, 0);
        if ($hourCount >= self::MAX_OTP_PER_HOUR_PER_PHONE) {
            throw ValidationException::withMessages([
                'mobile' => 'تعداد درخواست OTP زیاد است. لطفاً یک ساعت دیگر تلاش کنید.',
            ]);
        }

        $result = $this->otp->send($normalized);

        if (! ($result['success'] ?? false)) {
            // اگر throttle داخلی OTPService بود، wait_time را surface کن
            $errors = ['mobile' => $result['message'] ?? 'ارسال OTP ناموفق بود.'];
            if (! empty($result['wait_time'])) {
                $errors['wait_time'] = (string) (int) $result['wait_time'];
            }
            throw ValidationException::withMessages($errors);
        }

        Cache::put($hourKey, $hourCount + 1, now()->addHour());

        return [
            'ok' => true,
            'expires_in' => (int) ($result['expires_in'] ?? config('sms.otp.expires_in', 120)),
            'can_resend_in' => (int) config('sms.otp.resend_delay', 60),
        ];
    }

    /**
     * تأیید OTP — اگر مشتری نباشد ساخته می‌شود، توکن Sanctum برمی‌گرداند.
     *
     * $deviceId اختیاری: مقدار X-Device-ID که فرانت اپ موبایل می‌فرستد.
     * در ستون device_id روی personal_access_tokens ذخیره می‌شود تا بشود لیست
     * دستگاه‌ها را نمایش داد یا فقط یکی را revoke کرد. اگر null باشد، توکن
     * بدون device_id ذخیره می‌شود (web/Next BFF/فعلاً غیرضروری).
     *
     * @return array{customer: Customer, token: NewAccessToken, is_new: bool}
     *
     * @throws ValidationException
     */
    public function verifyOtp(string $mobile, string $code, ?string $deviceId = null): array
    {
        $normalized = PhoneNormalizer::normalize($mobile);
        if ($normalized === null) {
            throw ValidationException::withMessages(['mobile' => 'شماره موبایل نامعتبر است.']);
        }

        $trimmedCode = trim($code);

        // ─── حالت تست — اگر کد === master_otp باشد، تأیید واقعی skip می‌شود ──
        $isTestBypass = $this->isTestModeFor($normalized)
            && $trimmedCode !== ''
            && hash_equals((string) config('customerapp.test-mode.master_otp'), $trimmedCode);

        if ($isTestBypass) {
            \Illuminate\Support\Facades\Log::warning('identity.test_mode_verify_otp', [
                'mobile' => $normalized,
            ]);
        } else {
            $verification = $this->otp->verify($normalized, $trimmedCode);
            if (! ($verification['success'] ?? false)) {
                throw ValidationException::withMessages([
                    'code' => $verification['message'] ?? 'کد تأیید نامعتبر است.',
                ]);
            }
        }

        // race-safe upsert — unique(mobile) + transaction جلوی duplicate در
        // درخواست‌های همزمان را می‌گیرد.
        [$customer, $isNew] = DB::transaction(function () use ($normalized): array {
            // withTrashed: حسابِ حذف‌شده (soft delete از اپ) نباید دوباره ساخته
            // شود (unique mobile می‌شکست) و نباید بی‌سروصدا برگردد — بازگردانی
            // فقط توسط ادمین از پنل انجام می‌شود.
            $existing = Customer::query()->withTrashed()->byMobile($normalized)->lockForUpdate()->first();
            if ($existing && $existing->trashed()) {
                throw ValidationException::withMessages([
                    'mobile' => 'این حساب به درخواستِ شما حذف شده است. برای بازگردانی با پشتیبانی تماس بگیرید.',
                ]);
            }
            if ($existing) {
                if (! $existing->mobile_verified_at) {
                    $existing->forceFill(['mobile_verified_at' => now()])->save();
                }

                return [$existing, false];
            }

            $created = Customer::query()->create([
                'mobile' => $normalized,
                'mobile_verified_at' => now(),
                'is_active' => true,
            ]);

            return [$created, true];
        });

        if (! $customer->isActive()) {
            throw ValidationException::withMessages([
                'mobile' => 'حساب شما غیرفعال است. با پشتیبانی تماس بگیرید.',
            ]);
        }

        $customer->recordLogin(request()?->ip());

        $token = $customer->createToken(self::TOKEN_NAME, ['*']);

        // ذخیره‌ی device_id روی Sanctum PAT — Sanctum API مستقیماً این فیلد را
        // نمی‌سازد، پس بعد از createToken روی همان رکورد update می‌کنیم.
        // دسترسی به ip مستقیماً از request() گرفته می‌شود.
        if ($deviceId !== null && $deviceId !== '' && $this->isValidDeviceId($deviceId)) {
            $token->accessToken->forceFill([
                'device_id' => substr($deviceId, 0, 64),
                'last_used_ip' => request()?->ip(),
            ])->save();
        }

        return [
            'customer' => $customer->fresh(),
            'token' => $token,
            'is_new' => $isNew,
        ];
    }

    /**
     * ورود از مینی‌اپِ بله — initData از قبل توسطِ BaleInitData اعتبارسنجی شده.
     *
     * نگاشت: اول با bale_user_id (ورودهای بعدی حتی بدونِ شماره)، سپس با شمارهٔ
     * به‌اشتراک‌گذاشته‌شده (upsert مثلِ verifyOtp — شمارهٔ بله verified حساب
     * می‌شود چون بله آن را احراز کرده). بدونِ هیچ‌کدام → 422 (کاربر با OTP وارد شود).
     *
     * @param  array<string, mixed>  $baleUser  آبجکتِ user داخلِ initData
     * @return array{customer: Customer, token: NewAccessToken, is_new: bool}
     */
    public function loginWithBale(array $baleUser, ?string $deviceId = null): array
    {
        $baleUserId = (int) ($baleUser['id'] ?? 0);
        $normalized = PhoneNormalizer::normalize((string) ($baleUser['phone_number'] ?? ''));

        [$customer, $isNew] = DB::transaction(function () use ($baleUser, $baleUserId, $normalized): array {
            // ۱) نگاشتِ قبلی با شناسهٔ بله
            if ($baleUserId > 0) {
                $mapped = Customer::query()->withTrashed()
                    ->where('bale_user_id', $baleUserId)->lockForUpdate()->first();
                if ($mapped && $mapped->trashed()) {
                    throw ValidationException::withMessages([
                        'init_data' => 'این حساب به درخواستِ شما حذف شده است. برای بازگردانی با پشتیبانی تماس بگیرید.',
                    ]);
                }
                if ($mapped) {
                    return [$mapped, false];
                }
            }

            // ۲) با شمارهٔ به‌اشتراک‌گذاشته‌شده در بله
            if ($normalized === null || $normalized === '') {
                throw ValidationException::withMessages([
                    'init_data' => 'شمارهٔ موبایل در بله به اشتراک گذاشته نشده است؛ لطفاً با کدِ یک‌بارمصرف وارد شوید.',
                ]);
            }

            $existing = Customer::query()->withTrashed()->byMobile($normalized)->lockForUpdate()->first();
            if ($existing && $existing->trashed()) {
                throw ValidationException::withMessages([
                    'mobile' => 'این حساب به درخواستِ شما حذف شده است. برای بازگردانی با پشتیبانی تماس بگیرید.',
                ]);
            }
            if ($existing) {
                $fill = [];
                if (! $existing->mobile_verified_at) {
                    $fill['mobile_verified_at'] = now();
                }
                if ($baleUserId > 0 && ! $existing->bale_user_id) {
                    $fill['bale_user_id'] = $baleUserId;
                }
                if ($fill !== []) {
                    $existing->forceFill($fill)->save();
                }

                return [$existing, false];
            }

            $created = Customer::query()->create([
                'mobile' => $normalized,
                'mobile_verified_at' => now(),
                'is_active' => true,
                'bale_user_id' => $baleUserId > 0 ? $baleUserId : null,
                'first_name' => $baleUser['first_name'] ?? null,
                'last_name' => $baleUser['last_name'] ?? null,
            ]);

            return [$created, true];
        });

        if (! $customer->isActive()) {
            throw ValidationException::withMessages([
                'mobile' => 'حساب شما غیرفعال است. با پشتیبانی تماس بگیرید.',
            ]);
        }

        $customer->recordLogin(request()?->ip());

        $token = $customer->createToken(self::TOKEN_NAME, ['*']);
        if ($deviceId !== null && $deviceId !== '' && $this->isValidDeviceId($deviceId)) {
            $token->accessToken->forceFill([
                'device_id' => substr($deviceId, 0, 64),
                'last_used_ip' => request()?->ip(),
            ])->save();
        }

        return [
            'customer' => $customer->fresh(),
            'token' => $token,
            'is_new' => $isNew,
        ];
    }

    /**
     * آیا حالت تست برای این شماره فعال است؟
     *
     * شرایط:
     *   - env CUSTOMER_APP_TEST_MODE=true
     *   - شماره در allowed_mobiles (در صورت تعریف) باشد، یا allowlist خالی باشد
     */
    private function isTestModeFor(string $normalizedMobile): bool
    {
        if (! config('customerapp.test-mode.enabled', false)) {
            return false;
        }

        $allowed = (array) config('customerapp.test-mode.allowed_mobiles', []);
        if (empty($allowed)) {
            return true; // allowlist خالی → همه شماره‌ها در حالت تست قبول
        }

        return in_array($normalizedMobile, $allowed, true);
    }

    /**
     * اعتبارسنجی X-Device-ID — UUID v4 یا alpha-num محدود طول‌دار.
     */
    private function isValidDeviceId(string $id): bool
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id);
    }

    /**
     * ست کردن نام خانوادگی (الزامی) و نام (اختیاری).
     */
    public function completeProfile(Customer $customer, string $lastName, ?string $firstName = null): Customer
    {
        $attrs = ['last_name' => trim($lastName) ?: $customer->last_name];
        // نام فقط در صورت ارسالِ مقدار غیرخالی به‌روزرسانی می‌شود (اختیاری).
        if ($firstName !== null && trim($firstName) !== '') {
            $attrs['first_name'] = trim($firstName);
        }
        $customer->forceFill($attrs)->save();

        return $customer->fresh();
    }

    /**
     * آیا پروفایل مشتری کامل است؟ (حداقل نام خانوادگی)
     */
    public function isProfileComplete(Customer $customer): bool
    {
        return ! empty($customer->last_name);
    }
}
