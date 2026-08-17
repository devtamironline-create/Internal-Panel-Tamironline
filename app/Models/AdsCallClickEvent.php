<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * یک کلیکِ واقعیِ «تماس» — با event_id یکتا (ضدِ retry) و snapshot
 * شناسه‌های Google. مرجعِ همیشگی: دیتابیسِ خودِ ما، نه Google.
 */
class AdsCallClickEvent extends Model
{
    /**
     * وضعیت‌های چرخهٔ آپلود به Google.
     * «sending» گذرا است: claim اتمیک پیش از ارسال، تا دو اجرای هم‌زمان
     * یک event را نفرستند؛ بعد از پاسخ بلافاصله به processing/pending/failed می‌رود.
     */
    public const GOOGLE_STATUSES = ['not_ready', 'pending', 'sending', 'processing', 'uploaded', 'failed', 'ignored'];

    protected $fillable = [
        'event_id', 'attribution_id', 'ads_attribution_id', 'client_source',
        'gclid', 'wbraid', 'gbraid',
        'page_url', 'page_path', 'placement', 'phone_number', 'event_time',
        'google_status', 'google_attempts', 'google_uploaded_at', 'google_request_id', 'google_error',
        'google_last_attempt_at', 'google_next_retry_at', 'google_last_status_checked_at',
        'google_error_code', 'google_response_meta',
        'metadata',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'google_uploaded_at' => 'datetime',
        'google_last_attempt_at' => 'datetime',
        'google_next_retry_at' => 'datetime',
        'google_last_status_checked_at' => 'datetime',
        'google_attempts' => 'integer',
        'google_response_meta' => 'array',
        'metadata' => 'array',
    ];

    public function attribution(): BelongsTo
    {
        return $this->belongsTo(AdsAttribution::class, 'ads_attribution_id');
    }

    public function isAttributed(): bool
    {
        return filled($this->gclid) || filled($this->wbraid) || filled($this->gbraid);
    }
}
