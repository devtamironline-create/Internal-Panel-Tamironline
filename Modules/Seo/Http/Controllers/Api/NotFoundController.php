<?php

namespace Modules\Seo\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Seo\Models\SeoNotFound;

/**
 * POST /v1/seo/404 — ثبت/تجمیع بازدید ۴۰۴ از فرانت.
 */
class NotFoundController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'uri' => 'required|string|max:191',
            'referrer' => 'nullable|string|max:2048',
        ]);

        $uri = $data['uri'];
        $referrer = $data['referrer'] ?? null;
        $ua = substr((string) $request->userAgent(), 0, 512);

        // upsert اتمیک: اگر uri موجود بود hits++ و در غیر این صورت رکورد جدید.
        $existing = SeoNotFound::query()->where('uri', $uri)->first();
        if ($existing) {
            $existing->forceFill([
                'hits' => DB::raw('hits + 1'),
                'referrer' => $referrer ?: $existing->referrer,
                'user_agent' => $ua ?: $existing->user_agent,
                'last_seen_at' => now(),
            ])->save();
        } else {
            SeoNotFound::query()->create([
                'uri' => $uri,
                'referrer' => $referrer,
                'user_agent' => $ua,
                'hits' => 1,
                'last_seen_at' => now(),
            ]);
        }

        return response()->json(['ok' => true], 201);
    }
}
