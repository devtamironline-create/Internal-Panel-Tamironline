<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * گِیتِ endpointهای کاتالوگِ فقط‌خواندنی که مصرف‌کننده‌شان سرورِ فرانت است:
 * یا توکنِ داخلیِ BFF (مثلِ VerifyInternalToken) یا IPِ معتمدِ سرورِ فرانت
 * (services.internal.trusted_ips).
 *
 * چرا IP هم کافی است: هنگامِ `npm run build` در Docker، env توکن در دسترسِ
 * مرحلهٔ build نیست و همهٔ fetchهای ISR با 401 می‌شکستند؛ در حالی که این
 * endpointها همان محتوای عمومیِ سایت‌اند و توکن برای محرمانگی نبود.
 *
 * نکتهٔ امنیتی: چون trustProxies(at:'*') فعال است، request->ip() با هدرِ
 * X-Forwarded-For قابلِ جعل است؛ این‌جا REMOTE_ADDR (طرفِ واقعیِ TCP — غیرقابلِ
 * جعل) مقایسه می‌شود. سرورِ فرانت مستقیم به panel می‌زند، پس REMOTE_ADDR
 * همان IPِ فرانت است.
 */
class VerifyTrustedFrontend
{
    public function handle(Request $request, Closure $next): Response
    {
        // ۱) توکنِ داخلی (Bearer یا X-Internal-Token) — هم‌منطق با VerifyInternalToken.
        $provided = (string) ($request->header('X-Internal-Token') ?: $request->bearerToken());
        if ($provided !== '') {
            foreach ([config('services.internal.token'), config('services.internal.token_old')] as $expected) {
                $expected = (string) $expected;
                if ($expected !== '' && hash_equals($expected, $provided)) {
                    return $next($request);
                }
            }
        }

        // ۲) IPِ معتمدِ سرورِ فرانت — REMOTE_ADDR، نه request->ip() (ضدِ جعلِ XFF).
        $remote = (string) $request->server('REMOTE_ADDR', '');
        $trusted = array_filter(array_map('trim', explode(',', (string) config('services.internal.trusted_ips'))));
        if ($remote !== '' && in_array($remote, $trusted, true)) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
