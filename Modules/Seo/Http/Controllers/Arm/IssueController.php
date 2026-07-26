<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Illuminate\Http\Request;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoIssue;
use Modules\Seo\Support\Arm\ArmEnums;

/** مشکلات فنی — گروه‌بندی بر اساس شدت + گردش رفع. */
class IssueController extends BaseArmController
{
    public function index(Request $request)
    {
        $project = $this->project();

        $filters = [
            'category' => $request->string('category')->toString(),
            'severity' => $request->string('severity')->toString(),
            'status' => $request->string('status')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $issues = $project->issues()
            ->with(['page:id,title,path', 'assignee:id,name'])
            ->when($filters['category'], fn ($q, $v) => $q->where('category', $v))
            ->when($filters['severity'], fn ($q, $v) => $q->where('severity', $v))
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('seo::arm.issues.index', compact('project', 'issues', 'filters'));
    }

    public function update(Request $request, SeoIssue $issue)
    {
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(ArmEnums::ISSUE_STATUSES)),
            'assigned_to' => 'nullable|exists:users,id',
            'recommended_fix' => 'nullable|string|max:3000',
        ]);

        if ($validated['status'] === 'resolved' && $issue->status !== 'resolved') {
            $issue->resolved_at = now();
        }
        $issue->fill($validated)->save();

        SeoChangeLog::record('issue-status', 'بازوی سئو', "«{$issue->title}» → ".$issue->statusLabel());

        return back()->with('success', 'مشکل فنی به‌روزرسانی شد.');
    }
}
