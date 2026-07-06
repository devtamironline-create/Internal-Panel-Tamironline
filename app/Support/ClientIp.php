<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * IPِ واقعیِ کاربر — درخواست‌های سایت از BFF (سرورِ Next.js) پراکسی می‌شوند و
 * request->ip() برای همهٔ کاربران یکی است (IPِ کانتینرِ فرانت). وقتی درخواست
 * با توکنِ داخلیِ BFF آمده باشد، اولین IPِ معتبرِ X-Forwarded-For (که خودِ BFF
 * ست می‌کند و از سمتِ کاربر spoof‌پذیر نیست) استفاده می‌شود.
 *
 * مصرف: کلیدِ rate-limit و dedupe/auditِ per-IP در مسیرهای عمومی.
 */
class ClientIp
{
    public static function resolve(Request $request): string
    {
        if (self::isInternalBff($request)) {
            $xff = (string) $request->header('X-Forwarded-For', '');
            foreach (explode(',', $xff) as $hop) {
                $ip = trim($hop);
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return (string) $request->ip();
    }

    /**
     * هم‌منطق با VerifyInternalToken (شاملِ توکنِ قدیمی برای rotation).
     */
    public static function isInternalBff(Request $request): bool
    {
        $provided = (string) ($request->header('X-Internal-Token') ?: $request->bearerToken());
        if ($provided === '') {
            return false;
        }

        foreach ([config('services.internal.token'), config('services.internal.token_old')] as $expected) {
            $expected = (string) $expected;
            if ($expected !== '' && hash_equals($expected, $provided)) {
                return true;
            }
        }

        return false;
    }
}
