<?php

namespace Modules\Seo\Http\Controllers\Arm;

use Illuminate\Http\Request;
use Modules\Seo\Contracts\SeoPerformanceProvider;
use Modules\Seo\Services\Arm\ArmDashboardService;
use Modules\Seo\Services\Arm\SeoHealthScoreService;

/**
 * گزارش‌ها — از رکوردهای دیتابیس تولید می‌شوند (فاز ۱). خروجی HTML آمادهٔ
 * چاپ/ذخیره است؛ PDF عمداً ساخته نمی‌شود (زیرساخت مطمئن آن اینجا نیست).
 */
class ReportController extends BaseArmController
{
    public const TYPES = [
        'executive' => 'گزارش مدیریتی',
        'weekly' => 'گزارش هفتگی سئو',
        'content' => 'گزارش تولید محتوا',
        'keywords' => 'گزارش حرکت کلمات',
        'pages' => 'گزارش عملکرد صفحات',
        'issues' => 'گزارش مشکلات فنی',
        'links' => 'گزارش لینک‌سازی',
        'roadmap' => 'گزارش پیشرفت برنامه ۹۰ روزه',
    ];

    public function index()
    {
        return view('seo::arm.reports.index', [
            'project' => $this->project(),
            'types' => self::TYPES,
        ]);
    }

    public function show(
        Request $request,
        string $type,
        SeoPerformanceProvider $performance,
        ArmDashboardService $dashboard,
        SeoHealthScoreService $health,
    ) {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        $project = $this->project();

        // انتخاب بازهٔ گزارش — پیش‌فرض: ۳۰ روز اخیر.
        $to = $request->date('to') ?: now();
        $from = $request->date('from') ?: $to->copy()->subDays(30);

        $snapshot = $performance->latestSnapshot($project, $to->toDateString());
        $previous = $snapshot ? $performance->previousSnapshot($project, $snapshot) : null;

        $data = [
            'project' => $project,
            'type' => $type,
            'typeLabel' => self::TYPES[$type],
            'from' => $from,
            'to' => $to,
            'snapshot' => $snapshot,
            'previous' => $previous,
        ];

        $data += match ($type) {
            'executive' => [
                'health' => $health->compute($project, $snapshot),
                'progress' => $dashboard->roadmapProgress($project),
                'top_opportunities' => $project->opportunities()->whereIn('status', ['open', 'in_progress'])->orderByDesc('opportunity_score')->limit(6)->get(),
                'risks' => $project->issues()->whereIn('severity', ['critical', 'high'])->whereNotIn('status', ['resolved', 'ignored'])->limit(6)->get(),
                'done' => $project->roadmapItems()->where('status', 'completed')->whereBetween('completed_at', [$from, $to])->orderByDesc('completed_at')->limit(15)->get(),
                'delayed' => $project->roadmapItems()->whereDate('planned_date', '<', now())->whereNotIn('status', ['completed', 'skipped'])->orderBy('planned_date')->limit(15)->get(),
                'next_week' => $project->roadmapItems()->whereBetween('planned_date', [now(), now()->addWeek()])->orderBy('planned_date')->get(),
            ],
            'weekly' => [
                'series' => $performance->series($project, $from->toDateString(), $to->toDateString()),
                'completed' => $project->roadmapItems()->whereBetween('completed_at', [$from, $to])->orderBy('completed_at')->get(),
                'published' => $project->contentItems()->whereBetween('published_at', [$from, $to])->get(),
            ],
            'content' => [
                'items' => $project->contentItems()->whereBetween('planned_at', [$from, $to])->orderBy('planned_at')->get(),
            ],
            'keywords' => [
                'keywords' => $project->keywords()->with('page:id,title,path')->orderByDesc('opportunity_score')->limit(100)->get(),
            ],
            'pages' => [
                'pages' => $project->pages()->orderByDesc('impressions')->limit(100)->get(),
            ],
            'issues' => [
                'issues' => $project->issues()->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")->get(),
            ],
            'links' => [
                'links' => $project->linkItems()->orderByDesc('created_at')->limit(100)->get(),
            ],
            'roadmap' => [
                'progress' => $dashboard->roadmapProgress($project),
                'byPhase' => $project->roadmapItems()
                    ->selectRaw('phase, status, count(*) as c')
                    ->groupBy('phase', 'status')->get()
                    ->groupBy('phase'),
            ],
            default => [],
        };

        return view('seo::arm.reports.show', $data);
    }
}
