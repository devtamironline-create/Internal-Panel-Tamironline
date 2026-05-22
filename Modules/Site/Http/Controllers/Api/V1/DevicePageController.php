<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\CRM\Models\Device;
use Modules\Site\Services\PageSectionService;
use Modules\Site\Support\MediaUrl;

/**
 * صفحه‌ی هر دستگاه — /v1/devices/{slug}.
 *
 * پاسخ شامل اطلاعات دستگاه و سکشن‌های منتشرشده‌ی صفحه‌ی الگوی device است
 * (در صورت تعریف در schema). Placeholder‌های متن FAQ/Testimonial با
 * context صفحه (نام دستگاه و اسلاگ) جایگزین می‌شوند.
 */
class DevicePageController extends Controller
{
    public function __construct(private PageSectionService $sections)
    {
    }

    public function show(string $slug): JsonResponse
    {
        $device = Device::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first(['id', 'name', 'slug', 'icon', 'thumbnail', 'tone']);

        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $context = [
            'device'      => $device->name,
            'device_slug' => $device->slug,
            'page_title'  => $device->name,
        ];

        // اگر در آینده صفحه‌ی الگوی 'device' در schema تعریف شد، سکشن‌هایش را
        // با همین context برمی‌گردانیم. در غیر این صورت آرایه‌ی خالی.
        $sections = $this->sections->pageExists('device')
            ? $this->sections->loadForPublic('device', $context)
            : (object) [];

        return response()
            ->json([
                'device' => [
                    'id'        => (int) $device->id,
                    'label'     => $device->name,
                    'slug'      => $device->slug,
                    'href'      => '/devices/' . $device->slug,
                    'icon'      => $device->icon,
                    'thumbnail' => MediaUrl::resolve($device->thumbnail),
                    'tone'      => $device->tone,
                ],
                'sections' => $sections,
            ])
            ->header('Cache-Control', 'public, max-age=300, s-maxage=300');
    }
}
