<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Brand;
use Modules\Site\Support\MediaUrl;

/**
 * فهرست برندها برای مصرف فرانت — منبع: Modules\CRM\Models\Brand.
 *
 * فقط برندهای فعال برمی‌گردد. با ?featured=true فقط برندهای ویژه
 * (پرچم is_featured) خروجی می‌شوند — مناسب سکشن H6 صفحه‌ی Home.
 */
class CatalogBrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min($limit, 100));

        $query = Brand::query()
            ->active()
            ->ordered();

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $brands = $query->limit($limit)->get(['id', 'name', 'slug', 'logo']);

        $data = $brands->map(fn (Brand $b) => [
            'id'   => (int) $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
            'logo' => MediaUrl::resolve($b->logo),
        ])->values();

        // تعداد کل برندهای فعال — برای meta.total در فرانت
        $total = Brand::query()->where('is_active', true)->count();

        return response()
            ->json([
                'data' => $data,
                'meta' => ['total' => $total],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/catalog/brands/{slug} — جزئیات یک برند با تمام فیلدهای CMS.
     *
     * هر فیلد null یعنی «ادمین چیزی وارد نکرده» — فرانت از fixture استفاده می‌کند.
     * آرایه‌های خالی هم مثل null عمل می‌کنند (فرانت بررسی .length می‌کند).
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

        return response()
            ->json([
                'id'               => (int) $brand->id,
                'name'             => $brand->name,
                'slug'             => $brand->slug,
                'logo'             => MediaUrl::resolve($brand->logo),
                'tagline'          => $brand->tagline,
                'description'      => $brand->description,
                'tone'             => $brand->tone,
                'bg'               => $brand->bg,
                'stats'            => $brand->stats ?? [],
                'issues'           => $brand->issues ?? [],
                'why_us'           => $brand->why_us ?? [],
                'faq'              => $brand->faq ?? [],
                'meta_title'       => $brand->meta_title,
                'meta_description' => $brand->meta_description,
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }
}
