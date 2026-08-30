<?php

namespace Modules\CRM\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CRM\Models\City;
use Modules\CRM\Models\CityPage;
use Modules\CRM\Services\CityPageGenerator;
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Models\Faq;
use Modules\Site\Models\Review;
use Modules\Site\Models\Taxonomy;

/**
 * مدیریتِ صفحاتِ سئوِ شهری (SEO-024). هدفِ طراحی: «خیلی راحت برای ادمین» —
 * یک صفحه به‌ازای هر شهر، صفحات گروه‌بندی‌شده، دکمهٔ همگام‌سازی و انتشارِ
 * یک‌کلیکی.
 */
class CityPageController extends Controller
{
    /** فهرستِ شهرهایِ اصلی + شمارشِ صفحات (نقطهٔ ورود). */
    public function overview(Request $request)
    {
        $cities = City::query()
            ->mainCities()
            ->with('province:id,name')
            ->ordered()
            ->get(['id', 'province_id', 'name', 'slug']);

        // شمارشِ صفحات و منتشرشده‌ها به‌ازای هر شهر — با دو کوئریِ گروهی.
        $counts = CityPage::query()
            ->selectRaw('city_id, count(*) as total, sum(status = ?) as published', [CityPage::STATUS_PUBLISHED])
            ->groupBy('city_id')
            ->get()
            ->keyBy('city_id');

        return view('crm::city-pages.overview', compact('cities', 'counts'));
    }

    /** صفحاتِ یک شهر — گروه‌بندی‌شده بر اساس نوع، با نشانِ وضعیت. */
    public function index(Request $request, City $city)
    {
        abort_if($city->isDistrict(), 404, 'مناطق صفحهٔ سئو ندارند.');

        $status = $request->string('status')->toString();

        $pages = CityPage::query()
            ->where('city_id', $city->id)
            ->when(in_array($status, [CityPage::STATUS_DRAFT, CityPage::STATUS_PUBLISHED, CityPage::STATUS_ARCHIVED], true),
                fn ($q) => $q->where('status', $status))
            ->with(['device:id,name', 'brand:id,name'])
            ->orderByRaw("CASE type WHEN 'city' THEN 1 WHEN 'services' THEN 2 WHEN 'device' THEN 3 WHEN 'brands' THEN 4 WHEN 'brand' THEN 5 WHEN 'combo' THEN 6 ELSE 7 END")
            ->orderBy('device_id')
            ->orderBy('brand_id')
            ->get()
            ->groupBy('type');

        $summary = CityPage::query()
            ->where('city_id', $city->id)
            ->selectRaw('count(*) as total, sum(status = ?) as published, sum(status = ?) as draft', [
                CityPage::STATUS_PUBLISHED, CityPage::STATUS_DRAFT,
            ])
            ->first();

        return view('crm::city-pages.index', compact('city', 'pages', 'summary', 'status'));
    }

    /** همگام‌سازی: ساختِ صفحاتِ نبوده از پوششِ فعلی (idempotent). */
    public function sync(City $city, CityPageGenerator $generator)
    {
        abort_if($city->isDistrict(), 404);

        $result = $generator->sync($city);
        $created = $result['created'] ?? 0;

        return back()->with('success', $created > 0
            ? "همگام‌سازی انجام شد؛ {$created} صفحهٔ جدید (پیش‌نویس) اضافه شد."
            : 'همگام‌سازی انجام شد؛ صفحهٔ جدیدی برای افزودن نبود.');
    }

    /** انتشارِ همهٔ پیش‌نویس‌های این شهر با یک کلیک. */
    public function publishAll(City $city)
    {
        abort_if($city->isDistrict(), 404);

        $count = 0;
        CityPage::query()
            ->where('city_id', $city->id)
            ->where('status', CityPage::STATUS_DRAFT)
            ->get()
            ->each(function (CityPage $page) use (&$count) {
                $page->publish();
                $count++;
            });

        return back()->with('success', "{$count} صفحه منتشر شد.");
    }

    public function edit(CityPage $cityPage)
    {
        $cityPage->load(['city:id,name,slug', 'device:id,name', 'brand:id,name']);

        return view('crm::city-pages.edit', array_merge(['cityPage' => $cityPage], $this->formData($cityPage)));
    }

    public function update(Request $request, CityPage $cityPage)
    {
        $validated = $this->validateRequest($request, $cityPage);

        $faqIds = $this->extract($validated, 'faq_ids');
        $faqCategoryIds = $this->extract($validated, 'faq_category_ids');
        $reviewIds = $this->extract($validated, 'review_ids');

        $this->applyDefaults($validated);

        // ویرایشِ دستی ⇒ دیگر «خودکار» نیست (از بازنویسیِ آینده محافظت).
        $validated['auto_generated'] = false;

        $cityPage->update($validated);
        $cityPage->faqs()->sync($this->withSortOrder($faqIds));
        $cityPage->faqCategories()->sync($this->withSortOrder($faqCategoryIds));
        $cityPage->reviews()->sync($reviewIds);

        return redirect()
            ->route('crm.city-pages.edit', $cityPage->id)
            ->with('success', 'صفحه ذخیره شد.');
    }

    /** دادهٔ فرمِ ویرایش — فهرست‌های FAQ/دسته/نظرات + انتخاب‌های فعلی. */
    private function formData(CityPage $page): array
    {
        return [
            'allFaqs' => Faq::query()
                ->where('is_published', true)
                ->orderBy('sort_order')->orderByDesc('created_at')
                ->get(['id', 'question', 'answer']),
            'allFaqCategories' => Taxonomy::query()
                ->ofType(Taxonomy::TYPE_FAQ)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->withCount(['faqs' => fn ($q) => $q->where('is_published', true)])
                ->get(['id', 'slug', 'name']),
            'allReviews' => Review::query()
                ->where('status', Review::STATUS_APPROVED)
                ->orderByDesc('is_featured')->limit(500)
                ->get(['id', 'type', 'author_name', 'topic', 'rating', 'content']),
            'selectedFaqIds' => $page->faqs()->pluck('faqs.id')->all(),
            'selectedFaqCategoryIds' => $page->faqCategories()->pluck('site_taxonomies.id')->map(fn ($i) => (int) $i)->all(),
            'selectedReviewIds' => $page->reviews()->pluck('site_reviews.id')->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function validateRequest(Request $request, CityPage $page): array
    {
        return $request->validate([
            // مسیر/slugِ عمومیِ صفحه — قابلِ ویرایشِ دستیِ ادمین. باید انگلیسی،
            // با / شروع شود و یکتا باشد (مثلاً /mashhad/services/washing-machine).
            'path' => [
                'required', 'string', 'max:255',
                'regex:#^/[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*$#',
                \Illuminate\Validation\Rule::unique('crm_city_pages', 'path')->ignore($page->id),
            ],
            'title' => 'nullable|string|max:255',
            'h1' => 'nullable|string|max:255',
            'eyebrow' => 'nullable|string|max:120',
            'subtitle' => 'nullable|string|max:500',
            'caption' => 'nullable|string|max:500',
            'content' => 'nullable|string|max:200000',

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
        ], [
            'path.required' => 'مسیرِ صفحه الزامی است.',
            'path.regex' => 'مسیر باید انگلیسی و با / شروع شود (مثلاً /mashhad/services/washing-machine).',
            'path.unique' => 'این مسیر قبلاً برای صفحهٔ دیگری ثبت شده است.',
        ]);
    }

    private function applyDefaults(array &$validated): void
    {
        if (array_key_exists('content', $validated)) {
            $validated['content'] = HtmlSanitizer::clean((string) $validated['content']);
        }

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

    /** @return array<int, mixed> */
    private function extract(array &$validated, string $key): array
    {
        $vals = (array) ($validated[$key] ?? []);
        unset($validated[$key]);
        $vals = array_values(array_filter($vals, fn ($v) => $v !== null && $v !== ''));

        return array_values(array_unique($vals));
    }

    /** @return array<int|string, array{sort_order:int}> */
    private function withSortOrder(array $ids): array
    {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[$id] = ['sort_order' => $i];
        }

        return $out;
    }

    /** انتشار/بازگردانی به پیش‌نویسِ یک صفحه (تاگل). */
    public function togglePublish(CityPage $cityPage)
    {
        if ($cityPage->isPublished()) {
            $cityPage->unpublish();
            $msg = 'صفحه به پیش‌نویس بازگشت (از سایت حذف شد).';
        } else {
            $cityPage->publish();
            $msg = 'صفحه منتشر شد.';
        }

        return back()->with('success', $msg);
    }

    /** پیش‌نمایشِ امنِ ادمین — پشتِ auth پنل؛ حتی صفحاتِ پیش‌نویس. */
    public function preview(CityPage $cityPage)
    {
        $cityPage->load(['city:id,name,slug', 'device:id,name', 'brand:id,name']);

        return view('crm::city-pages.preview', compact('cityPage'));
    }

    public function destroy(CityPage $cityPage)
    {
        $cityId = $cityPage->city_id;
        $cityPage->delete();

        return redirect()
            ->route('crm.cities.pages.index', $cityId)
            ->with('success', 'صفحه حذف شد.');
    }
}
