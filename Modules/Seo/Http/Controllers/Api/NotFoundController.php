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
            'uri' => 'required|string|max:2048',
            'referrer' => 'nullable|string|max:2048',
        ]);

        $uri = $data['uri'];
        $hash = hash('sha256', $uri);
        $referrer = $data['referrer'] ?? null;
        $ua = substr((string) $request->userAgent(), 0, 512);

        // upsertِ اتمیک و امن در برابرِ هم‌زمانی: اول increment روی ردیفِ موجود
        // (با کلیدِ uri_hash). اگر ردیفی نبود، insert؛ و اگر insert به‌خاطرِ
        // مسابقهٔ هم‌زمان با unique برخورد، دوباره increment.
        $update = [
            'hits' => DB::raw('hits + 1'),
            'last_seen_at' => now(),
        ];
        if ($referrer) {
            $update['referrer'] = $referrer;
        }
        if ($ua !== '') {
            $update['user_agent'] = $ua;
        }

        $affected = SeoNotFound::query()->where('uri_hash', $hash)->update($update);
        if ($affected === 0) {
            try {
                SeoNotFound::query()->create([
                    'uri' => $uri,
                    'uri_hash' => $hash,
                    'referrer' => $referrer,
                    'user_agent' => $ua,
                    'hits' => 1,
                    'last_seen_at' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // برخوردِ unique در شرایطِ هم‌زمانی → فقط increment.
                SeoNotFound::query()->where('uri_hash', $hash)->update($update);
            }
        }

        return response()->json(['ok' => true], 201);
    }
}
