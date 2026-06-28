<?php

namespace Modules\Site\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Site\Models\Taxonomy;

class TaxonomyController extends Controller
{
    private function checkAccess(string $type): void
    {
        $u = auth()->user();
        $perm = $type === Taxonomy::TYPE_FAQ ? 'manage-site-faqs' : 'manage-site-testimonials';
        if (! $u || (! $u->can($perm) && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, [Taxonomy::TYPE_FAQ, Taxonomy::TYPE_TESTIMONIAL], true)) {
            abort(404);
        }
    }

    public function index(string $type): View
    {
        $this->assertType($type);
        $this->checkAccess($type);

        $countRel = $type === Taxonomy::TYPE_FAQ ? 'faqs' : 'testimonials';

        $items = Taxonomy::ofType($type)->ordered()->withCount($countRel)->paginate(30);

        $stats = [
            'total' => Taxonomy::ofType($type)->count(),
            'active' => Taxonomy::ofType($type)->where('is_active', true)->count(),
            'items' => $type === Taxonomy::TYPE_FAQ
                ? \Modules\Site\Models\Faq::query()->count()
                : \Modules\Site\Models\Review::query()->count(),
        ];

        return view('site::admin.taxonomies.index', compact('items', 'type', 'stats'));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->assertType($type);
        $this->checkAccess($type);

        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'slug'        => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/',
                Rule::unique('site_taxonomies')->where('type', $type)],
            'description' => 'nullable|string|max:300',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        $data['type'] = $type;
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);

        Taxonomy::create($data);

        return back()->with('success', 'دسته‌بندی ایجاد شد.');
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $this->assertType($type);
        $this->checkAccess($type);

        $taxonomy = Taxonomy::ofType($type)->findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'slug'        => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?$/',
                Rule::unique('site_taxonomies')->where('type', $type)->ignore($id)],
            'description' => 'nullable|string|max:300',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', false);

        $taxonomy->update($data);

        return back()->with('success', 'دسته‌بندی به‌روز شد.');
    }

    /**
     * تغییرِ سریعِ وضعیتِ فعال/غیرفعال (بدونِ نیاز به ارسالِ کلِ فرم).
     */
    public function toggle(string $type, int $id): RedirectResponse
    {
        $this->assertType($type);
        $this->checkAccess($type);

        $taxonomy = Taxonomy::ofType($type)->findOrFail($id);
        $taxonomy->update(['is_active' => ! $taxonomy->is_active]);

        return back()->with('success', $taxonomy->is_active ? 'دسته‌بندی فعال شد.' : 'دسته‌بندی غیرفعال شد.');
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        $this->assertType($type);
        $this->checkAccess($type);

        Taxonomy::ofType($type)->findOrFail($id)->delete();

        return back()->with('success', 'دسته‌بندی حذف شد.');
    }
}
