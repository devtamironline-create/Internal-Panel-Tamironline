<?php

namespace Tests\Feature\SeoArm;

use Modules\Seo\Models\SeoContentItem;
use Modules\Seo\Models\SeoProject;
use Modules\Seo\Models\SeoRoadmapItem;

/** صحت و idempotency دادهٔ فاز ۱. */
class SeoArmSeederTest extends ArmTestCase
{
    public function test_seeder_is_idempotent(): void
    {
        $this->seedStageOne();
        $counts = $this->tableCounts();

        $this->seedStageOne();

        $this->assertSame($counts, $this->tableCounts(), 'اجرای دوم seeder نباید رکورد تکراری بسازد.');
    }

    public function test_seeder_preserves_manual_edits(): void
    {
        $this->seedStageOne();

        $item = SeoRoadmapItem::query()->firstOrFail();
        $item->update(['status' => 'in_progress']);

        $this->seedStageOne();

        $this->assertSame('in_progress', $item->fresh()->status);
    }

    public function test_roadmap_spans_90_days_with_phases(): void
    {
        $this->seedStageOne();
        $project = SeoProject::current();

        $days = $project->roadmapItems()->pluck('day_number');
        $this->assertSame(1, $days->min());
        $this->assertSame(90, $days->max());
        $this->assertSame([1, 2, 3, 4, 5, 6], $project->roadmapItems()->distinct()->orderBy('phase')->pluck('phase')->all());
        $this->assertGreaterThanOrEqual(100, $project->roadmapItems()->count());
    }

    public function test_week_one_includes_content_production(): void
    {
        $this->seedStageOne();
        $project = SeoProject::current();

        $weekOne = SeoContentItem::query()
            ->where('seo_project_id', $project->id)
            ->whereBetween('planned_at', [$project->start_date, $project->start_date->copy()->addDays(6)])
            ->get();

        $this->assertGreaterThanOrEqual(4, $weekOne->count(), 'هفتهٔ اول باید تولید محتوا داشته باشد.');
        $this->assertTrue($weekOne->contains(fn ($i) => $i->content_type === 'article' && $i->status === 'brief'), 'محتوای جدید با وضعیت بریف');
        $this->assertSame(2, $weekOne->where('content_type', 'refresh')->count(), 'دو به‌روزرسانی در هفتهٔ اول');
        $this->assertTrue($weekOne->contains(fn ($i) => $i->content_type === 'service_page'), 'بهینه‌سازی صفحهٔ خدمات');
    }

    public function test_no_external_http_calls_during_stage_one(): void
    {
        \Illuminate\Support\Facades\Http::preventStrayRequests();

        $this->seedStageOne();
        $this->actingAs($this->adminUser())->get('/admin/seo/arm')->assertOk();

        // اگر فراخوانی خارجی رخ می‌داد، preventStrayRequests استثنا می‌داد.
        $this->assertTrue(true);
    }

    /** @return array<string, int> */
    private function tableCounts(): array
    {
        $out = [];
        foreach ([
            'seo_projects', 'seo_snapshots', 'seo_pages', 'seo_keywords',
            'seo_roadmap_items', 'seo_content_items', 'seo_opportunities',
            'seo_issues', 'seo_link_items',
        ] as $table) {
            $out[$table] = (int) \DB::table($table)->count();
        }

        return $out;
    }
}
