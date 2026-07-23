<?php

namespace Modules\Seo\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Services\MetaResolver;
use Modules\Seo\Services\SeoableRegistry;

/**
 * GET /v1/seo/meta?type={type}&slug={slug}
 *
 * متای نهاییِ رندرشدهٔ یک آیتم را برای مصرف در generateMetadata فرانت
 * Next.js برمی‌گرداند (title, description, canonical, robots, og, twitter, jsonld).
 */
class MetaController extends Controller
{
    public function __construct(
        private readonly SeoableRegistry $registry,
        private readonly MetaResolver $resolver,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|max:50',
            'slug' => 'required|string|max:255',
        ]);

        if (! $this->registry->has($data['type'])) {
            return response()->json(['message' => 'نوع نامعتبر است.'], 422);
        }

        $model = $this->registry->resolveBySlug($data['type'], $data['slug']);
        if ($model === null) {
            return response()->json(['message' => 'آیتم یافت نشد.'], 404);
        }

        $meta = $this->resolver->resolve($data['type'], $model);

        $payload = ['data' => $meta];
        $etag = '"'.md5(json_encode($payload, JSON_UNESCAPED_UNICODE)).'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response()->json(null, 304)->setEtag(trim($etag, '"'));
        }

        return response()->json($payload)
            ->setEtag(trim($etag, '"'))
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300, stale-while-revalidate=600');
    }
}
