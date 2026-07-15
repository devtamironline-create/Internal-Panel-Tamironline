<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Analytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * دریافتِ رویدادهای تحلیلی از کلاینت (اپ/سایت).
 *
 *   POST /v1/analytics/track
 *   body: { event: "brand_view", device_id?, brand_id?, subject_type?, subject_id?,
 *           session_id?, source?, props?: {} }
 *
 * public + throttle. زمینه (IP، UA، URL، session) خودکار از Request برداشته می‌شود.
 * پاسخ همیشه سبک و 202 است تا کلاینت منتظرِ نتیجه نماند (fire-and-forget).
 */
class AnalyticsController extends Controller
{
    /** فهرستِ سفیدِ نوعِ رویدادهای مجاز از سمتِ کلاینت (جلوگیری از آلودگیِ داده). */
    private const ALLOWED_EVENTS = [
        'page_view', 'brand_view', 'device_view', 'service_view',
        'search', 'banner_view', 'banner_click', 'add_to_cart',
        'order_start', 'order_step', 'contact_click', 'phone_click',
    ];

    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string', 'max:64'],
            'device_id' => ['nullable', 'integer', 'min:1'],
            'brand_id' => ['nullable', 'integer', 'min:1'],
            'subject_type' => ['nullable', 'string', 'max:64'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'in:app,site,panel'],
            'props' => ['nullable', 'array'],
        ]);

        $event = $data['event'];
        if (! in_array($event, self::ALLOWED_EVENTS, true)) {
            return response()->json([
                'message' => 'نوعِ رویداد مجاز نیست.',
                'code' => 'invalid_event',
            ], 422);
        }

        Analytics::trackFromRequest($request, $event, [
            'device_id' => $data['device_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'source' => $data['source'] ?? 'site',
            'customer_id' => optional($request->user())->id,
            'props' => $data['props'] ?? null,
        ]);

        return response()->json(['recorded' => true], 202);
    }
}
