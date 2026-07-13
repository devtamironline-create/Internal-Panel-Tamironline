<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\TechnicianAuthService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rolling token برای اپِ تکنسین — نسخهٔ RollingTokenِ اپِ مشتری. اگر بیش از
 * نیمی از عمرِ توکن گذشته باشد، توکنِ تازه در هدرِ `X-Renewed-Token` می‌گذارد تا
 * تکنسینِ فعال هیچ‌وقت خارج نشود (session کشویی). فقط روی پاسخِ موفقِ 2xx.
 */
class TechRollingToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        $user = $request->user();
        if (! $user instanceof Technician || $user->trashed()) {
            return $response;
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return $response;
        }

        $expirationMinutes = (int) config('sanctum.expiration');
        if ($expirationMinutes <= 0) {
            return $response;
        }

        $createdAt = $token->created_at;
        if (! $createdAt) {
            return $response;
        }

        if ($createdAt->diffInMinutes(now()) < (int) ($expirationMinutes / 2)) {
            return $response;
        }

        try {
            $newToken = $user->createToken(TechnicianAuthService::TOKEN_NAME, ['*']);
            $response->headers->set('X-Renewed-Token', $newToken->plainTextToken);
        } catch (\Throwable $e) {
            Log::warning('tech_rolling_token.create_failed', [
                'technician_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}
