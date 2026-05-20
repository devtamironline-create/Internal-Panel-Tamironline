<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Http\Requests\Admin\StoreTestimonialRequest;
use Modules\Site\Models\Taxonomy;
use Modules\Site\Models\Testimonial;

class TestimonialController extends Controller
{
    private function checkAccess(): void
    {
        $u = auth()->user();
        if (! $u || (
            ! $u->can('manage-site-testimonials')
            && ! $u->can('manage-site')
            && ! $u->can('manage-permissions')
        )) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkAccess();

        $query = Testimonial::query()
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('customer_name', 'like', "%{$q}%")
                    ->orWhere('topic', 'like', "%{$q}%");
            });
        }

        if (! is_null($request->query('published'))) {
            $query->where('is_published', $request->boolean('published'));
        }

        $items = $query->paginate(20)->withQueryString();

        return view('site::admin.testimonials.index', compact('items'));
    }

    public function create(): View
    {
        $this->checkAccess();
        $taxonomies = Taxonomy::ofType(Taxonomy::TYPE_TESTIMONIAL)->ordered()->get();
        return view('site::admin.testimonials.create', compact('taxonomies'));
    }

    public function store(StoreTestimonialRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');

        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $taxonomyIds = $this->validateTaxonomyIds($request);
        unset($data['taxonomy_ids']);

        $t = Testimonial::create($data);
        $t->taxonomies()->sync($taxonomyIds);

        return redirect()
            ->route('site.admin.testimonials.index')
            ->with('success', 'نظر جدید ثبت شد.');
    }

    public function edit(string $id): View
    {
        $this->checkAccess();

        $testimonial = Testimonial::with('taxonomies:id')->findOrFail($id);
        $taxonomies = Taxonomy::ofType(Taxonomy::TYPE_TESTIMONIAL)->ordered()->get();
        $selectedTaxonomies = $testimonial->taxonomies->pluck('id')->all();

        return view('site::admin.testimonials.edit', compact('testimonial', 'taxonomies', 'selectedTaxonomies'));
    }

    public function update(StoreTestimonialRequest $request, string $id): RedirectResponse
    {
        $testimonial = Testimonial::findOrFail($id);

        $data = $request->validated();
        $data['is_published'] = $request->boolean('is_published');

        if ($data['is_published'] && ! $testimonial->published_at) {
            $data['published_at'] = now();
        }
        if (! $data['is_published']) {
            $data['published_at'] = null;
        }

        $taxonomyIds = $this->validateTaxonomyIds($request);
        unset($data['taxonomy_ids']);

        $testimonial->update($data);
        $testimonial->taxonomies()->sync($taxonomyIds);

        return redirect()
            ->route('site.admin.testimonials.index')
            ->with('success', 'نظر به‌روز شد.');
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
        return Taxonomy::ofType(Taxonomy::TYPE_TESTIMONIAL)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();
    }

    public function togglePublish(string $id): RedirectResponse
    {
        $this->checkAccess();

        $testimonial = Testimonial::findOrFail($id);
        $testimonial->is_published = ! $testimonial->is_published;
        $testimonial->published_at = $testimonial->is_published ? ($testimonial->published_at ?? now()) : null;
        $testimonial->save();

        return back()->with('success', $testimonial->is_published ? 'منتشر شد.' : 'از انتشار خارج شد.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->checkAccess();

        Testimonial::findOrFail($id)->delete();

        return redirect()
            ->route('site.admin.testimonials.index')
            ->with('success', 'نظر حذف شد.');
    }
}
