<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Brand;
use Modules\Site\Services\PageSectionService;
use Modules\Site\Support\CatalogMerger;
use Modules\Site\Support\MediaUrl;

/**
 * Catalog endpoints برای برندها.
 *
 * Detail از الگوی Template + Override (مثل devices) استفاده می‌کند.
 * Template در page_sections.brand با placeholderهای {brand}, {brand_slug}.
 */
class CatalogBrandController extends Controller
{
    public function __construct(private PageSectionService $sections)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min($limit, 100));

        $query = Brand::query()->active()->ordered();

        if ($request->boolean('featured')) {
            $query->featured();
        }

        // فیلتر بر اساس device slug — برندهایی که این دستگاه را پشتیبانی می‌کنند
        $deviceSlug = trim((string) $request->query('device', ''));
        if ($deviceSlug !== '') {
            // brand.supported_device_slugs یک JSON array است
            $driver = $query->getQuery()->getConnection()->getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                $query->whereJsonContains('supported_device_slugs', $deviceSlug);
            } else {
                // برای sqlite و سایر driverها: like در JSON serialized
                $query->where('supported_device_slugs', 'like', '%"' . $deviceSlug . '"%');
            }
        }

        $brands = $query->limit($limit)->get(['id', 'name', 'slug', 'logo']);

        $data = $brands->map(fn (Brand $b) => [
            'id'   => (int) $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'logo' => MediaUrl::resolve($b->logo),
        ])->values();

        $total = Brand::query()->where('is_active', true)->count();

        return response()
            ->json([
                'data' => $data,
                'meta' => ['total' => $total],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/catalog/brands/{slug} — جزئیات یک برند با merge logic.
     */
    public function show(string $slug): JsonResponse
    {
        $brand = Brand::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $brand) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $context = [
            'brand'      => $brand->name,
            'brand_slug' => $brand->slug,
            'page_title' => $brand->name,
        ];
        $template = $this->sections->pageExists('brand')
            ? $this->sections->loadForPublic('brand', $context)
            : [];

        $flat = CatalogMerger::flattenTemplate($template);

        return response()
            ->json([
                'id'               => (int) $brand->id,
                'name'             => $brand->name,
                'slug'             => $brand->slug,
                'logo'             => MediaUrl::resolve($brand->logo),

                // متن‌ها: override ?? template
                'tagline'          => CatalogMerger::pick($brand->tagline, $flat['tagline'] ?? null),
                'description'      => CatalogMerger::pick($brand->description, $flat['description'] ?? null),

                // ظاهر (فقط override — رنگ‌ها معمولاً اختصاصی برند هستند)
                'tone'             => $brand->tone,
                'bg'               => $brand->bg,

                // آرایه‌ها: override ?? template
                'stats'            => CatalogMerger::pick($brand->stats, CatalogMerger::templateStats($template)),
                'issues'           => CatalogMerger::pick($brand->issues, CatalogMerger::templateIssues($template)),
                'why_us'           => CatalogMerger::pick($brand->why_us, CatalogMerger::templateWhyUs($template)),
                'faq'              => CatalogMerger::pick($brand->faq, CatalogMerger::templateFaq($template)),

                // گارانتی و پشتیبانی
                'warranty_text'    => CatalogMerger::pick($brand->warranty_text, $flat['warranty_text'] ?? null),
                'support_info'     => CatalogMerger::pick($brand->support_info, $flat['support_info'] ?? null),

                // SEO
                'meta_title'       => CatalogMerger::pick($brand->meta_title, $flat['meta_title'] ?? null),
                'meta_description' => CatalogMerger::pick($brand->meta_description, $flat['meta_description'] ?? null),
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }
}
