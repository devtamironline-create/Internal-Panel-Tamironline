<?php

namespace App\Services\Ads\Google;

/**
 * خطای تحویل به Google — با پرچمِ retryable تا state machine تصمیم بگیرد
 * event به pending برگردد (تلاش دوباره) یا failed شود (خطای دائمی).
 *
 * پیام‌ها هرگز شامل credential/token نیستند؛ فقط کلاس خطا و کد HTTP.
 */
class GoogleDeliveryException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
