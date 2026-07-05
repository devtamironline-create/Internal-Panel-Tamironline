<?php

namespace Modules\Site\Http\Controllers\Admin\Forum;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Site\Models\Forum\Topic;

/**
 * مدیریتِ «موضوعاتِ» انجمن (تاکسونومی) — برای فیلترِ «جستجو بر اساس موضوع».
 * ساخت/ویرایش/فعال‌خاموش/حذف + مرتب‌سازی. دسترسی: مدیریتِ سوالاتِ انجمن.
 */
class TopicController extends Controller
{
    private function checkManage(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->checkManage();
        $topics = Topic::query()->ordered()
            ->withCount('questions')
            ->get();

        return view('site::admin.forum.topics.index', compact('topics'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->checkManage();
        $data = $this->validateData($request);

        if (empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($data['label']);
        }
        Topic::create($data);

        return back()->with('success', 'موضوع اضافه شد.');
    }

    public function update(Request $request, Topic $topic): RedirectResponse
    {
        $this->checkManage();
        $data = $this->validateData($request, $topic);
        if (empty($data['slug'])) {
            unset($data['slug']); // slug خالی = دست‌نخورده
        }
        $topic->update($data);

        return back()->with('success', 'موضوع به‌روز شد.');
    }

    public function toggle(Topic $topic): RedirectResponse
    {
        $this->checkManage();
        $topic->update(['is_active' => ! $topic->is_active]);

        return back()->with('success', $topic->is_active ? 'موضوع فعال شد.' : 'موضوع غیرفعال شد.');
    }

    public function destroy(Topic $topic): RedirectResponse
    {
        $this->checkManage();
        // سوال‌های منتسب بدونِ موضوع می‌شوند (topic_id همان می‌ماند ولی رابطه null برمی‌گردد).
        $topic->questions()->update(['topic_id' => null]);
        $topic->delete();

        return back()->with('success', 'موضوع حذف شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Topic $topic = null): array
    {
        $data = $request->validate([
            'label' => 'required|string|max:120',
            'slug' => 'nullable|string|max:80|regex:/^[a-z0-9\-]+$/|unique:site_forum_topics,slug'.($topic ? ','.$topic->id : ''),
            'description' => 'nullable|string|max:300',
            'icon' => 'nullable|string|max:60',
            'tone' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'nullable|boolean',
        ], [
            'slug.regex' => 'slug فقط حروفِ انگلیسیِ کوچک، عدد و خطِ تیره.',
            'slug.unique' => 'این slug قبلاً استفاده شده است.',
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function uniqueSlug(string $label): string
    {
        $base = Str::slug($label) ?: 'topic';
        $slug = $base;
        $i = 2;
        while (Topic::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
