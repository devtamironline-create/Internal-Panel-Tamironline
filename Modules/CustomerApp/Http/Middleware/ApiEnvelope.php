<?php

namespace Modules\CustomerApp\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrap همه‌ی JsonResponse های موفق در envelope استاندارد:
 *   { success: bool, data?: mixed, message?: string, meta?: array, code?: string }
 *
 * فقط روی روت‌های /v1/customer/* اعمال می‌شود — Next.js (/v1/catalog, /v1/blog)
 * دست‌نخورده باقی می‌ماند تا breaking change نباشد.
 *
 * Detection: اگر payload از قبل کلید `success` دارد، envelope مجدد اعمال نمی‌شود
 * (idempotent).
 *
 * Error responses (status >= 400) هم در همان envelope wrap می‌شوند ولی با
 * success=false و کلید code/errors اگر موجود باشد.
 */
class ApiEnvelope
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        if (! is_array($payload)) {
            return $response;
        }

        // اگر از قبل wrap شده، دست نزن (idempotent)
        if (array_key_exists('success', $payload)) {
            return $response;
        }

        $status = $response->getStatusCode();
        $isError = $status >= 400;

        if ($isError) {
            $wrapped = $this->wrapError($payload, $status);
        } else {
            $wrapped = $this->wrapSuccess($payload);
        }

        return $response->setData($wrapped);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function wrapSuccess(array $payload): array
    {
        // اگر payload فرم `data + meta` دارد، همان را به envelope ببر
        if (array_key_exists('data', $payload) || array_key_exists('meta', $payload)) {
            $out = ['success' => true];
            if (array_key_exists('data', $payload)) {
                $out['data'] = $payload['data'];
            }
            if (! empty($payload['message'])) {
                $out['message'] = $payload['message'];
            }
            if (array_key_exists('meta', $payload)) {
                $out['meta'] = $payload['meta'];
            }

            return $out;
        }

        // در غیر این صورت کل payload را data بگذار
        return [
            'success' => true,
            'data' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function wrapError(array $payload, int $status): array
    {
        $out = [
            'success' => false,
            'message' => $payload['message'] ?? $this->defaultMessageForStatus($status),
        ];

        $code = $payload['code'] ?? $payload['error_code'] ?? $this->defaultCodeForStatus($status);
        if ($code) {
            $out['code'] = $code;
        }

        $data = [];
        if (isset($payload['errors']) && is_array($payload['errors'])) {
            $data['errors'] = $payload['errors'];
        }
        if (! empty($code)) {
            $data['error_code'] = $code;
        }
        if ($data !== []) {
            $out['data'] = $data;
        }

        return $out;
    }

    private function defaultMessageForStatus(int $status): string
    {
        return match (true) {
            $status === 401 => 'احراز هویت لازم است.',
            $status === 403 => 'دسترسی غیرمجاز.',
            $status === 404 => 'یافت نشد.',
            $status === 409 => 'تداخل با وضعیت فعلی.',
            $status === 422 => 'ورودی نامعتبر است.',
            $status === 423 => 'سرویس قفل است.',
            $status === 426 => 'لطفاً اپ را به‌روزرسانی کنید.',
            $status === 429 => 'تعداد درخواست‌ها زیاد است. کمی صبر کنید.',
            $status >= 500 => 'خطای داخلی سرور.',
            default => 'خطایی رخ داد.',
        };
    }

    private function defaultCodeForStatus(int $status): ?string
    {
        return match (true) {
            $status === 401 => 'unauthenticated',
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 409 => 'conflict',
            $status === 422 => 'validation_failed',
            $status === 423 => 'locked',
            $status === 426 => 'upgrade_required',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'server_error',
            default => null,
        };
    }
}
