<?php

namespace Modules\Site\Http\Controllers\Admin\Forum;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\CRM\Models\Brand;
use Modules\CRM\Models\Device;
use Modules\CRM\Support\HtmlSanitizer;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Expert;
use Modules\Site\Models\Forum\Question;

/**
 * مدیریت سوالات انجمن — moderation فوق‌حرفه‌ای با bulk، تغییر وضعیت،
 * تنظیم flagهای hot/featured، تغییر resolution، حذف، و reply به‌عنوان
 * کارشناس (auto-approved + is_expert_reply).
 */
class QuestionController extends Controller
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

    /**
     * Moderation actions: تأیید/رد/spam/hot/featured.
     * مجاز با moderate-forum-questions یا manage-forum-questions.
     */
    private function checkModerate(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('moderate-forum-questions') && ! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    /**
     * Destructive: حذف سوال.
     * مجاز با delete-forum-questions یا manage-forum-questions.
     */
    private function checkDelete(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('delete-forum-questions') && ! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    /**
     * Answer management: پاسخ ادمین / accept / expert reply.
     * مجاز با manage-forum-answers یا manage-forum-questions.
     */
    private function checkAnswers(): void
    {
        $u = auth()->user();
        if (! $u || (! $u->can('manage-forum-answers') && ! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkView();

        $query = Question::query()->with(['device:id,name,slug', 'brand:id,name,slug'])
            ->withCount('answers')->latest('created_at');

        if ($status = $request->query('status')) {
            if (in_array($status, ['pending', 'approved', 'rejected', 'spam'], true)) {
                $query->where('status', $status);
            }
        }
        if ($res = $request->query('resolution')) {
            $query->where('resolution_status', $res);
        }
        if ($deviceId = $request->query('device_id')) {
            $query->where('device_id', (int) $deviceId);
        }
        if ($brandId = $request->query('brand_id')) {
            $query->where('brand_id', (int) $brandId);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($qq) => $qq->where('title', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%")
                ->orWhere('author_name', 'like', "%{$q}%"));
        }
        if ($request->boolean('hot_only')) {
            $query->where('is_hot', true);
        }
        if ($request->boolean('featured_only')) {
            $query->where('is_featured', true);
        }

        $questions = $query->paginate(20)->withQueryString();
        $devices = Device::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $brands = Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $counts = [
            'all' => Question::count(),
            'pending' => Question::where('status', 'pending')->count(),
            'approved' => Question::where('status', 'approved')->count(),
            'rejected' => Question::where('status', 'rejected')->count(),
            'spam' => Question::where('status', 'spam')->count(),
            'unanswered' => Question::where('resolution_status', 'unanswered')->count(),
            'hot' => Question::where('is_hot', true)->count(),
            'featured' => Question::where('is_featured', true)->count(),
        ];

        return view('site::admin.forum.questions.index', compact('questions', 'devices', 'brands', 'counts'));
    }

    public function show(int $id): View
    {
        $this->checkView();
        $question = Question::with(['device:id,name,slug', 'brand:id,name,slug', 'answers.expert'])->findOrFail($id);
        $experts = Expert::active()->orderBy('name')->get(['id', 'name', 'title']);

        return view('site::admin.forum.questions.show', compact('question', 'experts'));
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $this->checkModerate();
        $data = $request->validate(['status' => 'required|in:pending,approved,rejected,spam']);

        $question = Question::findOrFail($id);
        $oldStatus = $question->status;
        $question->update([
            'status' => $data['status'],
            'approved_at' => $data['status'] === Question::STATUS_APPROVED ? now() : null,
            'approved_by_user_id' => $data['status'] === Question::STATUS_APPROVED ? auth()->id() : null,
            'published_at' => $data['status'] === Question::STATUS_APPROVED ? ($question->published_at ?? now()) : null,
        ]);

        \Modules\Site\Support\ForumActivityLogger::log(
            "status:{$data['status']}",
            questionId: $question->id,
            meta: ['from' => $oldStatus, 'to' => $data['status']],
        );

        return back()->with('success', 'وضعیت سوال به‌روز شد.');
    }

    public function toggleFlag(int $id, string $flag): RedirectResponse
    {
        $this->checkModerate();
        if (! in_array($flag, ['is_hot', 'is_featured'], true)) {
            abort(404);
        }
        $q = Question::findOrFail($id);
        $newValue = ! $q->{$flag};
        $q->update([$flag => $newValue]);

        \Modules\Site\Support\ForumActivityLogger::log(
            "flag:{$flag}",
            questionId: $q->id,
            meta: ['value' => $newValue],
        );

        return back()->with('success', 'به‌روز شد.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'title' => 'required|string|min:5|max:250',
            'body' => 'required|string|min:10|max:100000',
            'model' => 'nullable|string|max:80',
            'device_id' => 'nullable|integer|exists:crm_devices,id',
            'brand_id' => 'nullable|integer|exists:crm_brands,id',
            'tags' => 'nullable|string',
        ]);

        $q = Question::findOrFail($id);
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? '')))));
        $q->update([
            'title' => $data['title'],
            'body' => HtmlSanitizer::clean($data['body']) ?? $data['body'],
            'model' => $data['model'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'brand_id' => $data['brand_id'] ?? null,
            'tags' => $tags ?: null,
        ]);

        \Modules\Site\Support\ForumActivityLogger::log('edit', questionId: $q->id);

        return back()->with('success', 'سوال ویرایش شد.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        // ابتدا فقط چک باز (view-forum) — gate سختگیرانه بسته به action انتخاب می‌شود
        $this->checkView();

        $data = $request->validate([
            'action' => 'required|in:approve,reject,spam,delete,mark_hot,unmark_hot,mark_featured,unmark_featured',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:site_forum_questions,id',
        ]);

        // per-action permission check
        match ($data['action']) {
            'delete' => $this->checkDelete(),
            'approve', 'reject', 'spam', 'mark_hot', 'unmark_hot', 'mark_featured', 'unmark_featured' => $this->checkModerate(),
        };

        $count = 0;
        $base = Question::whereIn('id', $data['ids']);

        switch ($data['action']) {
            case 'approve':
                $count = (clone $base)->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by_user_id' => auth()->id(),
                    'published_at' => now(),
                ]);
                break;
            case 'reject':
                $count = (clone $base)->update(['status' => 'rejected', 'approved_at' => null, 'published_at' => null]);
                break;
            case 'spam':
                $count = (clone $base)->update(['status' => 'spam', 'approved_at' => null, 'published_at' => null]);
                break;
            case 'delete':
                $count = (clone $base)->delete();
                break;
            case 'mark_hot':
                $count = (clone $base)->update(['is_hot' => true]);
                break;
            case 'unmark_hot':
                $count = (clone $base)->update(['is_hot' => false]);
                break;
            case 'mark_featured':
                $count = (clone $base)->update(['is_featured' => true]);
                break;
            case 'unmark_featured':
                $count = (clone $base)->update(['is_featured' => false]);
                break;
        }

        \Modules\Site\Support\ForumActivityLogger::log(
            "bulk:{$data['action']}",
            meta: ['ids' => $data['ids'], 'count' => $count],
        );

        return back()->with('success', "اقدام «{$data['action']}» روی {$count} سوال اعمال شد.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->checkDelete();
        $q = Question::findOrFail($id);
        \Modules\Site\Support\ForumActivityLogger::log(
            'delete',
            questionId: $q->id,
            meta: ['title' => $q->title, 'slug' => $q->slug],
        );
        $q->delete();

        return redirect()->route('site.admin.forum.questions.index')->with('success', 'سوال حذف شد.');
    }

    // ─── Answers nested actions ───────────────────────────────

    public function answerUpdateStatus(Request $request, int $id, int $answerId): RedirectResponse
    {
        $this->checkAnswers();
        $data = $request->validate(['status' => 'required|in:pending,approved,rejected,spam']);
        $answer = Answer::where('question_id', $id)->findOrFail($answerId);
        $oldStatus = $answer->status;

        $answer->update([
            'status' => $data['status'],
            'approved_at' => $data['status'] === Answer::STATUS_APPROVED ? now() : null,
            'approved_by_user_id' => $data['status'] === Answer::STATUS_APPROVED ? auth()->id() : null,
        ]);

        $answer->question->recomputeResolution();

        \Modules\Site\Support\ForumActivityLogger::log(
            "answer-status:{$data['status']}",
            questionId: $id,
            answerId: $answerId,
            meta: ['from' => $oldStatus, 'to' => $data['status']],
        );

        return back()->with('success', 'وضعیت پاسخ به‌روز شد.');
    }

    public function answerDestroy(int $id, int $answerId): RedirectResponse
    {
        $this->checkAnswers();
        $answer = Answer::where('question_id', $id)->findOrFail($answerId);
        $question = $answer->question;
        \Modules\Site\Support\ForumActivityLogger::log(
            'answer-delete',
            questionId: $id,
            answerId: $answerId,
            meta: ['author' => $answer->author_name],
        );
        $answer->delete();
        $question?->recomputeResolution();

        return back()->with('success', 'پاسخ حذف شد.');
    }

    /**
     * ادمین پاسخ کارشناسی ثبت می‌کند — auto-approved + is_expert_reply=true.
     */
    public function adminReply(Request $request, int $id): RedirectResponse
    {
        $this->checkAnswers();
        $data = $request->validate([
            'body' => 'required|string|min:10|max:50000',
            'expert_id' => 'nullable|integer|exists:site_forum_experts,id',
        ]);

        $question = Question::findOrFail($id);
        $expert = ! empty($data['expert_id']) ? Expert::find($data['expert_id']) : null;
        $user = auth()->user();

        $answer = Answer::create([
            'question_id' => $question->id,
            'body' => HtmlSanitizer::clean($data['body']) ?? $data['body'],
            'author_name' => $expert?->name ?? ($user?->name ?? 'تیم تعمیرآنلاین'),
            'expert_id' => $expert?->id,
            'is_expert_reply' => true,
            'status' => Answer::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by_user_id' => $user?->id,
        ]);

        if ($expert) {
            $expert->increment('answers_count');
        }
        $question->recomputeResolution();

        \Modules\Site\Support\ForumActivityLogger::log(
            'admin-reply',
            questionId: $question->id,
            answerId: $answer->id,
            meta: ['expert_id' => $expert?->id, 'expert_name' => $expert?->name],
        );

        // اطلاع‌رسانی ایمیلی به نویسنده (در صورت داشتن ایمیل و فعال‌بودن setting)
        \Modules\Site\Support\ForumNotifier::notifyAuthorOfAnswer($question, $answer);

        return back()->with('success', 'پاسخ کارشناسی ثبت و منتشر شد.');
    }
}
