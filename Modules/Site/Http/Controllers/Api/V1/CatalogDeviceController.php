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

        // تعداد کل دستگاه‌های فعال — برای نمایش «+N خدمت دیگر» در فرانت.
        $total = Device::query()->where('is_active', true)->count();

        return response()
            ->json([
                'data'  => $data,
                'meta'  => ['total' => $total],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/catalog/devices/{slug} — جزئیات یک دستگاه با تمام فیلدهای CMS.
     *
     * هر فیلد null یعنی «ادمین چیزی وارد نکرده» — فرانت از fixture استفاده می‌کند.
     */
    public function show(string $slug): JsonResponse
    {
        $device = Device::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $device) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()
            ->json([
                'id'               => (int) $device->id,
                'label'            => $device->name,
                'slug'             => $device->slug,
                'name'             => $device->service_name ?? $device->name, // عنوان صفحه
                'short_name'       => $device->short_name,
                'description'      => $device->description,
                'service_name'     => $device->service_name,
                'technician_name'  => $device->technician_name,
                'starting_price'   => $device->starting_price !== null ? (int) $device->starting_price : null,
                'accent'           => $device->accent,
                'bg'               => $device->bg,
                'icon'             => $device->icon,
                'thumbnail'        => MediaUrl::resolve($device->thumbnail),
                'tone'             => $device->tone, // CSS class (مثل tone-blue) — برای backward compat
                'issues'           => $device->issues ?? [],
                'faq'              => $device->faq ?? [],
                'meta_title'       => $device->meta_title,
                'meta_description' => $device->meta_description,
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }
}
