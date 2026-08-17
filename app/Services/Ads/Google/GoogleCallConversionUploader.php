<?php

namespace App\Services\Ads\Google;

use App\Models\AdsCallClickEvent;
use Illuminate\Support\Facades\Log;

/**
 * ارکستراسیون آپلود Conversion به Google — state machine و retry.
 *
 *   pending → sending (claim اتمیک) → processing (requestId گرفت)
 *          → uploaded (requestStatus=SUCCESS)
 *          → pending دوباره (خطای گذرا، با backoff نمایی + jitter)
 *          → failed (خطای دائمی یا سقف تلاش)
 *
 * اصل اول: DB تعمیرآنلاین Source of Truth است — هیچ مسیری event را حذف
 * نمی‌کند و خرابی پروکسی/Google فقط ارسال را عقب می‌اندازد، نه ثبت را.
 */
class GoogleCallConversionUploader
{
    public function __construct(
        protected GoogleDataManagerService $dataManager,
        protected array $config,
    ) {}

    public static function fromConfig(): self
    {
        return new self(GoogleDataManagerService::fromConfig(), (array) config('ads_tracking.google', []));
    }

    /** ارسال eventهای واجدشرایط. خروجی: آمار برای گزارش کامند. */
    public function uploadPending(int $limit = 50): array
    {
        $stats = ['claimed' => 0, 'processing' => 0, 'validated' => 0, 'retried' => 0, 'failed' => 0];

        $this->recoverStuckSending();

        $batchSize = max(1, (int) ($this->config['batch_size'] ?? 1));

        $candidates = AdsCallClickEvent::query()
            ->where('google_status', 'pending')
            ->where(function ($q) {
                $q->whereNotNull('gclid')->orWhereNotNull('wbraid')->orWhereNotNull('gbraid');
            })
            ->where(function ($q) {
                $q->whereNull('google_next_retry_at')->orWhere('google_next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($candidates->chunk($batchSize) as $chunk) {
            $claimed = $chunk->filter(fn (AdsCallClickEvent $e) => $this->claim($e))->values();
            if ($claimed->isEmpty()) {
                continue;
            }
            $stats['claimed'] += $claimed->count();

            $this->sendBatch($claimed->all(), $stats);
        }

        return $stats;
    }

    /**
     * claim اتمیک: فقط اجرایی که سطر را از pending به sending ببرد
     * فرستنده است — دو worker هم‌زمان یک event را نمی‌فرستند.
     */
    public function claim(AdsCallClickEvent $event): bool
    {
        $updated = AdsCallClickEvent::query()
            ->whereKey($event->id)
            ->where('google_status', 'pending')
            ->update([
                'google_status' => 'sending',
                'google_last_attempt_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated === 1) {
            $event->refresh();

            return true;
        }

        return false;
    }

    /** sending های جامانده از crash قدیمی‌تر از ۳۰ دقیقه → pending. */
    public function recoverStuckSending(): int
    {
        return AdsCallClickEvent::query()
            ->where('google_status', 'sending')
            ->where('google_last_attempt_at', '<', now()->subMinutes(30))
            ->update(['google_status' => 'pending', 'updated_at' => now()]);
    }

    /** @param array<int, AdsCallClickEvent> $events */
    protected function sendBatch(array $events, array &$stats): void
    {
        $attemptNumbers = [];
        foreach ($events as $event) {
            $attemptNumbers[$event->id] = (int) $event->google_attempts + 1;
        }

        try {
            $result = $this->dataManager->ingest($events);
        } catch (GoogleDeliveryException $e) {
            foreach ($events as $event) {
                $this->markFailure($event, $e, $attemptNumbers[$event->id], $stats);
            }

            return;
        }

        foreach ($events as $event) {
            if ($result['validate_only']) {
                // حالت Gate: request فقط validate شد — conversion واقعی ساخته
                // نشده، پس event برای آپلود واقعیِ بعدی pending می‌ماند.
                $event->forceFill([
                    'google_status' => 'pending',
                    'google_attempts' => $attemptNumbers[$event->id],
                    'google_response_meta' => $result['meta'] + ['validate_only' => true],
                    'google_error' => null,
                    'google_error_code' => null,
                ])->save();
                $stats['validated']++;
            } else {
                $event->forceFill([
                    'google_status' => 'processing',
                    'google_attempts' => $attemptNumbers[$event->id],
                    'google_request_id' => $result['request_id'],
                    'google_response_meta' => $result['meta'],
                    'google_error' => null,
                    'google_error_code' => null,
                    'google_next_retry_at' => null,
                ])->save();
                $stats['processing']++;
            }

            $this->log('info', $result['validate_only'] ? 'validate_only_ok' : 'ingest_accepted', $event, [
                'request_id' => $result['request_id'],
                'attempt' => $attemptNumbers[$event->id],
            ]);
        }
    }

    protected function markFailure(AdsCallClickEvent $event, GoogleDeliveryException $e, int $attempt, array &$stats): void
    {
        $maxAttempts = max(1, (int) ($this->config['max_attempts'] ?? 10));
        $retryable = $e->retryable && $attempt < $maxAttempts;

        $event->forceFill([
            'google_status' => $retryable ? 'pending' : 'failed',
            'google_attempts' => $attempt,
            'google_error' => mb_substr($e->getMessage(), 0, 900),
            'google_error_code' => $e->errorCode,
            'google_next_retry_at' => $retryable ? $this->nextRetryAt($attempt) : null,
        ])->save();

        $stats[$retryable ? 'retried' : 'failed']++;

        $this->log($retryable ? 'warning' : 'error', 'ingest_failed', $event, [
            'attempt' => $attempt,
            'error_code' => $e->errorCode,
            'retryable' => $retryable,
        ]);
    }

    /** backoff نمایی + jitter — کف ۱ دقیقه، سقف ۶ ساعت. */
    public function nextRetryAt(int $attempt): \Illuminate\Support\Carbon
    {
        $seconds = min(21_600, 60 * (2 ** min(max($attempt - 1, 0), 8)));

        return now()->addSeconds($seconds + random_int(0, 60));
    }

    /** poll وضعیت requestهای در حال پردازش. */
    public function pollProcessing(int $limit = 50): array
    {
        $stats = ['checked' => 0, 'uploaded' => 0, 'still_processing' => 0, 'retried' => 0, 'failed' => 0];

        $events = AdsCallClickEvent::query()
            ->where('google_status', 'processing')
            ->whereNotNull('google_request_id')
            ->where(function ($q) {
                $q->whereNull('google_last_status_checked_at')
                    ->orWhere('google_last_status_checked_at', '<=', now()->subMinutes(5));
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        // requestهای batch مشترک فقط یک‌بار پرس‌وجو می‌شوند.
        foreach ($events->groupBy('google_request_id') as $requestId => $group) {
            try {
                $result = $this->dataManager->requestStatus((string) $requestId);
            } catch (GoogleDeliveryException $e) {
                // خطای poll (پروکسی/شبکه/…) وضعیت event را عوض نمی‌کند.
                foreach ($group as $event) {
                    $event->forceFill(['google_last_status_checked_at' => now()])->save();
                }
                $this->log('warning', 'poll_failed', $group->first(), [
                    'request_id' => $requestId, 'error_code' => $e->errorCode,
                ]);

                continue;
            }

            $stats['checked'] += $group->count();

            foreach ($group as $event) {
                $this->applyRequestStatus($event, $result, $group->count(), $stats);
            }
        }

        return $stats;
    }

    protected function applyRequestStatus(AdsCallClickEvent $event, array $result, int $groupSize, array &$stats): void
    {
        $base = [
            'google_last_status_checked_at' => now(),
            'google_response_meta' => $result['meta'] + ['request_status' => $result['status']],
        ];

        switch ($result['status']) {
            case 'SUCCESS':
                $event->forceFill($base + [
                    'google_status' => 'uploaded',
                    'google_uploaded_at' => now(),
                    'google_error' => null,
                    'google_error_code' => null,
                    'google_next_retry_at' => null,
                ])->save();
                $stats['uploaded']++;
                $this->log('info', 'uploaded', $event, ['request_id' => $event->google_request_id]);
                break;

            case 'PROCESSING':
            case 'UNKNOWN':
                // اگر خیلی طولانی شد، دوباره ارسال می‌کنیم — transactionId ثابت
                // است و Google خودش deduplicate می‌کند.
                if ($event->google_last_attempt_at && $event->google_last_attempt_at->lt(now()->subDay())) {
                    $this->requeue($event, $base, 'PROCESSING_TIMEOUT', $stats);
                } else {
                    $event->forceFill($base + ['google_status' => 'processing'])->save();
                    $stats['still_processing']++;
                }
                break;

            case 'PARTIAL_SUCCESS':
                if ($groupSize > 1) {
                    // batch مشکل‌دار — رویدادها جدا جدا دوباره ارسال می‌شوند تا
                    // event مشکل‌دار مشخص شود (transactionId ثابت → بدون duplicate).
                    $this->requeue($event, $base, 'PARTIAL_SUCCESS_SPLIT', $stats);
                    break;
                }
                // batch=1 → مثل FAILED رفتار می‌شود.
                // no break
            case 'FAILED':
                $transient = $result['errors'] !== null
                    && preg_match('/internal|unavailable|deadline|timeout|retry/i', $result['errors']) === 1;
                $maxAttempts = max(1, (int) ($this->config['max_attempts'] ?? 10));

                if ($transient && (int) $event->google_attempts < $maxAttempts) {
                    $this->requeue($event, $base + ['google_error' => $result['errors'], 'google_error_code' => 'STATUS_'.$result['status']], 'TRANSIENT', $stats);
                } else {
                    $event->forceFill($base + [
                        'google_status' => 'failed',
                        'google_error' => $result['errors'] ?? ('requestStatus='.$result['status']),
                        'google_error_code' => 'STATUS_'.$result['status'],
                    ])->save();
                    $stats['failed']++;
                    $this->log('error', 'request_failed', $event, [
                        'request_id' => $event->google_request_id, 'status' => $result['status'],
                    ]);
                }
                break;
        }
    }

    protected function requeue(AdsCallClickEvent $event, array $base, string $reason, array &$stats): void
    {
        $event->forceFill($base + [
            'google_status' => 'pending',
            'google_next_retry_at' => $this->nextRetryAt((int) $event->google_attempts),
            'google_error_code' => $reason,
        ])->save();
        $stats['retried']++;
        $this->log('warning', 'requeued', $event, ['reason' => $reason]);
    }

    /** اکشن ادمین: eventهای failed دوباره وارد صف می‌شوند (idempotent سمت Google). */
    public function retryFailed(): int
    {
        return AdsCallClickEvent::query()
            ->where('google_status', 'failed')
            ->update([
                'google_status' => 'pending',
                'google_attempts' => 0,
                'google_next_retry_at' => null,
                'updated_at' => now(),
            ]);
    }

    protected function log(string $level, string $stage, ?AdsCallClickEvent $event, array $context = []): void
    {
        Log::channel('ads-google')->{$level}($stage, array_filter([
            'event_id' => $event?->event_id,
            'db_id' => $event?->id,
            'status' => $event?->google_status,
            'gclid' => GoogleDataManagerService::mask($event?->gclid),
            'wbraid' => GoogleDataManagerService::mask($event?->wbraid),
            'gbraid' => GoogleDataManagerService::mask($event?->gbraid),
        ], fn ($v) => $v !== null) + $context);
    }
}
