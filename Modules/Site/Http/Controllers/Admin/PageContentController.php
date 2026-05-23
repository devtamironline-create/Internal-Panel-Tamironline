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

        $title = $this->sections->pages()[$slug] ?? $slug;
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
            'faq_categories' => Taxonomy::ofType(Taxonomy::TYPE_FAQ)
                ->ordered()
                ->get(['id', 'slug', 'name', 'is_active']),
            'testimonial_categories' => Taxonomy::ofType(Taxonomy::TYPE_TESTIMONIAL)
                ->ordered()
                ->get(['id', 'slug', 'name', 'is_active']),
        ];

        return view('site::admin.page-content.edit', [
            'slug' => $slug,
            'title' => $title,
            'schemaSections' => $schemaSections,
            'values' => $values,
            'references' => $references,
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $this->checkAccess();

        if (! $this->sections->pageExists($slug)) {
            abort(404);
        }

        $this->sections->saveAll($slug, (array) $request->input('sections', []));

        return redirect()
            ->route('site.admin.page-content.edit', $slug)
            ->with('success', 'محتوای صفحه ذخیره شد.');
    }
}
