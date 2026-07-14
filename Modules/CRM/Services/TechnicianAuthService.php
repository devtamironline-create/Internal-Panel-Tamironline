<?php

namespace Modules\CRM\Services;

use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Modules\CRM\Models\Technician;
use Modules\Identity\Support\PhoneNormalizer;
use Modules\SMS\Services\OTPService;

/**
 * احراز هویتِ تکنسین برای اپِ موبایل/PWA — موبایل + OTP → توکنِ Sanctum.
 *
 * برخلافِ مشتری، تکنسین self-register نمی‌شود: باید از قبل توسطِ ادمین ساخته و
 * status=active باشد. اگر شماره متعلق به تکنسینِ فعال نباشد، OTP فرستاده نمی‌شود.
 */
class TechnicianAuthService
{
    public const TOKEN_NAME = 'tech-app-token';

    public function __construct(private OTPService $otp) {}

    /**
     * ارسالِ OTP به تکنسینِ فعال. کد هرگز در پاسخ برنمی‌گردد.
     *
     * @return array{ok: bool, expires_in: int, can_resend_in: int}
     *
     * @throws ValidationException
     */
    public function sendOtp(string $mobile): array
    {
        $normalized = $this->normalize($mobile);

        if (! $this->activeTechnicianByMobile($normalized)) {
            throw ValidationException::withMessages([
                'mobile' => 'این شماره متعلق به هیچ تکنسینِ فعالی نیست.',
            ]);
        }

        $result = $this->otp->send($normalized);
        if (! ($result['success'] ?? false)) {
            $errors = ['mobile' => $result['message'] ?? 'ارسال کد ناموفق بود.'];
            if (! empty($result['wait_time'])) {
                $errors['wait_time'] = (string) (int) $result['wait_time'];
            }
            throw ValidationException::withMessages($errors);
        }

        return [
            'ok' => true,
            'expires_in' => (int) ($result['expires_in'] ?? config('sms.otp.expires_in', 120)),
            'can_resend_in' => (int) config('sms.otp.resend_delay', 60),
        ];
    }

    /**
     * تأییدِ OTP → تکنسینِ فعال + توکنِ Sanctum.
     *
     * @return array{technician: Technician, token: NewAccessToken}
     *
     * @throws ValidationException
     */
    public function verifyOtp(string $mobile, string $code, ?string $deviceId = null): array
    {
        $normalized = $this->normalize($mobile);

        $verification = $this->otp->verify($normalized, trim($code));
        if (! ($verification['success'] ?? false)) {
            throw ValidationException::withMessages([
                'code' => $verification['message'] ?? 'کد تأیید نامعتبر است.',
            ]);
        }

        $technician = $this->activeTechnicianByMobile($normalized);
        if (! $technician) {
            throw ValidationException::withMessages([
                'mobile' => 'این شماره متعلق به هیچ تکنسینِ فعالی نیست.',
            ]);
        }

        $token = $technician->createToken(self::TOKEN_NAME, ['*']);

        if ($deviceId !== null && $deviceId !== '' && $this->isValidDeviceId($deviceId)) {
            $token->accessToken->forceFill([
                'device_id' => substr($deviceId, 0, 64),
                'last_used_ip' => request()?->ip(),
            ])->save();
        }

        return ['technician' => $technician->fresh(), 'token' => $token];
    }

    private function normalize(string $mobile): string
    {
        $normalized = PhoneNormalizer::normalize($mobile);
        if ($normalized === null) {
            throw ValidationException::withMessages(['mobile' => 'شماره موبایل نامعتبر است.']);
        }

        return $normalized;
    }

    /** تکنسینِ فعال با این موبایل (یا null). شماره‌ها هم به‌صورتِ 09... و هم بدونِ صفر امتحان می‌شوند. */
    private function activeTechnicianByMobile(string $normalized): ?Technician
    {
        $candidates = array_unique(array_filter([
            $normalized,
            ltrim($normalized, '0'),
            '0'.ltrim($normalized, '0'),
        ]));

        return Technician::query()
            ->whereIn('mobile', $candidates)
            ->where('status', 'active')
            ->first();
    }

    private function isValidDeviceId(string $id): bool
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id);
    }
}
