<?php

namespace App\Services\Ads\Google;

/**
 * پروکسی اجباری است ولی پیکربندی ناقص/نامعتبر است — Fail Closed:
 * هیچ درخواستی به Google ارسال نمی‌شود (نه مستقیم، نه fallback).
 * event در DB می‌ماند و بعداً retry می‌شود.
 */
class GoogleProxyUnavailableException extends GoogleDeliveryException
{
    public function __construct(string $message = 'پیکربندی پروکسی Google ناقص است؛ درخواست ارسال نشد (fail-closed).')
    {
        parent::__construct($message, retryable: true, errorCode: 'PROXY_UNAVAILABLE');
    }
}
