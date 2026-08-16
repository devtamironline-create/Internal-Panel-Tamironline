<?php

namespace App\Services\Ads;

use App\Models\AdsAttribution;
use App\Models\AdsCallClickEvent;
use Illuminate\Support\Facades\Log;

/**
 * ثبتِ رویدادِ Call Click — قواعدِ قفل‌شده:
 *
 *  - event_id یکتا: retry مرورگر رویدادِ تکراری نمی‌سازد؛ سه کلیکِ واقعی
 *    با سه event_id سه رویدادِ جدا هستند (یک gclid می‌تواند چند تماس داشته باشد).
 *  - attribution پیدا نشد؟ رویداد هرگز دور ریخته نمی‌شود (not_ready).
 *  - شناسه‌های Google از دیتابیسِ خودمان snapshot می‌شوند، نه از مرورگر.
 */
class AdsCallClickService
{
    /**
     * @param  array<string, mixed>  $data  ورودیِ اعتبارسنجی‌شدهٔ کنترلر
     * @return array{event: AdsCallClickEvent, created: bool, attributed: bool}
     */
    public function record(array $data): array
    {
        // ضدِ تکرار — قبل از هر کاری، تا مسیرِ retry ارزان بماند.
        $existing = AdsCallClickEvent::where('event_id', $data['event_id'])->first();
        if ($existing !== null) {
            Log::info('ads_tracking.duplicate_event', ['event_id' => $data['event_id']]);

            return ['event' => $existing, 'created' => false, 'attributed' => $existing->isAttributed()];
        }

        // Resolution سمتِ سرور — به شناسه‌های ادعاییِ مرورگر اعتماد نمی‌کنیم.
        $attribution = filled($data['attribution_id'] ?? null)
            ? AdsAttribution::where('attribution_id', $data['attribution_id'])->first()
            : null;

        if ($attribution === null && filled($data['attribution_id'] ?? null)) {
            Log::info('ads_tracking.invalid_attribution', [
                'attribution_id' => $data['attribution_id'],
                'event_id' => $data['event_id'],
            ]);
        }

        $hasGoogleId = $attribution?->hasGoogleId() ?? false;

        try {
            $event = AdsCallClickEvent::create([
                'event_id' => $data['event_id'],
                'attribution_id' => $attribution?->attribution_id,
                'ads_attribution_id' => $attribution?->id,
                'client_source' => $data['client_source'] ?? ($attribution->client_source ?? 'unknown'),
                'gclid' => $attribution?->gclid,
                'wbraid' => $attribution?->wbraid,
                'gbraid' => $attribution?->gbraid,
                'page_url' => $data['page_url'] ?? null,
                'page_path' => filled($data['page_url'] ?? null)
                    ? mb_substr((string) parse_url($data['page_url'], PHP_URL_PATH), 0, 500)
                    : null,
                'placement' => $data['placement'] ?? null,
                'phone_number' => $data['phone_number'] ?? null,
                'event_time' => now(),
                // آپلود Google در این مرحله خاموش است — فقط وضعیتِ آماده‌سازی.
                'google_status' => $hasGoogleId ? 'pending' : 'not_ready',
                'metadata' => $data['metadata'] ?? null,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // دو retry همزمان — برنده قبلاً نوشته؛ همان را برگردان (نه خطا).
            $event = AdsCallClickEvent::where('event_id', $data['event_id'])->firstOrFail();

            return ['event' => $event, 'created' => false, 'attributed' => $event->isAttributed()];
        }

        return ['event' => $event, 'created' => true, 'attributed' => $event->isAttributed()];
    }
}
