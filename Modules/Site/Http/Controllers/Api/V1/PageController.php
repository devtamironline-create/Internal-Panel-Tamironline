<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Site\Services\PageSectionService;

/**
 * نقطه‌ی پایان محتوای یک صفحه برای فرانت Next.js.
 *
 * فرانت با درخواست GET /v1/pages/{slug} ساختار زیر می‌گیرد:
 *
 *   {
 *     "slug": "home",
 *     "sections": {
 *       "hero":   { ...payload از DB... },
 *       "why_us": { ... },
 *       "faq":    { ..., "faq_ids_items": [...resolved...] }
 *     }
 *   }
 *
 * فقط سکشن‌های منتشرشده برمی‌گردند. فیلدهای reference (faqs، testimonials،
 * brands) به‌صورت خودکار hydrate می‌شوند تا فرانت round-trip اضافه ندهد.
 */
class PageController extends Controller
{
    public function __construct(private PageSectionService $sections)
    {
    }

    public function show(string $slug): JsonResponse
    {
        if (! $this->sections->pageExists($slug)) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $sections = $this->sections->loadForPublic($slug);

        return response()
            ->json([
                'slug'     => $slug,
                'sections' => (object) $sections,
            ])
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }
}
