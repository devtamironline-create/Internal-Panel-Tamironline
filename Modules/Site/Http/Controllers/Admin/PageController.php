<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Http\Requests\Admin\StorePageRequest;
use Modules\Site\Models\Faq;
use Modules\Site\Models\Page;
use Modules\Site\Models\Testimonial;

class PageController extends Controller
{
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

    public function index(Request $request): View
    {
        $this->checkAccess();

        $query = Page::query()->latest('updated_at');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('title', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%");
            });
        }

        $items = $query->paginate(20)->withQueryString();

        return view('site::admin.pages.index', compact('items'));
    }

    public function create(): View
    {
        $this->checkAccess();

        $testimonials = Testimonial::query()->orderBy('sort_order')->get(['id', 'customer_name', 'topic', 'is_published']);
        $faqs         = Faq::query()->orderBy('sort_order')->get(['id', 'question', 'is_published']);

        return view('site::admin.pages.create', compact('testimonials', 'faqs'));
    }

    public function store(StorePageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');
        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $testimonialIds = $data['testimonial_ids'] ?? [];
        $faqIds         = $data['faq_ids'] ?? [];
        unset($data['testimonial_ids'], $data['faq_ids']);

        $page = Page::create($data);

        $page->testimonials()->sync($this->withOrder($testimonialIds));
        $page->faqs()->sync($this->withOrder($faqIds));

        return redirect()
            ->route('site.admin.pages.edit', $page->id)
            ->with('success', 'صفحه ایجاد شد.');
    }

    public function edit(string $id): View
    {
        $this->checkAccess();

        $page = Page::with(['testimonials:id', 'faqs:id'])->findOrFail($id);

        $testimonials = Testimonial::query()->orderBy('sort_order')->get(['id', 'customer_name', 'topic', 'is_published']);
        $faqs         = Faq::query()->orderBy('sort_order')->get(['id', 'question', 'is_published']);

        $selectedTestimonials = $page->testimonials->pluck('id')->all();
        $selectedFaqs         = $page->faqs->pluck('id')->all();

        return view('site::admin.pages.edit', compact('page', 'testimonials', 'faqs', 'selectedTestimonials', 'selectedFaqs'));
    }

    public function update(StorePageRequest $request, string $id): RedirectResponse
    {
        $page = Page::findOrFail($id);

        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');

        if ($data['is_published'] && ! $page->published_at) {
            $data['published_at'] = now();
        }
        if (! $data['is_published']) {
            $data['published_at'] = null;
        }

        $testimonialIds = $data['testimonial_ids'] ?? [];
        $faqIds         = $data['faq_ids'] ?? [];
        unset($data['testimonial_ids'], $data['faq_ids']);

        $page->update($data);

        $page->testimonials()->sync($this->withOrder($testimonialIds));
        $page->faqs()->sync($this->withOrder($faqIds));

        return redirect()
            ->route('site.admin.pages.edit', $page->id)
            ->with('success', 'صفحه به‌روز شد.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->checkAccess();
        Page::findOrFail($id)->delete();

        return redirect()
            ->route('site.admin.pages.index')
            ->with('success', 'صفحه حذف شد.');
    }

    /**
     * تبدیل آرایه‌ی idها به ساختار pivot با sort_order بر اساس ترتیب آرایه.
     *
     * @param array<int, string> $ids
     * @return array<string, array{sort_order: int}>
     */
    private function withOrder(array $ids): array
    {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[$id] = ['sort_order' => $i];
        }
        return $out;
    }
}
