<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * احراز هویت سرور-به-سرور با Static Bearer Token.
 *
 * هدف: محدود کردن روت‌های /v1/* نوشتنی به مصرف‌کننده‌ی داخلی (Next BFF).
 * مقایسه با hash_equals برای جلوگیری از timing attack.
 */
class VerifyInternalToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected    = (string) config('services.internal.token');
        $expectedOld = (string) config('services.internal.token_old');
        $provided    = (string) $request->bearerToken();

        $valid = false;
        if ($expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
            $valid = true;
        } elseif ($expectedOld !== '' && $provided !== '' && hash_equals($expectedOld, $provided)) {
            $valid = true;
        }

        if (! $valid) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
