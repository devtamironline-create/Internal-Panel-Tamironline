<?php

namespace App\Services\Ads\Google;

use App\Models\AdsCallClickEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * Google Data Manager API — ingest رویدادهای Call Click و پیگیری وضعیت.
 *
 *   POST {base}/events:ingest              → requestId
 *   GET  {base}/requestStatus:retrieve     → SUCCESS|PROCESSING|FAILED|PARTIAL_SUCCESS
 *
 * قواعد mapping (قرارداد این پروژه):
 *   transactionId  = ads_call_click_events.event_id  (deduplication سمت Google)
 *   eventTimestamp = زمان واقعی کلیک، RFC3339 UTC
 *   eventSource    = WEB (سایت و PWA هر دو)
 *   adIdentifiers  = فقط شناسه‌های موجودِ خود event (gclid/wbraid/gbraid)
 *   بدون conversionValue/currency و بدون هیچ PII (شماره تلفن/IP/UA).
 */
class GoogleDataManagerService
{
    public function __construct(
        protected GoogleHttpClient $http,
        protected GoogleDataManagerTokenProvider $tokens,
        protected array $config,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            GoogleHttpClient::fromConfig(),
            GoogleDataManagerTokenProvider::fromConfig(),
            (array) config('ads_tracking.google', []),
        );
    }

    /** شناسهٔ کلیک برای log — هرگز کامل لاگ نمی‌شود. */
    public static function mask(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        $value = (string) $value;

        return strlen($value) <= 8 ? substr($value, 0, 2).'…' : substr($value, 0, 6).'…'.substr($value, -2);
    }

    /** بدنهٔ یک Data Manager Event از روی رکورد DB — بدون PII. */
    public function buildEvent(AdsCallClickEvent $event): array
    {
        $identifiers = array_filter([
            'gclid' => $event->gclid,
            'wbraid' => $event->wbraid,
            'gbraid' => $event->gbraid,
        ], fn ($v) => filled($v));

        if ($identifiers === []) {
            throw new GoogleDeliveryException(
                'event بدون شناسهٔ Google قابل ارسال نیست.',
                retryable: false,
                errorCode: 'NO_AD_IDENTIFIER',
            );
        }

        return [
            'transactionId' => (string) $event->event_id,
            'eventTimestamp' => $event->event_time?->clone()->utc()->format('Y-m-d\TH:i:s\Z')
                ?? now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'eventSource' => 'WEB',
            'adIdentifiers' => $identifiers,
        ];
    }

    public function destination(): array
    {
        return [
            'operatingAccount' => [
                'accountType' => 'GOOGLE_ADS',
                'accountId' => (string) ($this->config['customer_id'] ?? ''),
            ],
            'productDestinationId' => (string) ($this->config['conversion_action_id'] ?? ''),
        ];
    }

    /**
     * ارسال یک دسته event (پیش‌فرض batch=1).
     *
     * @param  array<int, AdsCallClickEvent>  $events
     * @return array{request_id: ?string, validate_only: bool, meta: array}
     *
     * @throws GoogleDeliveryException
     */
    public function ingest(array $events, ?bool $validateOnly = null): array
    {
        if ($events === []) {
            throw new GoogleDeliveryException('لیست event خالی است.', retryable: false, errorCode: 'EMPTY_BATCH');
        }

        if (blank($this->config['customer_id'] ?? null) || blank($this->config['conversion_action_id'] ?? null)) {
            throw new GoogleDeliveryException(
                'Customer ID / Conversion Action ID تنظیم نشده است.',
                retryable: false,
                errorCode: 'DESTINATION_MISSING',
            );
        }

        $validateOnly = $validateOnly ?? (bool) ($this->config['validate_only'] ?? true);

        $payload = [
            'destinations' => [$this->destination()],
            'events' => array_map(fn (AdsCallClickEvent $e) => $this->buildEvent($e), $events),
            'validateOnly' => $validateOnly,
        ];

        $response = $this->send(
            fn (string $token) => $this->http->postJson(
                rtrim((string) ($this->config['base_url'] ?? 'https://datamanager.googleapis.com/v1'), '/').'/events:ingest',
                $payload,
                ['Authorization' => 'Bearer '.$token],
            )
        );

        return [
            'request_id' => $response->json('requestId'),
            'validate_only' => $validateOnly,
            'meta' => $this->responseMeta($response),
        ];
    }

    /**
     * وضعیت یک request قبلی.
     *
     * @return array{status: string, errors: ?string, meta: array}
     */
    public function requestStatus(string $requestId): array
    {
        $response = $this->send(
            fn (string $token) => $this->http->get(
                rtrim((string) ($this->config['base_url'] ?? 'https://datamanager.googleapis.com/v1'), '/').'/requestStatus:retrieve',
                ['requestId' => $requestId],
                ['Authorization' => 'Bearer '.$token],
            )
        );

        return [
            'status' => $this->aggregateStatus($response->json() ?? []),
            'errors' => $this->extractErrorSummary($response->json() ?? []),
            'meta' => $this->responseMeta($response),
        ];
    }

    /**
     * اجرای درخواست با توکن + یک‌بار refresh خودکار روی 401 و
     * تبدیل خطاهای transport/HTTP به GoogleDeliveryException طبقه‌بندی‌شده.
     */
    protected function send(callable $request): Response
    {
        try {
            $response = $request($this->tokens->token());

            if ($response->status() === 401) {
                // توکن کش‌شده مرده — یک‌بار تازه بگیر و تکرار کن.
                $this->tokens->forget();
                $response = $request($this->tokens->token());
            }
        } catch (ConnectionException $e) {
            // خطای شبکه/پروکسی — قابل retry، بدون fallback مستقیم.
            throw new GoogleDeliveryException(
                'خطای اتصال به Google (از مسیر پروکسی): '.$e->getMessage(),
                retryable: true,
                errorCode: 'CONNECTION',
            );
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $googleStatus = (string) $response->json('error.status', '');
        $googleMessage = mb_substr((string) $response->json('error.message', ''), 0, 500);

        throw new GoogleDeliveryException(
            'Google HTTP '.$status.($googleStatus !== '' ? ' '.$googleStatus : '').($googleMessage !== '' ? ' — '.$googleMessage : ''),
            // 429 و 5xx و 408 گذرا هستند؛ 4xx های دیگر (validation/permission) دائمی.
            retryable: $status === 429 || $status === 408 || $status >= 500,
            errorCode: 'HTTP_'.$status.($googleStatus !== '' ? '_'.$googleStatus : ''),
        );
    }

    /**
     * جمع‌بندی وضعیت از پاسخ requestStatus — به شکل tolerant، چون پاسخ
     * per-destination گزارش می‌شود.
     */
    protected function aggregateStatus(array $body): string
    {
        $found = [];
        array_walk_recursive($body, function ($value, $key) use (&$found) {
            if (in_array($key, ['requestStatus', 'status'], true)
                && in_array($value, ['SUCCESS', 'PROCESSING', 'FAILED', 'PARTIAL_SUCCESS'], true)) {
                $found[$value] = true;
            }
        });

        return match (true) {
            isset($found['PARTIAL_SUCCESS']) => 'PARTIAL_SUCCESS',
            isset($found['FAILED']) && isset($found['SUCCESS']) => 'PARTIAL_SUCCESS',
            isset($found['FAILED']) => 'FAILED',
            isset($found['PROCESSING']) => 'PROCESSING',
            isset($found['SUCCESS']) => 'SUCCESS',
            default => 'UNKNOWN',
        };
    }

    protected function extractErrorSummary(array $body): ?string
    {
        $errors = [];
        array_walk_recursive($body, function ($value, $key) use (&$errors) {
            if (in_array($key, ['reason', 'errorReason', 'errorMessage', 'message'], true) && filled($value) && count($errors) < 5) {
                $errors[] = (string) $value;
            }
        });

        return $errors === [] ? null : mb_substr(implode(' | ', array_unique($errors)), 0, 900);
    }

    /** metadata غیرحساس برای ذخیره در google_response_meta. */
    protected function responseMeta(Response $response): array
    {
        $body = $response->json();

        return [
            'http_status' => $response->status(),
            // فقط کلیدهای سطح بالا — نه بدنهٔ کامل (ممکن است شناسهٔ کامل کلیک داشته باشد).
            'body_keys' => is_array($body) ? array_slice(array_keys($body), 0, 12) : [],
            'at' => now()->toIso8601String(),
        ];
    }
}
