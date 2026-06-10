<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\CRM\Models\Customer;

/**
 * مدیریت دستگاه‌های فعال (session ها) برای اپ موبایل.
 *
 *   GET    /v1/customer/auth/devices         لیست دستگاه‌های فعال این مشتری
 *   DELETE /v1/customer/auth/devices/{id}    revoke یک دستگاه خاص (logout-device)
 *
 * مفید وقتی کاربر می‌خواهد ببیند روی چه دستگاه‌هایی login است و یک
 * دستگاه گم‌شده/ناشناخته را خروج کند بدون logout-all.
 */
class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', Customer::class)
            ->where('tokenable_id', $customer->id)
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'device_id', 'last_used_at', 'last_used_ip', 'created_at', 'expires_at']);

        $data = $tokens->map(fn (PersonalAccessToken $t) => [
            'id' => (int) $t->id,
            'device_id' => $t->device_id,
            'is_current' => (int) $t->id === (int) $currentTokenId,
            'last_used_at' => $t->last_used_at?->utc()->toIso8601String(),
            'last_used_ip' => $t->last_used_ip,
            'created_at' => $t->created_at?->utc()->toIso8601String(),
            'expires_at' => $t->expires_at?->utc()->toIso8601String(),
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => ['total' => $data->count()],
        ])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = $this->customer($request);

        $token = PersonalAccessToken::query()
            ->where('id', $id)
            ->where('tokenable_type', Customer::class)
            ->where('tokenable_id', $customer->id)
            ->first();
        if (! $token) {
            abort(404, 'دستگاه یافت نشد.');
        }

        $isCurrent = (int) $token->id === (int) $request->user()->currentAccessToken()?->id;

        $token->delete();

        return response()->json([
            'message' => $isCurrent ? 'از این دستگاه خارج شدید.' : 'دستگاه revoke شد.',
            'data' => [
                'revoked_id' => $id,
                'was_current' => $isCurrent,
            ],
        ]);
    }

    private function customer(Request $request): Customer
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            abort(401, 'احراز هویت مشتری لازم است.');
        }

        return $user;
    }
}
