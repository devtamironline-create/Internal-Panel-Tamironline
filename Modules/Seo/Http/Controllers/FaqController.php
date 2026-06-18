<?php

namespace Modules\Seo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Seo\Models\SeoFaq;
use Modules\Seo\Services\SeoableRegistry;

/**
 * مدیریت پرسش‌های متداول (FAQ) به‌ازای هر صفحهٔ seoable. خروجی در
 * /v1/seo/meta (کلید faq) و — در صورت فعال‌بودن تنظیم faq_schema_enabled —
 * به‌صورت FAQPage JSON-LD منتشر می‌شود (§38).
 */
class FaqController extends Controller
{
    public function __construct(private readonly SeoableRegistry $registry) {}

    /**
     * انواعِ persistedِ قابل‌انتخاب (مدلِ DB دارند و resolver پویا ندارند).
     *
     * @return array<string, array<string, mixed>>
     */
    private function persistedTypes(): array
    {
        return array_filter(
            $this->registry->all(),
            fn ($cfg) => ! empty($cfg['model']) && empty($cfg['resolver'])
        );
    }

    public function index(Request $request)
    {
        $types = $this->persistedTypes();
        $type = (string) $request->query('type', '');
        $slug = (string) $request->query('slug', '');

        $model = null;
        $faqs = collect();

        if ($type !== '' && $slug !== '' && array_key_exists($type, $types)) {
            $model = $this->registry->resolveBySlug($type, $slug);
            if ($model && $model->getKey()) {
                $faqs = SeoFaq::query()
                    ->where('faqable_type', $model->getMorphClass())
                    ->where('faqable_id', $model->getKey())
                    ->orderBy('sort_order')
                    ->get();
            }
        }

        return view('seo::faq.index', compact('types', 'type', 'slug', 'model', 'faqs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'slug' => 'required|string|max:2048',
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:5000',
        ]);

        $model = $this->resolveOrFail($validated['type'], $validated['slug']);
        if (! $model) {
            return back()->withInput()->with('error', 'صفحهٔ موردنظر یافت نشد.');
        }

        $max = (int) SeoFaq::query()
            ->where('faqable_type', $model->getMorphClass())
            ->where('faqable_id', $model->getKey())
            ->max('sort_order');

        SeoFaq::create([
            'faqable_type' => $model->getMorphClass(),
            'faqable_id' => $model->getKey(),
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'sort_order' => $max + 1,
            'is_active' => true,
        ]);

        return $this->redirectBack($validated['type'], $validated['slug'], 'پرسش متداول افزوده شد.');
    }

    public function update(Request $request, SeoFaq $faq)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:5000',
        ]);

        $faq->update($validated);

        return back()->with('success', 'پرسش متداول به‌روزرسانی شد.');
    }

    public function toggle(SeoFaq $faq)
    {
        $faq->forceFill(['is_active' => ! $faq->is_active])->save();

        return back()->with('success', $faq->is_active ? 'فعال شد.' : 'غیرفعال شد.');
    }

    public function destroy(SeoFaq $faq)
    {
        $faq->delete();

        return back()->with('success', 'پرسش متداول حذف شد.');
    }

    /**
     * مرتب‌سازی مجدد بر اساس فهرستِ id (به ترتیبِ جدید).
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        foreach (array_values($validated['ids']) as $i => $id) {
            SeoFaq::query()->whereKey($id)->update(['sort_order' => $i + 1]);
        }

        return back()->with('success', 'ترتیب ذخیره شد.');
    }

    /** رزولوِ مدلِ persisted با اعتبارسنجی type. */
    private function resolveOrFail(string $type, string $slug)
    {
        if (! array_key_exists($type, $this->persistedTypes())) {
            return null;
        }
        $model = $this->registry->resolveBySlug($type, $slug);

        return ($model && $model->getKey()) ? $model : null;
    }

    private function redirectBack(string $type, string $slug, string $message)
    {
        return redirect()
            ->route('seo.admin.faq.index', ['type' => $type, 'slug' => $slug])
            ->with('success', $message);
    }
}
