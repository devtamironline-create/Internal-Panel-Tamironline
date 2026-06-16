<?php

namespace Modules\Seo\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoRedirect;

/**
 * GET /v1/seo/redirects
 *
 * فهرست ریدایرکت‌های فعال برای middleware.ts فرانت. فرانت تطبیق را با
 * همان منطق RedirectMatcher انجام می‌دهد.
 */
class RedirectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rules = SeoRedirect::query()->active()
            ->orderBy('id')
            ->get(['source', 'target', 'status_code', 'match_type'])
            ->map(fn ($r) => [
                'source' => $r->source,
                'target' => $r->target,
                'status_code' => $r->status_code,
                'match_type' => $r->match_type,
            ])
            ->all();

        $payload = ['data' => $rules];
        $etag = '"'.md5(json_encode($payload, JSON_UNESCAPED_UNICODE)).'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->json(null, 304)->setEtag(trim($etag, '"'));
        }

        return response()->json($payload)
            ->setEtag(trim($etag, '"'))
            ->header('Cache-Control', 'public, max-age=120, s-maxage=120');
    }
}
