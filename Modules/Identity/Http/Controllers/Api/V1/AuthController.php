<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Http\Requests\CompleteProfileRequest;
use Modules\Identity\Http\Requests\SendOtpRequest;
use Modules\Identity\Http\Requests\VerifyOtpRequest;
use Modules\Identity\Http\Resources\CustomerResource;
use Modules\Identity\Services\IdentityService;

/**
 * Unified phone + OTP authentication for customers (site / mobile app).
 *
 * Subject: Modules\CRM\Models\Customer (crm_customers جدول)
 * Admin/staff/tech همچنان از سیستم لاگین فعلی خود استفاده می‌کنند.
 *
 * Endpoints (prefix /api/v1/auth):
 *   POST /send-otp         — public
 *   POST /verify-otp       — public  → returns token
 *   POST /complete-profile — auth:sanctum
 *   GET  /me               — auth:sanctum
 *   POST /logout           — auth:sanctum
 *   POST /logout-all       — auth:sanctum
 */
class AuthController extends Controller
{
    public function __construct(private IdentityService $identity) {}

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $result = $this->identity->sendOtp((string) $request->input('mobile'));

        return response()->json([
            'ok' => true,
            'message' => 'کد تأیید ارسال شد.',
            'expires_in' => $result['expires_in'],
            'can_resend_in' => $result['can_resend_in'],
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        // X-Device-ID اختیاری — اپ موبایل می‌فرستد، Next BFF نمی‌فرستد.
        // فقط برای tagging توکن استفاده می‌شود تا بشود لیست دستگاه‌ها را داد.
        $deviceId = trim((string) $request->header('X-Device-ID', ''));

        $result = $this->identity->verifyOtp(
            (string) $request->input('mobile'),
            (string) $request->input('code'),
            $deviceId !== '' ? $deviceId : null,
        );

        $customer = $result['customer'];

        return response()->json([
            'ok' => true,
            'token' => $result['token']->plainTextToken,
            'token_type' => 'Bearer',
            'customer' => new CustomerResource($customer),
            'is_new' => $result['is_new'],
            'needs_profile' => ! $this->identity->isProfileComplete($customer),
        ]);
    }

    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $customer = $this->identity->completeProfile(
            $request->user(),
            (string) $request->input('last_name'),
            $request->input('first_name'),
        );

        return response()->json([
            'ok' => true,
            'customer' => new CustomerResource($customer),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'customer' => new CustomerResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true, 'message' => 'با موفقیت خارج شدید.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['ok' => true, 'message' => 'از همه دستگاه‌ها خارج شدید.']);
    }
}
