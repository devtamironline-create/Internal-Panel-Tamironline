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
    /** وضعیت‌های چرخهٔ آپلود به Google — در این مرحله فقط not_ready/pending ست می‌شوند. */
    public const GOOGLE_STATUSES = ['not_ready', 'pending', 'processing', 'uploaded', 'failed', 'ignored'];

    protected $fillable = [
        'event_id', 'attribution_id', 'ads_attribution_id', 'client_source',
        'gclid', 'wbraid', 'gbraid',
        'page_url', 'page_path', 'placement', 'phone_number', 'event_time',
        'google_status', 'google_attempts', 'google_uploaded_at', 'google_request_id', 'google_error',
        'metadata',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'google_uploaded_at' => 'datetime',
        'google_attempts' => 'integer',
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
