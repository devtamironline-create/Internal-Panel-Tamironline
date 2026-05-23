<?php

namespace Modules\Site\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Device;
use Modules\Site\Services\PageSectionService;
use Modules\Site\Support\CatalogMerger;
use Modules\Site\Support\MediaUrl;

/**
 * Catalog endpoints برای دستگاه‌ها.
 *
 * Detail (/v1/catalog/devices/{slug}) از الگوی Template + Override استفاده می‌کند:
 *   - Template از site_page_sections (page_slug='device') با placeholderهای
 *     {device}, {device_label}, {device_slug} پر می‌شود
 *   - Override از فیلدهای CMS در crm_devices خوانده می‌شود
 *   - merge: override (اگر non-null/non-empty) ?? template
 */
class CatalogDeviceController extends Controller
{
    public function __construct(private PageSectionService $sections) {}

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
            'id' => (int) $d->id,
            'label' => $d->name,
            'slug' => $d->slug,
            'href' => '/devices/'.$d->slug,
            'icon' => $d->icon,
            'thumbnail' => MediaUrl::resolve($d->thumbnail),
            'tone' => $d->tone,
        ])->values();

        $total = Device::query()->where('is_active', true)->count();

        return response()
            ->json([
                'data' => $data,
                'meta' => ['total' => $total],
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }

    /**
     * GET /v1/catalog/devices/{slug} — جزئیات یک دستگاه.
     *
     * Merge logic:
     *   override (per-device CMS column) ?? template (page_sections.device با placeholder substitution)
     *
     * فیلدهای null/empty در crm_devices به Template fallback می‌شوند.
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

        // Template با placeholder substitution برای این دستگاه
        $context = [
            'device' => $device->short_name ?? $device->name,
            'device_label' => $device->name,
            'device_slug' => $device->slug,
            'page_title' => $device->service_name ?? $device->name,
        ];
        $template = $this->sections->pageExists('device')
            ? $this->sections->loadForPublic('device', $context)
            : [];

        $flat = CatalogMerger::flattenTemplate($template);

        return response()
            ->json([
                'id' => (int) $device->id,
                'label' => $device->name,
                'slug' => $device->slug,

                // متن‌ها: override ?? template
                'name' => CatalogMerger::pick($device->service_name, $flat['service_name'] ?? $device->name),
                'short_name' => CatalogMerger::pick($device->short_name, $device->name),
                'service_name' => CatalogMerger::pick($device->service_name, $flat['service_name'] ?? null),
                'technician_name' => CatalogMerger::pick($device->technician_name, $flat['technician_name'] ?? null),
                'description' => CatalogMerger::pick($device->description, $flat['description'] ?? null),
                'tagline' => CatalogMerger::pick(null, $flat['tagline'] ?? null),
                'subtitle' => CatalogMerger::pick($device->subtitle, $flat['subtitle'] ?? null),
                'eyebrow' => CatalogMerger::pick($device->eyebrow, $flat['eyebrow'] ?? null),

                // ظاهر
                'starting_price' => $device->starting_price !== null ? (int) $device->starting_price : null,
                'accent' => $device->accent,
                'bg' => $device->bg,
                'icon' => $device->icon,
                'thumbnail' => MediaUrl::resolve($device->thumbnail),
                'tone' => $device->tone, // CSS class

                // آرایه‌ها: override ?? template
                'issues' => CatalogMerger::pick($device->issues, CatalogMerger::templateIssues($template)),
                'faq' => CatalogMerger::pick($device->faq, CatalogMerger::templateFaq($template)),

                // پشتیبانی و گارانتی
                'warranty_text' => CatalogMerger::pick($device->warranty_text, $flat['warranty_text'] ?? null),
                'support_info' => CatalogMerger::pick($device->support_info, $flat['support_info'] ?? null),
                'service_steps' => CatalogMerger::pick($device->service_steps, CatalogMerger::templateServiceSteps($template)),

                // SEO
                'meta_title' => CatalogMerger::pick($device->meta_title, $flat['meta_title'] ?? null),
                'meta_description' => CatalogMerger::pick($device->meta_description, $flat['meta_description'] ?? null),
            ])
            ->header('Cache-Control', 'public, max-age=600, s-maxage=600');
    }
}
