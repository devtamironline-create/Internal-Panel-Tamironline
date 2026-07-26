<?php

namespace Modules\Seo\Services\Arm;

use Modules\Seo\Contracts\SeoPerformanceProvider;
use Modules\Seo\Models\SeoChangeLog;
use Modules\Seo\Models\SeoProject;
use Modules\Seo\Support\Arm\ArmEnums;

/**
 * تجمیع دادهٔ «مرکز فرمان» — همهٔ اعداد از دیتابیس (از طریق provider/مدل‌ها)
 * می‌آیند؛ هیچ مقداری در کنترلر/ویو hardcode نمی‌شود.
 */
class ArmDashboardService
{
    public function __construct(
        private readonly SeoPerformanceProvider $performance,
        private readonly SeoHealthScoreService $health,
    ) {}

    /** @return array<string, mixed> */
    public function build(SeoProject $project): array
    {
        $snapshot = $this->performance->latestSnapshot($project);
        $previous = $snapshot ? $this->performance->previousSnapshot($project, $snapshot) : null;

        return [
            'project' => $project,
            'snapshot' => $snapshot,
            'previous' => $previous,
            'cards' => $this->cards($project, $snapshot, $previous),
            'health' => $this->health->compute($project, $snapshot),
            'action_center' => $this->actionCenter($project),
            'progress' => $this->roadmapProgress($project),
            'radar' => $this->radar($project),
            'priority_pages' => $this->priorityPages($project),
            'content_pipeline' => $this->contentPipeline($project),
            'issues_summary' => $this->issuesSummary($project),
            'activity' => SeoChangeLog::query()->latest('created_at')->limit(12)->get(),
        ];
    }

    /** @return list<array<string, mixed>> کارت‌های خلاصهٔ بالا */
    private function cards(SeoProject $project, $snapshot, $previous): array
    {
        $progress = $this->roadmapProgress($project);

        $metric = function (string $key, string $title, ?string $tooltip, $current, $prev, bool $higherIsBetter = true, ?string $format = null) {
            $delta = null;
            $direction = null;
            if ($current !== null && $prev !== null && (float) $prev != 0.0) {
                $delta = round(((float) $current - (float) $prev) / abs((float) $prev) * 100, 1);
                $direction = $delta == 0 ? 'flat' : (($delta > 0) === $higherIsBetter ? 'good' : 'bad');
            }

            return [
                'key' => $key, 'title' => $title, 'tooltip' => $tooltip,
                'value' => $current, 'previous' => $prev, 'delta' => $delta,
                'direction' => $direction, 'higher_is_better' => $higherIsBetter,
                'format' => $format,
                'interpretation' => $this->interpret($key, $current, $delta, $higherIsBetter),
            ];
        };

        return [
            $metric('clicks', 'کلیک ارگانیک', 'تعداد کلیک از نتایج جستجو در بازهٔ گزارش. بیشتر = بهتر.', $snapshot?->clicks, $previous?->clicks, true, 'int'),
            $metric('impressions', 'ایمپرشن', 'دفعات نمایش در نتایج جستجو. بیشتر = دیده‌شدن بیشتر.', $snapshot?->impressions, $previous?->impressions, true, 'int'),
            $metric('average_position', 'میانگین رتبه', 'میانگین جایگاه در نتایج. کمتر = بهتر.', $snapshot?->average_position, $previous?->average_position, false, 'position'),
            $metric('ctr', 'نرخ کلیک (CTR)', 'کلیک ÷ ایمپرشن. بیشتر = عنوان/توضیحات جذاب‌تر.', $snapshot?->ctr, $previous?->ctr, true, 'percent'),
            $metric('keywords_top_3', 'کلمات Top 3', 'تعداد کلمات هدف در ۳ نتیجهٔ اول.', $snapshot?->keywords_top_3, $previous?->keywords_top_3, true, 'int'),
            $metric('keywords_top_10', 'کلمات Top 10', 'تعداد کلمات هدف در صفحهٔ اول گوگل.', $snapshot?->keywords_top_10, $previous?->keywords_top_10, true, 'int'),
            $metric('organic_leads', 'لید ارگانیک', 'لیدهای منتسب به جستجوی ارگانیک (تماس + ورود اپ).', $snapshot?->organic_leads, $previous?->organic_leads, true, 'int'),
            [
                'key' => 'roadmap_progress', 'title' => 'پیشرفت برنامه ۹۰ روزه',
                'tooltip' => 'درصد کارهای موعدگذشتهٔ برنامه که انجام شده‌اند.',
                'value' => $progress['completion_percent'], 'previous' => null, 'delta' => null,
                'direction' => null, 'higher_is_better' => true, 'format' => 'percent100',
                'interpretation' => $progress['delayed'] > 0
                    ? $progress['delayed'].' کار عقب‌افتاده دارد — امروز اولویت با همین‌هاست.'
                    : 'برنامه روی ریل است.',
            ],
        ];
    }

    private function interpret(string $key, $value, ?float $delta, bool $higherIsBetter): string
    {
        if ($value === null) {
            return 'داده‌ای برای این بازه ثبت نشده است.';
        }
        if ($delta === null) {
            return 'اولین بازهٔ اندازه‌گیری — مبنای مقایسه‌های بعدی.';
        }
        $good = ($delta > 0) === $higherIsBetter;
        $abs = abs($delta);

        return match (true) {
            $delta == 0.0 => 'بدون تغییر نسبت به بازهٔ قبل.',
            $good && $abs >= 10 => 'رشد قابل توجه نسبت به بازهٔ قبل ✅',
            $good => 'روند مثبت — ادامه دهید.',
            $abs >= 10 => 'افت محسوس — نیازمند بررسی فوری است.',
            default => 'افت جزئی — زیر نظر بگیرید.',
        };
    }

    /** @return array<string, mixed> «امروز چه کنیم؟» */
    private function actionCenter(SeoProject $project): array
    {
        return [
            'overdue_tasks' => $project->roadmapItems()
                ->whereDate('planned_date', '<', now()->toDateString())
                ->whereNotIn('status', ['completed', 'skipped'])
                ->orderBy('planned_date')->limit(8)->get(),
            'today_tasks' => $project->roadmapItems()
                ->whereDate('planned_date', now()->toDateString())
                ->whereNotIn('status', ['completed', 'skipped'])
                ->orderByRaw("priority = 'p0' desc")->limit(8)->get(),
            'awaiting_review' => $project->contentItems()
                ->whereIn('status', ['expert_review', 'seo_review'])
                ->orderBy('due_at')->limit(6)->get(),
            'critical_issues' => $project->issues()
                ->where('severity', 'critical')
                ->whereNotIn('status', ['resolved', 'ignored'])
                ->limit(6)->get(),
            'refresh_pages' => $project->pages()
                ->where('workflow_status', 'refresh')
                ->orderByRaw("priority = 'p0' desc")->limit(6)->get(),
            'link_followups' => $project->linkItems()
                ->whereIn('status', ['outreach', 'negotiation'])
                ->orderBy('planned_at')->limit(6)->get(),
        ];
    }

    /** @return array<string, mixed> پیشرفت ۹۰ روزه */
    public function roadmapProgress(SeoProject $project): array
    {
        $day = $project->currentDayNumber();
        $week = $project->currentWeekNumber();
        $phase = collect(ArmEnums::PHASES)->search(
            fn ($p) => $day >= $p['range'][0] && $day <= $p['range'][1]
        ) ?: 1;

        $total = $project->roadmapItems()->count();
        $completed = $project->roadmapItems()->whereIn('status', ['completed', 'skipped'])->count();
        $dueSoFar = $project->roadmapItems()->whereDate('planned_date', '<=', now())->count();
        $doneSoFar = $project->roadmapItems()
            ->whereDate('planned_date', '<=', now())
            ->whereIn('status', ['completed', 'skipped'])->count();
        $delayed = max(0, $dueSoFar - $doneSoFar);

        return [
            'day' => $day,
            'week' => $week,
            'phase' => $phase,
            'phase_info' => ArmEnums::PHASES[$phase],
            'total' => $total,
            'completed' => $completed,
            'delayed' => $delayed,
            'completion_percent' => $dueSoFar > 0 ? (int) round($doneSoFar / $dueSoFar * 100) : 0,
            'overall_percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'milestones' => $project->roadmapItems()
                ->where('task_type', 'milestone')
                ->orderBy('day_number')
                ->get(['id', 'title', 'day_number', 'week_number', 'status', 'planned_date']),
        ];
    }

    /** @return array<string, list<\Modules\Seo\Models\SeoOpportunity>> رادار فرصت (۴ ربع) */
    private function radar(SeoProject $project): array
    {
        $open = $project->opportunities()
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('opportunity_score')
            ->limit(40)->get();

        return [
            'quick_win' => $open->filter(fn ($o) => $o->quadrant() === 'quick_win')->values()->all(),
            'strategic' => $open->filter(fn ($o) => $o->quadrant() === 'strategic')->values()->all(),
            'filler' => $open->filter(fn ($o) => $o->quadrant() === 'filler')->values()->all(),
            'avoid' => $open->filter(fn ($o) => $o->quadrant() === 'avoid')->values()->all(),
            'all' => $open,
        ];
    }

    private function priorityPages(SeoProject $project)
    {
        return $project->pages()
            ->whereIn('priority', ['p0', 'p1'])
            ->whereNotIn('workflow_status', ['archived', 'redirect', 'merge'])
            ->orderByRaw("priority = 'p0' desc")
            ->orderByDesc('impressions')
            ->limit(10)
            ->get();
    }

    /** @return array<string, int> خط تولید محتوا */
    private function contentPipeline(SeoProject $project): array
    {
        $counts = $project->contentItems()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')->pluck('c', 'status');

        $delayed = $project->contentItems()
            ->whereDate('due_at', '<', now()->toDateString())
            ->whereNotIn('status', ['published', 'monitoring'])
            ->count();

        return [
            'planned' => (int) ($counts['idea'] ?? 0) + (int) ($counts['brief'] ?? 0),
            'writing' => (int) ($counts['writing'] ?? 0),
            'review' => (int) ($counts['expert_review'] ?? 0) + (int) ($counts['seo_review'] ?? 0),
            'ready' => (int) ($counts['ready'] ?? 0) + (int) ($counts['scheduled'] ?? 0),
            'published' => (int) ($counts['published'] ?? 0) + (int) ($counts['monitoring'] ?? 0),
            'delayed' => $delayed,
        ];
    }

    /** @return array<string, int> مشکلات فنی به تفکیک شدت (باز) */
    private function issuesSummary(SeoProject $project): array
    {
        $counts = $project->issues()
            ->whereNotIn('status', ['resolved', 'ignored'])
            ->selectRaw('severity, count(*) as c')
            ->groupBy('severity')->pluck('c', 'severity');

        return [
            'critical' => (int) ($counts['critical'] ?? 0),
            'high' => (int) ($counts['high'] ?? 0),
            'medium' => (int) ($counts['medium'] ?? 0),
            'low' => (int) ($counts['low'] ?? 0),
        ];
    }
}
