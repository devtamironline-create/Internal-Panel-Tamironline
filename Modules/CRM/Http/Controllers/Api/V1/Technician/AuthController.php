<?php

namespace Modules\CRM\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Http\Resources\TechnicianResource;
use Modules\CRM\Services\TechnicianAuthService;

/**
 * احراز هویتِ اپِ تکنسین — موبایل + OTP → توکنِ Sanctum.
 *
 * Endpoints (prefix /v1/technician/auth):
 *   POST /send-otp   — public (throttle:otp-send)
 *   POST /verify-otp — public (throttle:otp-verify) → token
 *   GET  /me         — auth:sanctum + tech
 *   POST /logout     — auth:sanctum + tech
 */
class AuthController extends Controller
{
    public function __construct(private TechnicianAuthService $auth) {}

    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate(['mobile' => 'required|string|max:20']);

        $result = $this->auth->sendOtp((string) $request->input('mobile'));

        return response()->json([
            'success' => true,
            'message' => 'کد تأیید ارسال شد.',
            'data' => [
                'expires_in' => $result['expires_in'],
                'can_resend_in' => $result['can_resend_in'],
            ],
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'mobile' => 'required|string|max:20',
            'code' => 'required|string|max:10',
        ]);

        $deviceId = trim((string) $request->header('X-Device-ID', ''));

        $result = $this->auth->verifyOtp(
            (string) $request->input('mobile'),
            (string) $request->input('code'),
            $deviceId !== '' ? $deviceId : null,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $result['token']->plainTextToken,
                'token_type' => 'Bearer',
                'technician' => new TechnicianResource($result['technician']),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['technician' => new TechnicianResource($request->user())],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'با موفقیت خارج شدید.']);
    }
}
