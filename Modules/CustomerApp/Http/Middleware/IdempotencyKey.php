<?php

namespace Modules\CustomerApp\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * پشتیبانی از هدر `Idempotency-Key` برای POST/PUT/PATCH/DELETE.
 *
 * منطق:
 *   ۱) فقط روش‌های غیر-idempotent (POST, PUT, PATCH, DELETE) را در نظر می‌گیرد
 *   ۲) اگر هدر Idempotency-Key نباشد → بدون تغییر عبور می‌دهد
 *   ۳) اگر key با scope (route + user) قبلاً ذخیره شده → response قبلی برمی‌گرداند
 *   ۴) در غیر این صورت → endpoint اجرا و response (اگر 2xx یا 4xx پایدار) ذخیره می‌شود ۲۴ ساعت
 *
 * Scope شامل auth_id (مشتری) + path راه می‌شود تا یک key از کاربر A برای
 * request کاربر B استفاده نشود.
 *
 * UUID v4 پذیرفته می‌شود. کلیدهای غیرمعتبر نادیده گرفته می‌شوند (بدون خطا).
 */
class IdempotencyKey
{
    private const CACHE_TTL_SECONDS = 86400; // 24h

    private const ELIGIBLE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::ELIGIBLE_METHODS, true)) {
            return $next($request);
        }

        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '' || ! $this->isValidKey($key)) {
            return $next($request);
        }

        $cacheKey = $this->cacheKey($request, $key);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['status'], $cached['body'])) {
            $resp = new JsonResponse(
                $cached['body'],
                (int) $cached['status'],
                $cached['headers'] ?? []
            );
            $resp->headers->set('Idempotent-Replay', 'true');

            return $resp;
        }

        /** @var Response $response */
        $response = $next($request);

        // فقط پاسخ‌های 2xx و 4xx پایدار را cache کن — 5xx را قابل retry بگذار
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 500 && $status !== 408 && $status !== 425 && $status !== 429) {
            $body = $response instanceof JsonResponse
                ? $response->getData(true)
                : json_decode($response->getContent() ?: 'null', true);

            Cache::put($cacheKey, [
                'status' => $status,
                'body' => $body,
                'headers' => [
                    'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                ],
            ], self::CACHE_TTL_SECONDS);
        }

        return $response;
    }

    private function cacheKey(Request $request, string $idempotencyKey): string
    {
        $userId = optional($request->user())->getKey() ?? 'guest';
        $path = $request->path();

        return 'idempotency:'.$userId.':'.$path.':'.$idempotencyKey;
    }

    private function isValidKey(string $key): bool
    {
        // UUID یا 16-128 کاراکتر alpha-num/-/_
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z0-9_-]{16,128}$/', $key);
    }
}
