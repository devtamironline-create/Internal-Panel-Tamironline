<?php

namespace Modules\Site\Http\Controllers\Admin\Forum;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Question;
use Modules\Site\Models\Forum\Report;
use Modules\Site\Support\ForumActivityLogger;

/**
 * مدیریت گزارش‌های کاربران (سوال/پاسخ).
 */
class ReportController extends Controller
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
        if (! $u || (! $u->can('moderate-forum-questions') && ! $u->can('manage-forum-questions') && ! $u->can('manage-site') && ! $u->can('manage-permissions'))) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->checkView();

        $query = Report::query()->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->where('status', Report::STATUS_PENDING);
        }
        if ($reason = $request->query('reason')) {
            $query->where('reason', $reason);
        }

        $reports = $query->paginate(30)->withQueryString();

        // Hydrate target ها (سوال یا پاسخ) دستی — چون polymorphic نیست
        $qIds = $reports->where('reportable_type', 'question')->pluck('reportable_id')->unique();
        $aIds = $reports->where('reportable_type', 'answer')->pluck('reportable_id')->unique();

        $questions = Question::whereIn('id', $qIds)->get(['id', 'slug', 'title', 'status'])->keyBy('id');
        $answers = Answer::whereIn('id', $aIds)->with('question:id,slug,title')->get()->keyBy('id');
        $cIds = $reports->where('reportable_type', 'comment')->pluck('reportable_id')->unique();
        $comments = \Modules\Site\Models\Comment::whereIn('id', $cIds)->get(['id', 'content', 'status', 'commentable_type', 'commentable_id'])->keyBy('id');

        $counts = [
            'pending' => Report::where('status', Report::STATUS_PENDING)->count(),
            'reviewed' => Report::where('status', Report::STATUS_REVIEWED)->count(),
            'dismissed' => Report::where('status', Report::STATUS_DISMISSED)->count(),
            'actioned' => Report::where('status', Report::STATUS_ACTIONED)->count(),
        ];

        return view('site::admin.forum.reports.index', compact('reports', 'questions', 'answers', 'counts', 'comments'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->checkManage();
        $data = $request->validate([
            'status' => 'required|in:pending,reviewed,dismissed,actioned',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $report = Report::findOrFail($id);
        $report->update([
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        ForumActivityLogger::log(
            "report:{$data['status']}",
            questionId: $report->reportable_type === 'question' ? $report->reportable_id : null,
            answerId: $report->reportable_type === 'answer' ? $report->reportable_id : null,
            meta: ['report_id' => $report->id, 'reason' => $report->reason],
        );

        return back()->with('success', 'گزارش به‌روز شد.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->checkManage();
        Report::findOrFail($id)->delete();

        return back()->with('success', 'گزارش حذف شد.');
    }
}
