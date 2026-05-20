<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Http\Requests\Admin\StoreFaqRequest;
use Modules\Site\Models\Faq;
use Modules\Site\Models\Taxonomy;

class FaqController extends Controller
{
    private function checkAccess(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('manage-site-faqs')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkAccess();

        $query = Faq::query()->orderBy('sort_order')->orderByDesc('created_at');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('question', 'like', "%{$q}%")
                    ->orWhere('answer', 'like', "%{$q}%");
            });
        }

        if (! is_null($request->query('published'))) {
            $query->where('is_published', $request->boolean('published'));
        }

        $items = $query->paginate(20)->withQueryString();

        return view('site::admin.faqs.index', compact('items'));
    }

    public function create(): View
    {
        $this->checkAccess();
        $taxonomies = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->ordered()->get();
        return view('site::admin.faqs.create', compact('taxonomies'));
    }

    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');
        $taxonomyIds = $this->validateTaxonomyIds($request);
        unset($data['taxonomy_ids']);

        $faq = Faq::create($data);
        $faq->taxonomies()->sync($taxonomyIds);

        return redirect()
            ->route('site.admin.faqs.index')
            ->with('success', 'سوال جدید ثبت شد.');
    }

    public function edit(string $id): View
    {
        $this->checkAccess();
        $faq = Faq::with('taxonomies:id')->findOrFail($id);
        $taxonomies = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->ordered()->get();
        $selectedTaxonomies = $faq->taxonomies->pluck('id')->all();
        return view('site::admin.faqs.edit', compact('faq', 'taxonomies', 'selectedTaxonomies'));
    }

    public function update(StoreFaqRequest $request, string $id): RedirectResponse
    {
        $faq = Faq::findOrFail($id);
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');
        $taxonomyIds = $this->validateTaxonomyIds($request);
        unset($data['taxonomy_ids']);

        $faq->update($data);
        $faq->taxonomies()->sync($taxonomyIds);

        return redirect()
            ->route('site.admin.faqs.index')
            ->with('success', 'سوال به‌روز شد.');
    }

    /**
     * @return array<int, int>
     */
    private function validateTaxonomyIds(Request $request): array
    {
        $ids = (array) $request->input('taxonomy_ids', []);
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn ($v) => $v !== null && $v !== ''))));
        if (empty($ids)) {
            return [];
        }
        return Taxonomy::ofType(Taxonomy::TYPE_FAQ)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->checkAccess();
        Faq::findOrFail($id)->delete();

        return redirect()
            ->route('site.admin.faqs.index')
            ->with('success', 'سوال حذف شد.');
    }
}
