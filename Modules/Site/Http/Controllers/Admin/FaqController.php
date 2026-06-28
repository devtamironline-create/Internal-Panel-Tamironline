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

        $query = Faq::query()->with('taxonomies:id,name,slug')
            ->orderBy('sort_order')->orderByDesc('created_at');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($qq) use ($q) {
                $qq->where('question', 'like', "%{$q}%")
                    ->orWhere('answer', 'like', "%{$q}%");
            });
        }

        if (! is_null($request->query('published'))) {
            $query->where('is_published', $request->boolean('published'));
        }

        // برای نمایشِ گروه‌بندی‌شده + مدیریتِ گروهی همه‌ی نتایجِ فیلترشده را
        // یک‌جا می‌آوریم (تعداد FAQ معمولاً محدود است).
        $faqs = $query->get();

        $categories = Taxonomy::ofType(Taxonomy::TYPE_FAQ)->ordered()->get(['id', 'name', 'slug']);

        // گروه‌بندی بر اساس دسته — یک FAQِ چنددسته‌ای زیرِ هر دسته دیده می‌شود.
        $byCategory = [];      // taxonomy_id => Faq[]
        $uncategorized = [];   // بدونِ دسته
        foreach ($faqs as $f) {
            $taxIds = $f->taxonomies->pluck('id')->all();
            if (empty($taxIds)) {
                $uncategorized[] = $f;

                continue;
            }
            foreach ($taxIds as $tid) {
                $byCategory[(int) $tid][] = $f;
            }
        }

        $allIds = $faqs->pluck('id')->all();

        return view('site::admin.faqs.index', compact(
            'faqs', 'categories', 'byCategory', 'uncategorized', 'allIds'
        ));
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

    /**
     * کپیِ یک سوال (همراهِ دسته‌بندی‌هایش) — به‌صورتِ پیش‌نویس.
     */
    public function duplicate(string $id): RedirectResponse
    {
        $this->checkAccess();
        $faq = Faq::with('taxonomies:id')->findOrFail($id);
        $this->replicateFaq($faq);

        return back()->with('success', 'کپیِ سوال ساخته شد (به‌صورتِ پیش‌نویس).');
    }

    /**
     * مدیریتِ گروهی روی چند سوال: انتشار/پیش‌نویس/حذف/کپی/افزودن‌یا‌حذفِ دسته.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $this->checkAccess();

        $validated = $request->validate([
            'action' => 'required|in:publish,unpublish,delete,duplicate,attach,detach',
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
            'taxonomy_id' => 'nullable|integer',
        ]);

        $ids = array_values(array_unique($validated['ids']));
        $faqs = Faq::with('taxonomies:id')->whereIn('id', $ids)->get();
        if ($faqs->isEmpty()) {
            return back()->with('error', 'هیچ سوالی انتخاب نشده است.');
        }
        $count = $faqs->count();

        switch ($validated['action']) {
            case 'publish':
                Faq::whereIn('id', $ids)->update(['is_published' => true]);
                $msg = "{$count} سوال منتشر شد.";
                break;

            case 'unpublish':
                Faq::whereIn('id', $ids)->update(['is_published' => false]);
                $msg = "{$count} سوال به پیش‌نویس تغییر کرد.";
                break;

            case 'delete':
                Faq::whereIn('id', $ids)->delete();
                $msg = "{$count} سوال حذف شد.";
                break;

            case 'duplicate':
                foreach ($faqs as $f) {
                    $this->replicateFaq($f);
                }
                $msg = "{$count} کپیِ پیش‌نویس ساخته شد.";
                break;

            case 'attach':
            case 'detach':
                $taxId = $this->oneValidTaxonomyId($validated['taxonomy_id'] ?? null);
                if (! $taxId) {
                    return back()->with('error', 'دسته‌بندیِ معتبری انتخاب نشده است.');
                }
                foreach ($faqs as $f) {
                    if ($validated['action'] === 'attach') {
                        $f->taxonomies()->syncWithoutDetaching([$taxId]);
                    } else {
                        $f->taxonomies()->detach($taxId);
                    }
                }
                $verb = $validated['action'] === 'attach' ? 'به دسته اضافه شد' : 'از دسته حذف شد';
                $msg = "{$count} سوال {$verb}.";
                break;

            default:
                $msg = '';
        }

        return back()->with('success', $msg);
    }

    /**
     * کپیِ یک Faq + دسته‌بندی‌هایش (پیش‌نویس، با عنوانِ «(کپی)»).
     */
    private function replicateFaq(Faq $faq): Faq
    {
        $copy = $faq->replicate(['created_at', 'updated_at']);
        $copy->question = $faq->question.' (کپی)';
        $copy->is_published = false;
        $copy->save();
        $copy->taxonomies()->sync($faq->taxonomies->pluck('id')->all());

        return $copy;
    }

    private function oneValidTaxonomyId(?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        return Taxonomy::ofType(Taxonomy::TYPE_FAQ)->whereKey($id)->value('id');
    }
}
