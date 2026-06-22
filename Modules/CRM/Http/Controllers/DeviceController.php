<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Models\DeviceBrandPage;
use Modules\CRM\Models\DeviceCategory;
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Models\Faq;
use Modules\Site\Models\Review;
use Modules\Site\Models\Taxonomy;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::query();

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        if (! is_null($request->query('active'))) {
            $query->where('is_active', $request->boolean('active'));
        }
        if (! is_null($request->query('featured'))) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        $devices = $query->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('crm::devices.index', compact('devices'));
    }

    public function create()
    {
        return view('crm::devices.create', $this->formData(null));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $validated['thumbnail'] = $this->handleThumbnail($request, null);

        $faqIds = $this->extractFaqIds($validated);
        $faqCategoryIds = $this->extractFaqCategoryIds($validated);
        $reviewIds = $this->extractReviewIds($validated);
        $brandIds = $this->extractBrandIds($validated);
        $this->applyDefaults($validated, true);

        $device = Device::create($validated);
        $device->faqs()->sync($this->withSortOrder($faqIds));
        $device->faqCategories()->sync($this->withSortOrder($faqCategoryIds));
        $device->reviews()->sync($reviewIds);
        $device->brands()->sync($this->withSortOrder($brandIds));

        // ثبت Media use برای reverse-lookup
        $device->detachAllMedia();
        if (! empty($device->thumbnail_media_id)) {
            $device->attachMedia((int) $device->thumbnail_media_id, 'thumbnail');
        }

        // Auto-create صفحه‌ی ترکیبی برای هر pair جدید (device, brand)
        foreach ($brandIds as $brandId) {
            DeviceBrandPage::ensureForPair((int) $device->id, (int) $brandId);
        }

        $this->purgeDeviceCache($device);

        return redirect()->route('crm.devices.index')
            ->with('success', 'دستگاه با موفقیت اضافه شد.');
    }

    public function edit(Device $device)
    {
        return view('crm::devices.edit', array_merge(['device' => $device], $this->formData($device)));
    }

    public function update(Request $request, Device $device)
    {
        $validated = $this->validateRequest($request, $device->id);
        $validated['thumbnail'] = $this->handleThumbnail($request, $device);

        $faqIds = $this->extractFaqIds($validated);
        $faqCategoryIds = $this->extractFaqCategoryIds($validated);
        $reviewIds = $this->extractReviewIds($validated);
        $brandIds = $this->extractBrandIds($validated);
        $this->applyDefaults($validated, false);

        $device->update($validated);
        $device->faqs()->sync($this->withSortOrder($faqIds));
        $device->faqCategories()->sync($this->withSortOrder($faqCategoryIds));
        $device->reviews()->sync($reviewIds);
        $device->brands()->sync($this->withSortOrder($brandIds));

        // ثبت Media use برای reverse-lookup
        $device->detachAllMedia();
        if (! empty($device->thumbnail_media_id)) {
            $device->attachMedia((int) $device->thumbnail_media_id, 'thumbnail');
        }

        // Auto-create صفحه‌ی ترکیبی برای هر pair جدید (device, brand)
        foreach ($brandIds as $brandId) {
            DeviceBrandPage::ensureForPair((int) $device->id, (int) $brandId);
        }

        $this->purgeDeviceCache($device);

        return redirect()->route('crm.devices.index')
            ->with('success', 'دستگاه ویرایش شد.');
    }

    /**
     * پاک‌سازیِ کشِ فرانت برای صفحه‌ی این دستگاه (مسیر + تگ). بدونِ این،
     * تغییراتِ ادمین (FAQ، محتوا، …) تا انقضای کش روی سایت دیده نمی‌شوند.
     */
    private function purgeDeviceCache(Device $device): void
    {
        app(\Modules\Site\Support\FrontendRevalidator::class)->purge(
            ['/services/'.$device->slug, '/devices/'.$device->slug],
            ['device:'.$device->slug],
        );
    }

    /**
     * فهرست FAQها و reviewهای موجود + idهای انتخاب‌شده برای فرم.
     *
     * @return array<string, mixed>
     */
    private function formData(?Device $device): array
    {
        return [
            'deviceCategories' => DeviceCategory::query()->ordered()->get(['id', 'name']),
            'allFaqs' => Faq::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get(['id', 'question']),
            'selectedFaqIds' => $device ? $device->faqs()->pluck('faqs.id')->all() : [],
            'allFaqCategories' => Taxonomy::query()
                ->ofType(Taxonomy::TYPE_FAQ)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->withCount(['faqs' => fn ($q) => $q->where('is_published', true)])
                ->get(['id', 'slug', 'name']),
            'selectedFaqCategoryIds' => $device
                ? $device->faqCategories()->pluck('site_taxonomies.id')->map(fn ($i) => (int) $i)->all()
                : [],
            'allReviews' => Review::query()
                ->where('status', Review::STATUS_APPROVED)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(['id', 'type', 'author_name', 'topic', 'rating', 'content']),
            'selectedReviewIds' => $device ? $device->reviews()->pluck('site_reviews.id')->all() : [],
            'allBrands' => Brand::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'logo']),
            'selectedBrandIds' => $device
                ? $device->brands()->pluck('crm_brands.id')->map(fn ($i) => (int) $i)->all()
                : [],
        ];
    }

    public function destroy(Device $device)
    {
        $this->deleteStoredImage($device->thumbnail);
        $device->delete();

        return redirect()->route('crm.devices.index')
            ->with('success', 'دستگاه حذف شد.');
    }

    public function toggle(Request $request, Device $device, string $flag)
    {
        if (! in_array($flag, ['is_active', 'is_active_app', 'is_featured'], true)) {
            abort(404);
        }
        // فعال/غیرفعال‌سازیِ نمایش در سایت یا اپ فقط با دسترسی مدیر کل.
        if (in_array($flag, ['is_active', 'is_active_app'], true) && ! $request->user()?->can('manage-permissions')) {
            abort(403, 'فعال/غیرفعال‌سازی دستگاه فقط با دسترسی مدیر کل امکان‌پذیر است.');
        }
        $device->{$flag} = ! $device->{$flag};
        $device->save();

        // پاک‌سازیِ کشِ فرانت برای صفحاتِ متأثر (fire-and-forget).
        if (in_array($flag, ['is_active', 'is_active_app'], true)) {
            app(\Modules\Site\Support\FrontendRevalidator::class)
                ->purgePaths(['/services/'.$device->slug, '/services', '/']);
        }

        return back()->with('success', 'به‌روز شد.');
    }

    /**
     * فعال/غیرفعال‌سازی گروهیِ دستگاه‌ها روی سایت یا اپ — فقط مدیر کل.
     * گیتِ سطح روت این را تضمین می‌کند و اینجا نیز دوباره چک می‌شود.
     */
    public function bulkToggle(Request $request)
    {
        if (! $request->user()?->can('manage-permissions')) {
            abort(403, 'عملیات گروهیِ فعال/غیرفعال‌سازی فقط با دسترسی مدیر کل امکان‌پذیر است.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:crm_devices,id',
            'target' => 'required|in:is_active,is_active_app',
            'action' => 'required|in:activate,deactivate',
        ]);

        $activate = $validated['action'] === 'activate';
        $count = Device::whereIn('id', $validated['ids'])->update([$validated['target'] => $activate]);

        $where = $validated['target'] === 'is_active_app' ? 'اپ' : 'سایت';
        $verb = $activate ? 'فعال' : 'غیرفعال';
        $message = "{$count} دستگاه در {$where} {$verb} شد.";

        // تغییرِ گروهی → پاک‌سازیِ کاملِ کشِ فرانت.
        app(\Modules\Site\Support\FrontendRevalidator::class)->purgeAll();

        return back()->with('success', $message);
    }

    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:crm_devices,slug'.($id ? ','.$id : ''),
            'device_category_id' => 'nullable|integer|exists:crm_device_categories,id',
            'thumbnail_media_id' => 'nullable|integer|exists:site_media,id',
            'icon' => 'nullable|string|max:60',
            'tone' => 'nullable|string|max:30',
            'thumbnail' => 'nullable|string|max:500',
            'thumbnail_file' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_active_app' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',

            // CMS-override fields
            'short_name' => 'nullable|string|max:80',
            'subtitle' => 'nullable|string|max:500',
            'eyebrow' => 'nullable|string|max:120',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:200000',
            'cta_primary_label' => 'nullable|string|max:60',
            'cta_primary_url' => 'nullable|string|max:500',
            'cta_primary_icon' => 'nullable|string|max:60',
            'cta_secondary_label' => 'nullable|string|max:60',
            'cta_secondary_url' => 'nullable|string|max:500',
            'cta_secondary_icon' => 'nullable|string|max:60',
            'steps_image_desktop' => 'nullable|string|max:500',
            'steps_image_mobile' => 'nullable|string|max:500',
            'sections_enabled' => 'nullable|array',
            'sections_enabled.*' => 'nullable|boolean',
            'service_name' => 'nullable|string|max:160',
            'technician_name' => 'nullable|string|max:160',
            'starting_price' => 'nullable|integer|min:0',
            'accent' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'bg' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'warranty_text' => 'nullable|string|max:3000',
            'support_info' => 'nullable|string|max:3000',

            'issues' => 'nullable|array',
            'issues.*.title' => 'nullable|string|max:160',
            'issues.*.description' => 'nullable|string|max:1000',

            'faq' => 'nullable|array',
            'faq.*.question' => 'nullable|string|max:300',
            'faq.*.answer' => 'nullable|string|max:5000',

            'service_steps' => 'nullable|array',
            'service_steps.*.title' => 'nullable|string|max:160',
            'service_steps.*.description' => 'nullable|string|max:1000',
            'service_steps.*.icon' => 'nullable|string|max:60',

            'videos' => 'nullable|array',
            'videos.*.title' => 'nullable|string|max:200',
            'videos.*.aparat_id' => 'nullable|string|max:60',
            'videos.*.youtube_id' => 'nullable|string|max:60',
            'videos.*.video_url' => 'nullable|string|max:500',
            'videos.*.description' => 'nullable|string|max:600',
            'videos.*.poster_url' => 'nullable|string|max:500',

            'hero_image' => 'nullable|array',
            'hero_image.desktop_left.url' => 'nullable|string|max:500',
            'hero_image.desktop_left.alt' => 'nullable|string|max:200',
            'hero_image.desktop_right.url' => 'nullable|string|max:500',
            'hero_image.desktop_right.alt' => 'nullable|string|max:200',
            'hero_image.mobile.url' => 'nullable|string|max:500',
            'hero_image.mobile.alt' => 'nullable|string|max:200',

            'faq_ids' => 'nullable|array',
            'faq_ids.*' => 'string|exists:faqs,id',

            'faq_category_ids' => 'nullable|array',
            'faq_category_ids.*' => 'integer|exists:site_taxonomies,id',

            'review_ids' => 'nullable|array',
            'review_ids.*' => 'string|exists:site_reviews,id',

            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'integer|exists:crm_brands,id',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated  passed by reference
     * @return array<int, int>
     */
    private function extractFaqCategoryIds(array &$validated): array
    {
        $ids = array_values(array_map('intval', array_filter((array) ($validated['faq_category_ids'] ?? []), fn ($v) => $v !== null && $v !== '')));
        unset($validated['faq_category_ids']);

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $validated  passed by reference
     * @return array<int, int>
     */
    private function extractBrandIds(array &$validated): array
    {
        $ids = array_values(array_map('intval', array_filter((array) ($validated['brand_ids'] ?? []), fn ($v) => $v !== null && $v !== '')));
        unset($validated['brand_ids']);

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $validated  passed by reference
     * @return array<int, string>
     */
    private function extractFaqIds(array &$validated): array
    {
        $ids = array_values(array_filter((array) ($validated['faq_ids'] ?? []), fn ($v) => is_string($v) && $v !== ''));
        unset($validated['faq_ids']);

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $validated  passed by reference
     * @return array<int, string>
     */
    private function extractReviewIds(array &$validated): array
    {
        $ids = array_values(array_filter((array) ($validated['review_ids'] ?? []), fn ($v) => is_string($v) && $v !== ''));
        unset($validated['review_ids']);

        return array_values(array_unique($ids));
    }

    /**
     * تبدیل آرایه idها به ساختار pivot با sort_order طبق ترتیب آرایه.
     *
     * @param  array<int, string>  $ids
     * @return array<string, array{sort_order: int}>
     */
    private function withSortOrder(array $ids): array
    {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[$id] = ['sort_order' => $i];
        }

        return $out;
    }

    private function applyDefaults(array &$validated, bool $isNew): void
    {
        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $this->assertEnglishSlug($validated['slug']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? ($isNew ? true : false));
        $validated['is_active_app'] = (bool) ($validated['is_active_app'] ?? ($isNew ? true : false));
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        unset($validated['thumbnail_file']);

        // اگر media انتخاب شده، URL آن را در thumbnail بگذار (سازگاری backward)
        if (! empty($validated['thumbnail_media_id'])) {
            $m = \Modules\Site\Models\Media\Media::find($validated['thumbnail_media_id']);
            if ($m) {
                $validated['thumbnail'] = $m->url();
            }
        }

        // نرمالایز hero_image (شکل ۳-اسلاتی) — اگر همه‌ی فیلدها خالی است، null ذخیره می‌شود تا از template fallback شود
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

        // پاک‌سازی HTML خروجی TinyMCE قبل از ذخیره (allowlist tags + attrs)
        if (array_key_exists('description', $validated)) {
            $validated['description'] = HtmlSanitizer::clean($validated['description']);
        }

        // پاکسازی ردیف‌های خالی repeater
        foreach (['issues', 'faq', 'service_steps', 'videos'] as $key) {
            if (isset($validated[$key]) && is_array($validated[$key])) {
                $validated[$key] = $this->cleanRepeater($validated[$key]);
                if (empty($validated[$key])) {
                    $validated[$key] = null;
                }
            }
        }
    }

    /**
     * تضمین اینکه slug فقط با English kebab-case است. URLهای /devices/{slug}
     * در فرانت Next.js مسیرسازی می‌شوند و باید ASCII باشند — slug فارسی
     * مشکل encoding و SEO ایجاد می‌کند.
     */
    private function assertEnglishSlug(string $slug): void
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'اسلاگ باید با حروف کوچک انگلیسی، عدد و خط تیره باشد. اگر نام دستگاه فارسی است، حتماً یک اسلاگ انگلیسی وارد کنید (مثل washing-machine).',
            ]);
        }
    }

    /**
     * حذف ردیف‌های خالی repeater (ردیف‌هایی که هیچ مقدار غیرخالی ندارند).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function cleanRepeater(array $rows): array
    {
        $cleaned = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $hasValue = false;
            foreach ($row as $v) {
                if (is_string($v) && trim($v) !== '') {
                    $hasValue = true;
                    break;
                }
            }
            if ($hasValue) {
                $cleaned[] = array_map(
                    fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v,
                    $row
                );
            }
        }

        return array_values($cleaned);
    }

    private function handleThumbnail(Request $request, ?Device $device): ?string
    {
        $cleared = $request->input('thumbnail_cleared') === '1';

        if ($request->hasFile('thumbnail_file')) {
            $path = $request->file('thumbnail_file')->store('site/devices', 'public');
            if ($device) {
                $this->deleteStoredImage($device->thumbnail);
            }

            return $path;
        }

        if ($cleared) {
            if ($device) {
                $this->deleteStoredImage($device->thumbnail);
            }

            return null;
        }

        $url = trim((string) $request->input('thumbnail', ''));

        return $url === '' ? ($device?->thumbnail) : $url;
    }

    private function deleteStoredImage(?string $value): void
    {
        if (! $value) {
            return;
        }
        if (Str::startsWith($value, ['http://', 'https://', '/'])) {
            return;
        }
        Storage::disk('public')->delete($value);
    }
}
