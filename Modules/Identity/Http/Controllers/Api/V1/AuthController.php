<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Http\Requests\CompleteProfileRequest;
use Modules\Identity\Http\Requests\SendOtpRequest;
use Modules\Identity\Http\Requests\VerifyOtpRequest;
use Modules\Identity\Http\Resources\UserResource;
use Modules\Identity\Services\IdentityService;

/**
 * Unified phone + OTP authentication for site / mobile app / 3rd-party clients.
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
        $result = $this->identity->verifyOtp(
            (string) $request->input('mobile'),
            (string) $request->input('code'),
        );

        $user = $result['user'];

        return response()->json([
            'ok' => true,
            'token' => $result['token']->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
            'is_new' => $result['is_new'],
            'needs_profile' => ! $this->identity->isProfileComplete($user),
        ]);
    }

    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $user = $this->identity->completeProfile(
            $request->user(),
            (string) $request->input('first_name'),
            $request->input('last_name'),
        );

        return response()->json([
            'ok' => true,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'user' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // فقط توکن فعلی را revoke می‌کنیم
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true, 'message' => 'با موفقیت خارج شدید.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        // تمام توکن‌های این کاربر را revoke می‌کند (همه دستگاه‌ها)
        $request->user()->tokens()->delete();

        return response()->json(['ok' => true, 'message' => 'از همه دستگاه‌ها خارج شدید.']);
    }
}
