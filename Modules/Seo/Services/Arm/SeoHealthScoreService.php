<?php

namespace Modules\Seo\Services\Arm;

use Modules\Seo\Models\SeoProject;
use Modules\Seo\Models\SeoSnapshot;

/**
 * امتیاز سلامت سئو (۰ تا ۱۰۰) — امتیاز «عملیاتی داخلی» تعمیرآنلاین است،
 * نه متریک گوگل. ترکیبی از ۶ مؤلفه با وزن‌های قابل تنظیم در config.
 */
class SeoHealthScoreService
{
    public function __construct(private readonly OpportunityScoreService $opportunityScore) {}

    /**
     * @return array{score: int, components: array<string, array{score: float, weight: int, label: string}>}
     */
    public function compute(SeoProject $project, ?SeoSnapshot $snapshot): array
    {
        $w = (array) config('seo.arm.health_weights', []);
        $weights = [
            'technical' => (int) ($w['technical'] ?? 25),
            'content_progress' => (int) ($w['content_progress'] ?? 20),
            'keyword_visibility' => (int) ($w['keyword_visibility'] ?? 20),
            'ctr_health' => (int) ($w['ctr_health'] ?? 10),
            'roadmap_completion' => (int) ($w['roadmap_completion'] ?? 15),
            'commercial_performance' => (int) ($w['commercial_performance'] ?? 10),
        ];

        $components = [
            'technical' => ['score' => $this->technical($project, $snapshot), 'label' => 'سلامت فنی'],
            'content_progress' => ['score' => $this->contentProgress($project), 'label' => 'پیشرفت محتوا'],
            'keyword_visibility' => ['score' => $this->keywordVisibility($project), 'label' => 'دیده‌شدن کلمات'],
            'ctr_health' => ['score' => $this->ctrHealth($project), 'label' => 'سلامت CTR'],
            'roadmap_completion' => ['score' => $this->roadmapCompletion($project), 'label' => 'پیشرفت برنامه'],
            'commercial_performance' => ['score' => $this->commercialPerformance($project), 'label' => 'صفحات تجاری'],
        ];

        $total = 0.0;
        foreach ($components as $key => &$c) {
            $c['weight'] = $weights[$key];
            $total += ($c['score'] / 100) * $c['weight'];
        }

        return ['score' => (int) round($total), 'components' => $components];
    }

    /** سلامت فنی: از snapshot، وگرنه از نسبت مشکلات باز (بحرانی ×۳). */
    private function technical(SeoProject $project, ?SeoSnapshot $snapshot): float
    {
        if ($snapshot?->technical_health_score !== null) {
            return (float) $snapshot->technical_health_score;
        }

        $open = $project->issues()->whereNotIn('status', ['resolved', 'ignored'])->get(['severity']);
        if ($open->isEmpty()) {
            return 85.0; // بدون مشکل ثبت‌شده — تا کرال واقعی، سقف نمی‌دهیم
        }
        $penalty = 0;
        foreach ($open as $issue) {
            $penalty += match ($issue->severity) {
                'critical' => 12, 'high' => 6, 'medium' => 3, default => 1,
            };
        }

        return max(10.0, 100.0 - min(90, $penalty));
    }

    private function contentProgress(SeoProject $project): float
    {
        $due = $project->contentItems()->whereDate('due_at', '<=', now())->count();
        if ($due === 0) {
            return 70.0; // هنوز موعدی نگذشته — نمرهٔ خنثی
        }
        $done = $project->contentItems()
            ->whereDate('due_at', '<=', now())
            ->whereIn('status', ['ready', 'scheduled', 'published', 'monitoring'])
            ->count();

        return round($done / $due * 100, 1);
    }

    private function keywordVisibility(SeoProject $project): float
    {
        $keywords = $project->keywords()->where('status', 'active')->get(['current_position']);
        if ($keywords->isEmpty()) {
            return 50.0;
        }
        $score = $keywords->avg(function ($k) {
            $p = $k->current_position ?? 30;

            return max(0, 100 - (min(30, $p) - 1) * (100 / 29));
        });

        return round((float) $score, 1);
    }

    private function ctrHealth(SeoProject $project): float
    {
        $pages = $project->pages()->where('impressions', '>', 0)->get(['ctr', 'current_position']);
        if ($pages->isEmpty()) {
            return 50.0;
        }
        $ratio = $pages->avg(function ($p) {
            $expected = $this->opportunityScore->expectedCtr((int) round($p->current_position ?? 10));

            return min(1.5, ((float) $p->ctr) / max($expected, 0.001));
        });

        return round(min(100, (float) $ratio * 100 / 1.2), 1);
    }

    private function roadmapCompletion(SeoProject $project): float
    {
        $due = $project->roadmapItems()->whereDate('planned_date', '<=', now())->count();
        if ($due === 0) {
            return 70.0;
        }
        $done = $project->roadmapItems()
            ->whereDate('planned_date', '<=', now())
            ->whereIn('status', ['completed', 'skipped'])
            ->count();

        return round($done / $due * 100, 1);
    }

    /** صفحات تجاری (service/brand_service/pricing): CTR نسبت به انتظار رتبه. */
    private function commercialPerformance(SeoProject $project): float
    {
        $pages = $project->pages()
            ->whereIn('page_type', ['service', 'brand_service', 'pricing'])
            ->where('impressions', '>', 0)
            ->get(['ctr', 'current_position']);
        if ($pages->isEmpty()) {
            return 40.0; // صفحات تجاری بدون داده = ضعف شناخته‌شدهٔ فعلی
        }
        $ratio = $pages->avg(function ($p) {
            $expected = $this->opportunityScore->expectedCtr((int) round($p->current_position ?? 10));

            return min(1.5, ((float) $p->ctr) / max($expected, 0.001));
        });

        return round(min(100, (float) $ratio * 100 / 1.2), 1);
    }
}
