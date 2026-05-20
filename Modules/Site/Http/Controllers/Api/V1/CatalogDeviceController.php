<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Device;
use Modules\Site\Support\MediaUrl;

/**
 * فهرست دستگاه‌ها برای مصرف فرانت — منبع: Modules\CRM\Models\Device.
 *
 * فقط دستگاه‌های فعال برمی‌گردد. با ?featured=true فقط دستگاه‌های ویژه
 * (پرچم is_featured) خروجی می‌شوند — مناسب لیست خدمات اصلی صفحه‌ی Home
 * در صورتی که فرانت بخواهد به‌جای hero.services_items مستقیماً صدا بزند.
 */
class CatalogDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min($limit, 100));

        $query = Device::query()
            ->active()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $devices = $query->limit($limit)->get(['id', 'name', 'slug', 'icon', 'thumbnail', 'tone']);

        $data = $devices->map(fn (Device $d) => [
            'id'        => (int) $d->id,
            'label'     => $d->name,
            'slug'      => $d->slug,
            'href'      => '/devices/' . $d->slug,
            'icon'      => $d->icon,
            'thumbnail' => MediaUrl::resolve($d->thumbnail),
            'tone'      => $d->tone,
        ])->values();

        return response()
            ->json(['data' => $data])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }
}
