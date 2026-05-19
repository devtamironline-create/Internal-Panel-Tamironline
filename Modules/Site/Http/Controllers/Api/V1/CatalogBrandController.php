<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Brand;

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
            'logo' => $b->logo,
        ])->values();

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }
}
