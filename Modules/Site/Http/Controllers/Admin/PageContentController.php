<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\Site\Models\Faq;
use Modules\Site\Models\Review;
use Modules\Site\Models\Taxonomy;
use Modules\Site\Services\PageSectionService;

class PageContentController extends Controller
{
    public function __construct(private PageSectionService $sections) {}

    private function checkAccess(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('manage-site-pages')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->checkAccess();

        $pages = [];
        foreach ($this->sections->pages() as $slug => $title) {
            $schemaSections = $this->sections->sectionsOf($slug);
            $loaded = $this->sections->loadForAdmin($slug);

            $filled = 0;
            $published = 0;
            foreach ($loaded as $section) {
                if (! empty(array_filter($section['payload'] ?? [], fn ($v) => ! is_null($v) && $v !== '' && $v !== []))) {
                    $filled++;
                }
                if ($section['is_published']) {
                    $published++;
                }
            }

            $pages[] = [
                'slug' => $slug,
                'title' => $title,
                'sections_count' => count($schemaSections),
                'filled' => $filled,
                'published' => $published,
            ];
        }

        return view('site::admin.page-content.index', compact('pages'));
    }

    public function edit(string $slug): View
    {
        $this->checkAccess();

        if (! $this->sections->pageExists($slug)) {
            abort(404, 'صفحه‌ی مورد نظر در schema تعریف نشده است.');
        }

        $comboDevice = $this->resolveComboDevice($slug);
        $title = $comboDevice
            ? 'الگوی ترکیبی دستگاه — '.$comboDevice->name
            : ($this->sections->pages()[$slug] ?? $slug);
        $schemaSections = $this->sections->sectionsOf($slug);
        $values = $this->sections->loadForAdmin($slug);

        $references = [
            'faqs' => Faq::query()
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get(['id', 'question', 'is_published']),
            'testimonials' => Review::audio()
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get(['id', 'author_name as customer_name', 'topic', 'is_published']),
            'devices' => Device::query()
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'icon', 'tone', 'is_active']),
            'brands' => Brand::query()
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'logo', 'is_active']),
            'device_categories' => \Modules\CRM\Models\DeviceCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'icon', 'tone', 'is_active']),
            'faq_categories' => Taxonomy::ofType(Taxonomy::TYPE_FAQ)
                ->ordered()
                ->get(['id', 'slug', 'name', 'is_active']),
            'testimonial_categories' => Taxonomy::ofType(Taxonomy::TYPE_TESTIMONIAL)
                ->ordered()
                ->get(['id', 'slug', 'name', 'is_active']),
            'banner_zones' => \Modules\Site\Models\BannerZone::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'slug', 'name', 'recommended_width', 'recommended_height']),
        ];

        return view('site::admin.page-content.edit', [
            'slug' => $slug,
            'title' => $title,
            'schemaSections' => $schemaSections,
            'values' => $values,
            'references' => $references,
            'comboDevice' => $comboDevice,
            'comboDevices' => $this->comboDeviceList($slug, $comboDevice),
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $this->checkAccess();

        if (! $this->sections->pageExists($slug)) {
            abort(404);
        }

        $comboDevice = $this->resolveComboDevice($slug);

        $this->sections->saveAll($slug, (array) $request->input('sections', []));

        // پاک‌سازیِ کشِ فرانتِ همین صفحه (fire-and-forget؛ هرگز throw نمی‌کند).
        // برای الگویِ per-device، مسیرهای همه‌ی صفحاتِ ترکیبیِ فعالِ همان دستگاه هم
        // purge می‌شوند (فرانت صفحات را path-based کش می‌کند — مثلِ purgeDeviceCache).
        $tags = ['page:'.$slug];
        $paths = [];
        if ($comboDevice) {
            $tags[] = 'page:device_brand';
            $tags[] = 'device:'.$comboDevice->slug;
            $brandSlugs = \Modules\CRM\Models\Brand::query()
                ->whereIn('id', \Modules\CRM\Models\DeviceBrandPage::query()
                    ->where('device_id', $comboDevice->id)->where('is_active', true)->pluck('brand_id'))
                ->pluck('slug');
            foreach ($brandSlugs as $b) {
                $paths[] = '/services/'.$comboDevice->slug.'/'.$b;
                $paths[] = '/devices/'.$comboDevice->slug.'/'.$b;
            }
        }
        app(\Modules\Site\Support\FrontendRevalidator::class)->purge($paths, $tags);

        return redirect()
            ->route('site.admin.page-content.edit', $slug)
            ->with('success', 'محتوای صفحه ذخیره شد.');
    }

    /**
     * دستگاهِ متناظر با slug مجازیِ «الگوی ترکیبیِ per-device»
     * (device_brand:{device_slug}) — برای slugهای معمولی null.
     */
    private function resolveComboDevice(string $slug): ?Device
    {
        if (! str_starts_with($slug, PageSectionService::DEVICE_COMBO_PREFIX)) {
            return null;
        }

        $deviceSlug = substr($slug, strlen(PageSectionService::DEVICE_COMBO_PREFIX));
        $device = Device::query()->where('slug', $deviceSlug)->first();

        if (! $device) {
            abort(404, 'دستگاهِ این الگو پیدا نشد.');
        }

        return $device;
    }

    /**
     * فهرست دستگاه‌ها برای پنلِ «الگوی اختصاصی هر دستگاه» — فقط روی صفحه‌ی
     * device_brand (سراسری یا per-device). filled = تعداد سکشن‌های منتشرشده‌ی
     * پرشده‌ی الگوی اختصاصیِ آن دستگاه.
     *
     * @return array<int, array{name: string, slug: string, filled: int}>|null
     */
    private function comboDeviceList(string $slug, ?Device $comboDevice): ?array
    {
        if ($slug !== 'device_brand' && ! $comboDevice) {
            return null;
        }

        $filled = \Modules\Site\Models\PageSection::query()
            ->where('page_slug', 'like', PageSectionService::DEVICE_COMBO_PREFIX.'%')
            ->where('is_published', true)
            ->get(['page_slug', 'payload'])
            ->filter(fn ($r) => ! empty(array_filter((array) $r->payload, fn ($v) => $v !== null && $v !== '' && $v !== [])))
            ->groupBy('page_slug')
            ->map->count();

        return Device::query()
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($d) => [
                'name' => $d->name,
                'slug' => $d->slug,
                'filled' => (int) ($filled[PageSectionService::deviceComboSlug($d->slug)] ?? 0),
            ])
            ->all();
    }
}
