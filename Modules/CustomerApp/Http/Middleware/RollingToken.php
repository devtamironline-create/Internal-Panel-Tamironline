<?php

namespace Modules\CustomerApp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\CRM\Models\Customer;
use Modules\Identity\Services\IdentityService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rolling token: اگر توکن فعلی مشتری در نیمه‌ی دوم عمر است، یک توکن تازه
 * می‌سازد و در header `X-Renewed-Token` در پاسخ می‌گذارد. توکن قدیمی همچنان
 * تا انقضای واقعی کار می‌کند (به‌جز اگر کلاینت آن را drop کند).
 *
 * Threshold: اگر بیش از ۵۰٪ عمر توکن گذشته → جدید می‌سازد.
 * Sanctum expiration در config/sanctum.php (پیش‌فرض ۳۰ روز).
 */
class RollingToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // renewal فقط روی پاسخِ موفقِ (2xx) endpointهای احرازشده — نه روی خطاها.
        // تا فرانت مطمئن باشد X-Renewed-Token فقط در پاسخ‌های سالم می‌آید.
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return $response;
        }

        $user = $request->user();
        if (! $user instanceof Customer) {
            return $response;
        }

        // حسابِ همین الان حذف‌شده (delete-account) نباید توکنِ تازه بگیرد.
        if ($user->trashed()) {
            return $response;
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return $response;
        }

        $expirationMinutes = (int) config('sanctum.expiration');
        if ($expirationMinutes <= 0) {
            return $response; // توکن انقضا ندارد — نیازی به renew نیست
        }

        $createdAt = $token->created_at;
        if (! $createdAt) {
            return $response;
        }

        $ageMinutes = $createdAt->diffInMinutes(now());
        $halfLife = (int) ($expirationMinutes / 2);
        if ($ageMinutes < $halfLife) {
            return $response;
        }

        // در نیمه‌ی دوم عمر — توکن جدید بساز
        try {
            $newToken = $user->createToken(IdentityService::TOKEN_NAME, ['*']);
            $response->headers->set('X-Renewed-Token', $newToken->plainTextToken);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('rolling_token.create_failed', [
                'customer_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}
