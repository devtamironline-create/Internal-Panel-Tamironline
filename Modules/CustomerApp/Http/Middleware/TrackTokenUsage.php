<?php

namespace Modules\CustomerApp\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\CRM\Models\Customer;
use Symfony\Component\HttpFoundation\Response;

/**
 * ثبت last_used_ip روی personal_access_tokens.
 *
 * Sanctum خودش last_used_at را به‌روز می‌کند، ولی last_used_ip را نه. این
 * middleware روی هر request مشتری authenticated، اگر IP فرق کرده، آن را
 * ذخیره می‌کند. تک کوئری سبک — بدون save() کل مدل، فقط یک UPDATE.
 *
 * استفاده فقط روی روت‌های auth-required تا تماس‌های public DB نزنند.
 */
class TrackTokenUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof Customer) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $ip = $request->ip();
        if ($ip && $token->last_used_ip !== $ip) {
            // UPDATE مستقیم بدون reload کل مدل — بهینه
            PersonalAccessToken::query()
                ->where('id', $token->id)
                ->update(['last_used_ip' => substr((string) $ip, 0, 45)]);
        }

        return $next($request);
    }
}
