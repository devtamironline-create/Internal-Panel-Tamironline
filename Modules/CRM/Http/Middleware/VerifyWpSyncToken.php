<?php

namespace Modules\CRM\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\CRM\Models\CrmSetting;
use Symfony\Component\HttpFoundation\Response;

/**
 * اعتبارسنجی توکن سینک وردپرس.
 *
 * در هدر Authorization باید Bearer <token> بیاید.
 * مقدار توکن مرجع در crm_settings با کلید wp_sync_token ذخیره شده است.
 */
class VerifyWpSyncToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->bearerToken();
        $expected = CrmSetting::get('wp_sync_token');

        if (empty($expected) || empty($provided) || ! hash_equals($expected, $provided)) {
            return response()->json([
                'ok' => false,
                'error' => 'unauthorized',
                'message' => 'توکن سینک معتبر نیست.',
            ], 401);
        }

        return $next($request);
    }
}
