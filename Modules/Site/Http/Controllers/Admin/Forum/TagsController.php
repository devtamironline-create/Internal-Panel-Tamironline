<?php

namespace Modules\Site\Http\Controllers\Admin\Forum;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Modules\Site\Models\Forum\Question;
use Modules\Site\Support\ForumActivityLogger;

/**
 * مدیریت برچسب‌های انجمن — تگ‌ها در ستون JSON `tags` روی هر Question هستند.
 *
 * این صفحه:
 *   - لیست همه‌ی tagهای موجود + تعداد سوال هر کدام (aggregate)
 *   - تغییر نام (rename) — یک تگ در همه‌ی سوالات با نام جدید جایگزین می‌شود
 *   - ادغام (merge) — دو یا چند تگ به یک تگ هدف ادغام می‌شوند
 *   - حذف (delete) — تگ از همه‌ی سوالات حذف می‌شود
 */
class TagsController extends Controller
{
    private function checkView(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('view-forum') && ! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    private function checkManage(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkView();

        $search = trim((string) $request->query('q', ''));

        // ساخت آرایه [tag => count] از همه‌ی tagهای موجود
        $counts = [];
        Question::query()
            ->select('id', 'tags')
            ->whereNotNull('tags')
            ->chunk(500, function ($rows) use (&$counts) {
                foreach ($rows as $q) {
                    foreach ((array) $q->tags as $t) {
                        $t = is_string($t) ? trim($t) : '';
                        if ($t === '') {
                            continue;
                        }
                        $counts[$t] = ($counts[$t] ?? 0) + 1;
                    }
                }
            });

        if ($search !== '') {
            $counts = array_filter($counts, fn ($_, $tag) => str_contains(mb_strtolower((string) $tag), mb_strtolower($search)), ARRAY_FILTER_USE_BOTH);
        }

        arsort($counts);

        return view('site::admin.forum.tags', [
            'tags' => $counts,
            'search' => $search,
        ]);
    }

    /**
     * تغییر نام یک تگ → جایگزینی در همه‌ی سوالات.
     */
    public function rename(Request $request): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'old' => 'required|string|max:30',
            'new' => 'required|string|max:30|different:old',
        ]);

        $old = trim($data['old']);
        $new = trim($data['new']);
        $changed = $this->replaceTags([$old], $new);

        ForumActivityLogger::log('tags:rename', meta: ['from' => $old, 'to' => $new, 'questions_updated' => $changed]);

        return back()->with('success', "«{$old}» در {$changed} سوال به «{$new}» تغییر یافت.");
    }

    /**
     * ادغام چند تگ به یک تگ هدف.
     */
    public function merge(Request $request): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'sources' => 'required|array|min:2',
            'sources.*' => 'string|max:30',
            'target' => 'required|string|max:30',
        ]);

        $sources = array_values(array_unique(array_map('trim', $data['sources'])));
        $target = trim($data['target']);

        $changed = $this->replaceTags($sources, $target);

        ForumActivityLogger::log('tags:merge', meta: ['sources' => $sources, 'target' => $target, 'questions_updated' => $changed]);

        return back()->with('success', count($sources)." تگ در {$changed} سوال به «{$target}» ادغام شدند.");
    }

    /**
     * حذف یک تگ از همه‌ی سوالات.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'tag' => 'required|string|max:30',
        ]);

        $tag = trim($data['tag']);
        $changed = $this->replaceTags([$tag], null);

        ForumActivityLogger::log('tags:delete', meta: ['tag' => $tag, 'questions_updated' => $changed]);

        return back()->with('success', "تگ «{$tag}» از {$changed} سوال حذف شد.");
    }

    /**
     * منطق مشترک: همه‌ی tagهای $sources را در DB پیدا و با $target جایگزین/حذف می‌کنیم.
     * اگر $target null باشد، حذف می‌شویم (بدون جایگزین).
     *
     * @param  array<int, string>  $sources
     */
    private function replaceTags(array $sources, ?string $target): int
    {
        if ($sources === []) {
            return 0;
        }
        $sourcesLower = array_map('mb_strtolower', $sources);

        $changed = 0;
        DB::transaction(function () use ($sources, $sourcesLower, $target, &$changed) {
            Question::query()
                ->whereNotNull('tags')
                ->where(function ($q) use ($sources) {
                    foreach ($sources as $s) {
                        $q->orWhereJsonContains('tags', $s);
                    }
                })
                ->chunkById(200, function ($rows) use ($sourcesLower, $target, &$changed) {
                    foreach ($rows as $q) {
                        $tags = array_values(array_filter((array) $q->tags, fn ($t) => is_string($t) && trim($t) !== ''));
                        $hadMatch = false;
                        $newTags = [];

                        foreach ($tags as $t) {
                            if (in_array(mb_strtolower(trim($t)), $sourcesLower, true)) {
                                $hadMatch = true;
                                if ($target !== null && ! in_array($target, $newTags, true)) {
                                    $newTags[] = $target;
                                }
                            } else {
                                $newTags[] = $t;
                            }
                        }

                        if ($hadMatch) {
                            $q->update(['tags' => $newTags ?: null]);
                            $changed++;
                        }
                    }
                });
        });

        return $changed;
    }
}
