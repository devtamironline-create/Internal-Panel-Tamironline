<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Customer;

/**
 * Customer profile management for mobile app.
 *
 *   GET /v1/customer/profile  → snapshot کامل
 *   PUT /v1/customer/profile  → ویرایش first_name/last_name/email
 *
 * Endpoint جداگانه از /v1/auth/me و /v1/auth/complete-profile که فقط نام
 * می‌گیرند. این endpoint شکل response استاندارد envelope را دارد و فیلدهای
 * بیشتری برای ویرایش می‌پذیرد.
 */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        return response()->json([
            'data' => $this->shape($customer),
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        $data = $request->validate([
            'first_name' => 'required|string|min:2|max:80',
            'last_name' => 'nullable|string|max:80',
            'email' => 'nullable|email|max:150',
        ]);

        $customer->forceFill([
            'first_name' => trim($data['first_name']),
            'last_name' => isset($data['last_name']) ? trim((string) $data['last_name']) : null,
            'email' => isset($data['email']) ? mb_strtolower(trim((string) $data['email'])) : null,
        ])->save();

        return response()->json([
            'message' => 'پروفایل به‌روز شد.',
            'data' => $this->shape($customer->fresh()),
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

    /**
     * @return array<string, mixed>
     */
    private function shape(Customer $c): array
    {
        return [
            'id' => (int) $c->id,
            'mobile' => $c->mobile,
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'full_name' => $c->full_name ?: null,
            'email' => $c->email,
            'is_profile_complete' => ! empty($c->first_name),
            'mobile_verified_at' => $c->mobile_verified_at?->utc()->toIso8601String(),
            'subscription' => $c->subscription,
            'created_at' => $c->created_at?->utc()->toIso8601String(),
        ];
    }
}
