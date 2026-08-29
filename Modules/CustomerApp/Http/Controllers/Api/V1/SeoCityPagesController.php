<?php

namespace Modules\CustomerApp\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\CityPage;

/**
 * GET /v1/customer/seo/city-pages — صفحاتِ سئوِ شهریِ «منتشرشده» (SEO-024).
 *
 * مصرف‌کننده: فرانتِ Next.js (tamironline.com) برای رندرِ صفحاتِ
 *   /{city}, /{city}/services, /{city}/services/{device},
 *   /{city}/brands, /{city}/brands/{brand}, /{city}/services/{device}/{brand}
 *
 * قواعدِ مهم برای تیمِ سایت:
 *   - فقط pathهایی که این‌جا برمی‌گردند باید روی سایت وجود داشته باشند؛
 *     هر مسیرِ دیگری زیرِ این الگوها باید «۴۰۴ واقعی» بدهد.
 *   - صفحاتِ پیش‌نویس این‌جا نمی‌آیند (تا تاییدِ مدیر منتشر نشوند).
 *
 * دو حالت:
 *   بدونِ پارامتر یا ?city=mashhad  → فهرستِ صفحات (برای ساختِ مسیرها/sitemap)
 *   ?path=/mashhad/services/...     → یک صفحهٔ کامل، یا ۴۰۴ اگر منتشر نشده
 */
class SeoCityPagesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = CityPage::query()
            ->published()
            ->with(['city:id,name,slug', 'device:id,name,slug', 'brand:id,name,slug']);

        // واکشیِ یک صفحه با مسیرِ دقیق.
        if ($path = trim((string) $request->query('path'))) {
            $page = $query->where('path', $path)->first();

            if (! $page) {
                return response()->json(['message' => 'صفحه یافت نشد.'], 404);
            }

            return response()->json(['data' => $this->transform($page)])
                ->header('Cache-Control', 'public, max-age=3600');
        }

        // فیلترِ اختیاریِ شهر.
        if ($citySlug = trim((string) $request->query('city'))) {
            $query->whereHas('city', fn ($q) => $q->where('slug', $citySlug));
        }

        $pages = $query
            ->orderBy('city_id')
            ->orderByRaw("CASE type WHEN 'city' THEN 1 WHEN 'services' THEN 2 WHEN 'device' THEN 3 WHEN 'brands' THEN 4 WHEN 'brand' THEN 5 WHEN 'combo' THEN 6 ELSE 7 END")
            ->get();

        // پاسخِ CustomerApp خودکار در {success, data} پیچیده می‌شود؛ پس
        // فقط data برمی‌گردانیم (کلیدهای اضافه توسطِ wrapper حذف می‌شوند).
        return response()->json([
            'data' => $pages->map(fn (CityPage $p) => $this->transform($p))->values(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }

    /** @return array<string, mixed> */
    private function transform(CityPage $page): array
    {
        return [
            'path' => $page->path,
            'type' => $page->type,
            'city' => [
                'name' => $page->city?->name,
                'slug' => $page->city?->slug,
            ],
            'device' => $page->device ? ['name' => $page->device->name, 'slug' => $page->device->slug] : null,
            'brand' => $page->brand ? ['name' => $page->brand->name, 'slug' => $page->brand->slug] : null,
            'title' => $page->title,
            'h1' => $page->h1,
            'meta_description' => $page->meta_description,
            'content' => $page->content,
            'published_at' => $page->published_at?->toIso8601String(),
        ];
    }
}
