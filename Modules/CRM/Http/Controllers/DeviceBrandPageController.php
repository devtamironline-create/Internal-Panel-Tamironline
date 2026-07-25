<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Models\Faq;
use Modules\Site\Models\Review;
use Modules\Site\Models\Taxonomy;

/**
 * مدیریت صفحات ترکیبی (device, brand) — یک رکورد به ازای هر pair
 * که override صفحه‌ی /devices/{deviceSlug}/{brandSlug} را نگه می‌دارد.
 */
class DeviceBrandPageController extends Controller
{
    public function index(Request $request)
    {
        $query = DeviceBrandPage::query()
            ->with(['device:id,name,slug', 'brand:id,name,slug,logo'])
            // دستگاه‌های غیرفعال نباید در فهرست صفحات ترکیبی دیده شوند.
            ->whereHas('device', fn ($q) => $q->where('is_active', true))
            ->latest('updated_at');

        if ($deviceId = $request->query('device_id')) {
            $query->where('device_id', (int) $deviceId);
        }
        if ($brandId = $request->query('brand_id')) {
            $query->where('brand_id', (int) $brandId);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->whereHas('device', fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"))
                    ->orWhereHas('brand', fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"));
            });
        }

        $pages = $query->paginate(20)->withQueryString();

        $devices = Device::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']);
        $brands = Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']);

        return view('crm::device-brand-pages.index', compact('pages', 'devices', 'brands'));
    }

    public function create()
    {
        $page = null;

        return view('crm::device-brand-pages.create', array_merge(['page' => $page], $this->formData($page)));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        $faqIds = $this->extract($validated, 'faq_ids');
        $faqCategoryIds = $this->extract($validated, 'faq_category_ids');
        $reviewIds = $this->extract($validated, 'review_ids');

        $this->applyDefaults($validated);

        $page = DeviceBrandPage::create($validated);
        $page->faqs()->sync($this->withSortOrder($faqIds));
        $page->faqCategories()->sync($this->withSortOrder($faqCategoryIds));
        $page->reviews()->sync($reviewIds);

        $this->purgeFrontend($page);

        return redirect()->route('crm.device-brand-pages.edit', $page->id)
            ->with('success', 'صفحه‌ی ترکیبی ایجاد شد.');
    }

    public function edit(DeviceBrandPage $devicebrandpage)
    {
        $page = $devicebrandpage;

        return view('crm::device-brand-pages.edit', array_merge(['page' => $page], $this->formData($page)));
    }

    public function update(Request $request, DeviceBrandPage $devicebrandpage)
    {
        $page = $devicebrandpage;
        $validated = $this->validateRequest($request, $page->id);

        $faqIds = $this->extract($validated, 'faq_ids');
        $faqCategoryIds = $this->extract($validated, 'faq_category_ids');
        $reviewIds = $this->extract($validated, 'review_ids');

        $this->applyDefaults($validated);

        $page->update($validated);
        $page->faqs()->sync($this->withSortOrder($faqIds));
        $page->faqCategories()->sync($this->withSortOrder($faqCategoryIds));
        $page->reviews()->sync($reviewIds);

        $this->purgeFrontend($page);

        return redirect()->route('crm.device-brand-pages.edit', $page->id)
            ->with('success', 'صفحه‌ی ترکیبی به‌روز شد.');
    }

    public function destroy(DeviceBrandPage $devicebrandpage)
    {
        $devicebrandpage->delete();

        return redirect()->route('crm.device-brand-pages.index')->with('success', 'صفحه حذف شد.');
    }

    /**
     * فعال/غیرفعال‌کردن درون‌خطی یک صفحه‌ی ترکیبی. صفحه‌ی غیرفعال از
     * API سایت حذف می‌شود ولی override‌های ادمین دست‌نخورده می‌ماند.
     */
    public function toggle(Request $request, DeviceBrandPage $devicebrandpage)
    {
        // فعال/غیرفعال‌سازیِ صفحهٔ ترکیبی فقط با دسترسی مدیر کل.
        if (! $request->user()?->can('manage-permissions')) {
            abort(403, 'فعال/غیرفعال‌سازی صفحهٔ ترکیبی فقط با دسترسی مدیر کل امکان‌پذیر است.');
        }
        $devicebrandpage->is_active = ! $devicebrandpage->is_active;
        $devicebrandpage->save();

        $this->purgeFrontend($devicebrandpage);

        return back()->with('success', 'وضعیت صفحه‌ی ترکیبی به‌روز شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?DeviceBrandPage $page): array
    {
        return [
            'allDevices' => Device::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'allBrands' => Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'logo']),
            'allFaqs' => Faq::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get(['id', 'question', 'answer']),
            'allFaqCategories' => Taxonomy::query()
                ->ofType(Taxonomy::TYPE_FAQ)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->withCount(['faqs' => fn ($q) => $q->where('is_published', true)])
                ->get(['id', 'slug', 'name']),
            'allReviews' => Review::query()
                ->where('status', Review::STATUS_APPROVED)
                ->orderByDesc('is_featured')
                ->limit(500)
                ->get(['id', 'type', 'author_name', 'topic', 'rating', 'content']),
            'selectedFaqIds' => $page ? $page->faqs()->pluck('faqs.id')->all() : [],
            'selectedFaqCategoryIds' => $page ? $page->faqCategories()->pluck('site_taxonomies.id')->map(fn ($i) => (int) $i)->all() : [],
            'selectedReviewIds' => $page ? $page->reviews()->pluck('site_reviews.id')->all() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'device_id' => 'required|integer|exists:crm_devices,id|'.($id ? '' : 'unique:crm_device_brand_pages,device_id,NULL,id,brand_id,'.(int) $request->input('brand_id')),
            'brand_id' => 'required|integer|exists:crm_brands,id',
            'is_active' => 'nullable|boolean',

            'eyebrow' => 'nullable|string|max:120',
            'title' => 'nullable|string|max:200',
            'subtitle' => 'nullable|string|max:500',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:200000',

            // تصویرِ Hero اختصاصیِ همین صفحهٔ ترکیبی — همان ساختارِ ۳-اسلاتیِ دستگاه.
            'hero_image' => 'nullable|array',
            'hero_image.desktop_left.url' => 'nullable|string|max:500',
            'hero_image.desktop_left.alt' => 'nullable|string|max:200',
            'hero_image.desktop_right.url' => 'nullable|string|max:500',
            'hero_image.desktop_right.alt' => 'nullable|string|max:200',
            'hero_image.mobile.url' => 'nullable|string|max:500',
            'hero_image.mobile.alt' => 'nullable|string|max:200',

            'cta_primary_label' => 'nullable|string|max:60',
            'cta_primary_url' => 'nullable|string|max:500',
            'cta_primary_icon' => 'nullable|string|max:60',
            'cta_secondary_label' => 'nullable|string|max:60',
            'cta_secondary_url' => 'nullable|string|max:500',
            'cta_secondary_icon' => 'nullable|string|max:60',

            'steps_image_desktop' => 'nullable|string|max:500',
            'steps_image_mobile' => 'nullable|string|max:500',

            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',

            'sections_enabled' => 'nullable|array',
            'sections_enabled.*' => 'nullable|boolean',

            'faq_ids' => 'nullable|array',
            'faq_ids.*' => 'string|exists:faqs,id',

            'faq_category_ids' => 'nullable|array',
            'faq_category_ids.*' => 'integer|exists:site_taxonomies,id',

            'review_ids' => 'nullable|array',
            'review_ids.*' => 'string|exists:site_reviews,id',
        ]);
    }

    /**
     * پاک‌سازیِ کشِ فرانتِ همین صفحهٔ ترکیبی (fire-and-forget؛ هرگز throw نمی‌کند)
     * تا تغییراتِ ادمین (مثلاً تصویرِ Hero) بدونِ انتظار برای TTL دیده شود.
     */
    private function purgeFrontend(DeviceBrandPage $page): void
    {
        try {
            $page->loadMissing('device:id,slug', 'brand:id,slug');
            $d = (string) ($page->device->slug ?? '');
            $b = (string) ($page->brand->slug ?? '');
            if ($d === '' || $b === '') {
                return;
            }

            app(\Modules\Site\Support\FrontendRevalidator::class)->purge(
                ['/services/'.$d.'/'.$b, '/devices/'.$d.'/'.$b],
                ['page:device_brand', 'device:'.$d],
            );
        } catch (\Throwable) {
            // خرابیِ purge نباید ذخیره را بشکند.
        }
    }

    private function applyDefaults(array &$validated): void
    {
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        if (array_key_exists('description', $validated)) {
            $validated['description'] = HtmlSanitizer::clean($validated['description']);
        }

        // نرمالایز hero_image (شکل ۳-اسلاتی) — اگر همهٔ فیلدها خالی است null
        // ذخیره می‌شود تا مثلِ قبل از زنجیرهٔ دستگاه/برند/قالب fallback شود.
        if (array_key_exists('hero_image', $validated)) {
            $hi = \Modules\Site\Services\PageSectionService::normalizeHeroVisual($validated['hero_image']);
            $isEmpty = true;
            foreach (['desktop_left', 'desktop_right', 'mobile'] as $slot) {
                if (! empty($hi[$slot]['url']) || ! empty($hi[$slot]['alt'])) {
                    $isEmpty = false;
                    break;
                }
            }
            $validated['hero_image'] = $isEmpty ? null : $hi;
        }
    }

    /**
     * @param  array<string, mixed>  $validated  passed by reference
     * @return array<int, mixed>
     */
    private function extract(array &$validated, string $key): array
    {
        $vals = (array) ($validated[$key] ?? []);
        unset($validated[$key]);
        $vals = array_values(array_filter($vals, fn ($v) => $v !== null && $v !== ''));

        return array_values(array_unique($vals));
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int|string, array{sort_order: int}>
     */
    private function withSortOrder(array $ids): array
    {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[$id] = ['sort_order' => $i];
        }

        return $out;
    }
}
