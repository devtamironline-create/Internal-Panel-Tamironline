<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Illuminate\Http\Request;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoRoadmapItem;
use Modules\Seo\Services\Arm\ArmDashboardService;
use Modules\Seo\Support\Arm\ArmEnums;

/** برنامهٔ ۹۰ روزه — نمایش فاز/هفته/روز + تغییر وضعیت با اعتبارسنجی گذار. */
class RoadmapController extends BaseArmController
{
    public function index(Request $request, ArmDashboardService $dashboard)
    {
        $project = $this->project();

        $filters = [
            'status' => $request->string('status')->toString(),
            'phase' => $request->string('phase')->toString(),
            'week' => $request->string('week')->toString(),
            'task_type' => $request->string('task_type')->toString(),
            'priority' => $request->string('priority')->toString(),
            'q' => trim($request->string('q')->toString()),
        ];

        $items = $project->roadmapItems()
            ->with(['owner:id,first_name,last_name', 'relatedPage:id,title,path'])
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['phase'], fn ($q, $v) => $q->where('phase', (int) $v))
            ->when($filters['week'], fn ($q, $v) => $q->where('week_number', (int) $v))
            ->when($filters['task_type'], fn ($q, $v) => $q->where('task_type', $v))
            ->when($filters['priority'], fn ($q, $v) => $q->where('priority', $v))
            ->when($filters['q'], fn ($q, $v) => $q->where('title', 'like', "%{$v}%"))
            ->orderBy('day_number')
            ->paginate(30)
            ->withQueryString();

        return view('seo::arm.roadmap.index', [
            'project' => $project,
            'items' => $items,
            'filters' => $filters,
            'progress' => $dashboard->roadmapProgress($project),
        ]);
    }

    public function updateStatus(Request $request, SeoRoadmapItem $item)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:'.implode(',', array_keys(ArmEnums::ROADMAP_STATUSES)),
        ]);

        $item->transitionStatusTo($validated['status']);
        if ($validated['status'] === 'in_progress' && ! $item->started_at) {
            $item->started_at = now();
        }
        if ($validated['status'] === 'completed') {
            $item->completed_at = now();
        }
        $item->save();

        SeoChangeLog::record('roadmap-status', 'بازوی سئو', "«{$item->title}» → ".$item->statusLabel());

        return back()->with('success', 'وضعیت کار به‌روزرسانی شد.');
    }
}
