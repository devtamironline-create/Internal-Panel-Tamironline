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
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Models\Faq;
use Modules\Site\Models\Review;
use Modules\Site\Models\Taxonomy;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $query = Brand::query();

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

        $brands = $query->orderBy('sort_order')->orderBy('name')->paginate(20)->withQueryString();

        return view('crm::brands.index', compact('brands'));
    }

    public function create()
    {
        return view('crm::brands.create', $this->formData(null));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        $logoPath = $this->handleLogo($request, null);
        $validated['logo'] = $logoPath;

        $deviceIds = $this->extractDeviceIds($validated);
        $faqIds = $this->extractFaqIds($validated);
        $faqCategoryIds = $this->extractFaqCategoryIds($validated);
        $reviewIds = $this->extractReviewIds($validated);
        $this->applyDefaults($validated, true);

        $brand = Brand::create($validated);
        $brand->devices()->sync($this->withSortOrder($deviceIds));
        $brand->faqs()->sync($this->withSortOrder($faqIds));
        $brand->faqCategories()->sync($this->withSortOrder($faqCategoryIds));
        $brand->reviews()->sync($reviewIds);

        // ثبت Media use برای reverse-lookup
        $brand->detachAllMedia();
        if (! empty($brand->logo_media_id)) {
            $brand->attachMedia((int) $brand->logo_media_id, 'logo');
        }

        // Auto-create صفحه‌ی ترکیبی برای هر pair جدید (device, brand)
        foreach ($deviceIds as $deviceId) {
            DeviceBrandPage::ensureForPair((int) $deviceId, (int) $brand->id);
        }

        return redirect()->route('crm.brands.index')
            ->with('success', 'برند با موفقیت اضافه شد.');
    }

    public function edit(Brand $brand)
    {
        return view('crm::brands.edit', array_merge(['brand' => $brand], $this->formData($brand)));
    }

    public function update(Request $request, Brand $brand)
    {
        $validated = $this->validateRequest($request, $brand->id);
        $logoPath = $this->handleLogo($request, $brand);
        $validated['logo'] = $logoPath;

        $deviceIds = $this->extractDeviceIds($validated);
        $faqIds = $this->extractFaqIds($validated);
        $faqCategoryIds = $this->extractFaqCategoryIds($validated);
        $reviewIds = $this->extractReviewIds($validated);
        $this->applyDefaults($validated, false);

        $brand->update($validated);
        $brand->devices()->sync($this->withSortOrder($deviceIds));
        $brand->faqs()->sync($this->withSortOrder($faqIds));
        $brand->faqCategories()->sync($this->withSortOrder($faqCategoryIds));
        $brand->reviews()->sync($reviewIds);

        // ثبت Media use برای reverse-lookup
        $brand->detachAllMedia();
        if (! empty($brand->logo_media_id)) {
            $brand->attachMedia((int) $brand->logo_media_id, 'logo');
        }

        // Auto-create صفحه‌ی ترکیبی برای هر pair جدید (device, brand)
        foreach ($deviceIds as $deviceId) {
            DeviceBrandPage::ensureForPair((int) $deviceId, (int) $brand->id);
        }

        return redirect()->route('crm.brands.index')
            ->with('success', 'برند ویرایش شد.');
    }

    /**
     * منابع picker و idهای انتخاب‌شده برای فرم.
     *
     * @return array<string, mixed>
     */
    private function formData(?Brand $brand): array
    {
        return [
            'allDevices' => Device::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'thumbnail', 'icon']),
            'selectedDeviceIds' => $brand
                ? $brand->devices()->pluck('crm_devices.id')->map(fn ($i) => (int) $i)->all()
                : [],
            'allFaqs' => Faq::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get(['id', 'question', 'answer']),
            'selectedFaqIds' => $brand ? $brand->faqs()->pluck('faqs.id')->all() : [],
            'allFaqCategories' => Taxonomy::query()
                ->ofType(Taxonomy::TYPE_FAQ)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->withCount(['faqs' => fn ($q) => $q->where('is_published', true)])
                ->get(['id', 'slug', 'name']),
            'selectedFaqCategoryIds' => $brand
                ? $brand->faqCategories()->pluck('site_taxonomies.id')->map(fn ($i) => (int) $i)->all()
                : [],
            'allReviews' => Review::query()
                ->where('status', Review::STATUS_APPROVED)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(['id', 'type', 'author_name', 'topic', 'rating', 'content']),
            'selectedReviewIds' => $brand ? $brand->reviews()->pluck('site_reviews.id')->all() : [],
        ];
    }

    public function destroy(Brand $brand)
    {
        $this->deleteStoredImage($brand->logo);
        $brand->delete();

        return redirect()->route('crm.brands.index')
            ->with('success', 'برند حذف شد.');
    }

    /**
     * تغییر inline یکی از پرچم‌های is_active / is_featured.
     */
    public function toggle(Request $request, Brand $brand, string $flag)
    {
        if (! in_array($flag, ['is_active', 'is_featured'], true)) {
            abort(404);
        }
        // فعال/غیرفعال‌سازیِ نمایش در سایت فقط با دسترسی مدیر کل.
        if ($flag === 'is_active' && ! $request->user()?->can('manage-permissions')) {
            abort(403, 'فعال/غیرفعال‌سازی برند فقط با دسترسی مدیر کل امکان‌پذیر است.');
        }
        $brand->{$flag} = ! $brand->{$flag};
        $brand->save();

        return back()->with('success', 'به‌روز شد.');
    }

    /**
     * فعال/غیرفعال‌سازی گروهیِ برندها — فقط مدیر کل (manage-permissions).
     * گیتِ سطح روت این را تضمین می‌کند و اینجا نیز دوباره چک می‌شود.
     */
    public function bulkToggle(Request $request)
    {
        if (! $request->user()?->can('manage-permissions')) {
            abort(403, 'عملیات گروهیِ فعال/غیرفعال‌سازی فقط با دسترسی مدیر کل امکان‌پذیر است.');
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:crm_brands,id',
            'action' => 'required|in:activate,deactivate',
        ]);

        $activate = $validated['action'] === 'activate';
        $count = Brand::whereIn('id', $validated['ids'])->update(['is_active' => $activate]);

        $message = $activate
            ? "{$count} برند فعال شد."
            : "{$count} برند غیرفعال شد.";

        return back()->with('success', $message);
    }

    private function validateRequest(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:crm_brands,slug'.($id ? ','.$id : ''),
            'logo' => 'nullable|string|max:500',
            'logo_file' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'logo_media_id' => 'nullable|integer|exists:site_media,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',

            // CMS-override fields
            'tagline' => 'nullable|string|max:1000',
            'eyebrow' => 'nullable|string|max:120',
            'subtitle' => 'nullable|string|max:500',
            'caption' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:200000',
            'tone' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'bg' => 'nullable|string|max:20|regex:/^#[0-9a-fA-F]{3,8}$/',
            'meta_title' => 'nullable|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'warranty_text' => 'nullable|string|max:3000',
            'support_info' => 'nullable|string|max:3000',
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

            'stats' => 'nullable|array',
            'stats.*.value' => 'nullable|string|max:60',
            'stats.*.label' => 'nullable|string|max:120',

            'issues' => 'nullable|array',
            'issues.*.title' => 'nullable|string|max:160',
            'issues.*.description' => 'nullable|string|max:1000',
            'issues.*.icon' => 'nullable|string|max:60',

            'why_us' => 'nullable|array',
            'why_us.*.title' => 'nullable|string|max:160',
            'why_us.*.description' => 'nullable|string|max:1000',
            'why_us.*.icon' => 'nullable|string|max:60',

            'faq' => 'nullable|array',
            'faq.*.question' => 'nullable|string|max:300',
            'faq.*.answer' => 'nullable|string|max:5000',

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

            'device_ids' => 'nullable|array',
            'device_ids.*' => 'integer|exists:crm_devices,id',

            'faq_ids' => 'nullable|array',
            'faq_ids.*' => 'string|exists:faqs,id',

            'faq_category_ids' => 'nullable|array',
            'faq_category_ids.*' => 'integer|exists:site_taxonomies,id',

            'review_ids' => 'nullable|array',
            'review_ids.*' => 'string|exists:site_reviews,id',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated  passed by reference
     * @return array<int>
     */
    private function extractDeviceIds(array &$validated): array
    {
        $ids = array_map('intval', (array) ($validated['device_ids'] ?? []));
        unset($validated['device_ids']);

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
     * @return array<int, string>
     */
    private function extractReviewIds(array &$validated): array
    {
        $ids = array_values(array_filter((array) ($validated['review_ids'] ?? []), fn ($v) => is_string($v) && $v !== ''));
        unset($validated['review_ids']);

        return array_values(array_unique($ids));
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

    private function applyDefaults(array &$validated, bool $isNew): void
    {
        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);
        $this->assertEnglishSlug($validated['slug']);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = (bool) ($validated['is_active'] ?? ($isNew ? true : false));
        $validated['is_featured'] = (bool) ($validated['is_featured'] ?? false);
        unset($validated['logo_file']);

        // اگر media انتخاب شده، URL آن را در logo بگذار (سازگاری backward)
        if (! empty($validated['logo_media_id'])) {
            $m = \Modules\Site\Models\Media\Media::find($validated['logo_media_id']);
            if ($m) {
                $validated['logo'] = $m->url();
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

        // پاکسازی ردیف‌های خالی repeater (هر آرایه که هیچ مقدار غیرخالی ندارد حذف می‌شود)
        foreach (['stats', 'issues', 'why_us', 'faq', 'videos'] as $key) {
            if (isset($validated[$key]) && is_array($validated[$key])) {
                $validated[$key] = $this->cleanRepeater($validated[$key]);
                if (empty($validated[$key])) {
                    $validated[$key] = null;
                }
            }
        }
    }

    /**
     * تضمین اینکه slug فقط با English kebab-case است. URLهای /brands/{slug}
     * در فرانت Next.js مسیرسازی می‌شوند و باید ASCII باشند.
     */
    private function assertEnglishSlug(string $slug): void
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/', $slug)) {
            throw ValidationException::withMessages([
                'slug' => 'اسلاگ باید با حروف کوچک انگلیسی، عدد و خط تیره باشد. اگر نام برند فارسی است، حتماً یک اسلاگ انگلیسی وارد کنید (مثل samsung، lg).',
            ]);
        }
    }

    /**
     * حذف ردیف‌هایی که هیچ فیلد غیرخالی ندارند.
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

    /**
     * هندل لوگو: اگر فایل آپلود شده باشد ذخیره می‌شود و URL قبلی پاک می‌شود.
     * اگر admin روی «حذف تصویر» زده باشد ({field}_cleared=1)، فایل قدیمی پاک
     * و logo=null می‌شود. در غیر این صورت URL تایپ‌شده در فرم استفاده می‌شود.
     */
    private function handleLogo(Request $request, ?Brand $brand): ?string
    {
        $cleared = $request->input('logo_cleared') === '1';

        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('site/brands', 'public');
            if ($brand) {
                $this->deleteStoredImage($brand->logo);
            }

            return $path;
        }

        if ($cleared) {
            if ($brand) {
                $this->deleteStoredImage($brand->logo);
            }

            return null;
        }

        $url = trim((string) $request->input('logo', ''));

        return $url === '' ? ($brand?->logo) : $url;
    }

    /**
     * فقط فایل‌هایی که در storage/app/public نگه‌داری شده‌اند را حذف می‌کند؛
     * URL خارجی نباید پاک شود.
     */
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
