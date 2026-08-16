<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ads\AdsAttributionService;
use App\Services\Ads\AdsCallClickService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * دو endpoint عمومیِ ردیابیِ تبلیغات — بدونِ لاگینِ کاربر، پشتِ CORS و
 * throttle. سبک و سریع: هیچ گزارش/تجمیع سنگینی این‌جا اجرا نمی‌شود و ثبتِ
 * Call Click به هیچ سرویسِ بیرونی وابسته نیست.
 */
class AdsTrackingController extends Controller
{
    private const ID_PATTERN = '/^[A-Za-z0-9_\-.]{4,64}$/';

    /** POST /api/ads/attribution */
    public function attribution(Request $request, AdsAttributionService $service): JsonResponse
    {
        if (! config('ads_tracking.enabled')) {
            return response()->json(['ok' => false, 'message' => 'tracking disabled'], 503);
        }

        try {
            $data = $request->validate([
                'attribution_id' => ['nullable', 'string', 'max:32', 'regex:'.self::ID_PATTERN],
                'client_source' => 'nullable|in:website,pwa,unknown',
                'gclid' => 'nullable|string|max:255',
                'wbraid' => 'nullable|string|max:255',
                'gbraid' => 'nullable|string|max:255',
                'campaign_id' => 'nullable|string|max:64',
                'adgroup_id' => 'nullable|string|max:64',
                'keyword' => 'nullable|string|max:255',
                'match_type' => 'nullable|string|max:8',
                'device' => 'nullable|string|max:8',
                'network' => 'nullable|string|max:8',
                'creative_id' => 'nullable|string|max:64',
                'landing_url' => 'nullable|url|max:2000',
                'referrer' => 'nullable|string|max:2000',
                // metadata محدود — ضدِ abuse با payload عظیم.
                'metadata' => 'nullable|array|max:20',
                'metadata.*' => 'nullable|string|max:500',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::info('ads_tracking.invalid_attribution_payload', ['errors' => array_keys($e->errors())]);
            throw $e;
        }

        $result = $service->createOrTouch($data, $request);

        return response()->json([
            'ok' => true,
            'attribution_id' => $result['attribution']->attribution_id,
            'created' => $result['created'],
            'has_google_id' => $result['attribution']->hasGoogleId(),
            'expires_at' => $result['attribution']->expires_at?->toIso8601String(),
        ], $result['created'] ? 201 : 200);
    }

    /** POST /api/ads/call-click */
    public function callClick(Request $request, AdsCallClickService $service): JsonResponse
    {
        if (! config('ads_tracking.enabled')) {
            return response()->json(['ok' => false, 'message' => 'tracking disabled'], 503);
        }

        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:64', 'regex:'.self::ID_PATTERN],
            'attribution_id' => ['nullable', 'string', 'max:32', 'regex:'.self::ID_PATTERN],
            'client_source' => 'nullable|in:website,pwa,unknown',
            'page_url' => 'nullable|url|max:2000',
            'placement' => 'nullable|string|max:64',
            'phone_number' => 'nullable|string|max:32',
            'metadata' => 'nullable|array|max:20',
            'metadata.*' => 'nullable|string|max:500',
        ]);

        $result = $service->record($data);

        return response()->json([
            'ok' => true,
            'event_id' => $result['event']->event_id,
            'tracked' => true,
            'attributed' => $result['attributed'],
            'duplicate' => ! $result['created'],
        ], $result['created'] ? 201 : 200);
    }
}
