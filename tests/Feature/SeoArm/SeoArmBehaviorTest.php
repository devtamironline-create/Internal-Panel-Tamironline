<?php

namespace Tests\Feature\SeoArm;

use Illuminate\Validation\ValidationException;
use Modules\Seo\Models\SeoKeyword;
use Modules\Seo\Models\SeoPage;
use Modules\Seo\Models\SeoProject;
use Modules\Seo\Models\SeoRoadmapItem;
use Modules\Seo\Services\Arm\OpportunityScoreService;

/** رفتار دامنه: داشبورد دیتابیس‌محور، فیلترها، گذار وضعیت، هم‌نوع‌خواری، امتیاز فرصت، بازهٔ گزارش. */
class SeoArmBehaviorTest extends ArmTestCase
{
    public function test_dashboard_cards_read_values_from_database(): void
    {
        $this->seedStageOne();
        $snapshot = SeoProject::current()->snapshots()->first();

        $response = $this->actingAs($this->adminUser())->get('/admin/seo/arm')->assertOk();

        $cards = collect($response->viewData('cards'))->keyBy('key');
        $this->assertSame((int) $snapshot->clicks, (int) $cards['clicks']['value']);
        $this->assertSame((int) $snapshot->impressions, (int) $cards['impressions']['value']);
        $this->assertGreaterThan(0, $cards['clicks']['value'], 'مقدار کارت باید از DB بیاید نه صفرِ hardcode.');
    }

    public function test_roadmap_status_filter_works(): void
    {
        $this->seedStageOne();
        SeoRoadmapItem::query()->limit(3)->get()->each(fn ($i) => $i->update(['status' => 'ready']));

        $response = $this->actingAs($this->adminUser())
            ->get('/admin/seo/arm/roadmap?status=ready')
            ->assertOk();

        $items = $response->viewData('items');
        $this->assertSame(3, $items->total());
        $this->assertTrue(collect($items->items())->every(fn ($i) => $i->status === 'ready'));
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->seedStageOne();
        $item = SeoRoadmapItem::query()->where('status', 'planned')->firstOrFail();

        // planned → completed پرش نامعتبر است (باید از in_progress عبور کند).
        try {
            $item->transitionStatusTo('completed');
            $this->fail('گذار نامعتبر باید ValidationException بدهد.');
        } catch (ValidationException) {
            $this->assertSame('planned', $item->fresh()->status);
        }

        // از مسیر HTTP هم رد شود و وضعیت تغییری نکند.
        $this->actingAs($this->adminUser())
            ->from('/admin/seo/arm/roadmap')
            ->put('/admin/seo/arm/roadmap/'.$item->id.'/status', ['status' => 'completed'])
            ->assertRedirect('/admin/seo/arm/roadmap')
            ->assertSessionHasErrors();
        $this->assertSame('planned', $item->fresh()->status);

        // گذار معتبر انجام شود.
        $this->actingAs($this->adminUser())
            ->put('/admin/seo/arm/roadmap/'.$item->id.'/status', ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $item->fresh()->status);
    }

    public function test_duplicate_active_primary_keyword_is_rejected(): void
    {
        $this->seedStageOne();
        $project = SeoProject::current();

        // «تعمیر لباسشویی سامسونگ» primary صفحهٔ سامسونگ است؛ صفحهٔ دیگری نتواند بگیرد.
        $otherPage = SeoPage::query()->where('title', 'صفحه قیمت‌ها')->firstOrFail();

        $this->actingAs($this->adminUser())
            ->from('/admin/seo/arm/pages')
            ->put('/admin/seo/arm/pages/'.$otherPage->id, [
                'priority' => $otherPage->priority,
                'workflow_status' => $otherPage->workflow_status,
                'primary_keyword' => 'تعمیر لباسشویی سامسونگ',
            ])
            ->assertSessionHasErrors('primary_keyword');

        $this->assertNotSame('تعمیر لباسشویی سامسونگ', $otherPage->fresh()->primary_keyword);

        // هلپر تشخیص هم‌نوع‌خواری هم مستقیماً درست کار کند (با نرمال‌سازی ي/ی).
        $conflict = SeoKeyword::conflictingPrimary($project->id, SeoKeyword::normalize('تعمير لباسشویی سامسونگ'));
        $this->assertNotNull($conflict);
    }

    public function test_keyword_page_relationships_work(): void
    {
        $this->seedStageOne();

        $keyword = SeoKeyword::query()->where('keyword', 'تعمیر لباسشویی سامسونگ')->firstOrFail();
        $this->assertNotNull($keyword->page);
        $this->assertSame('صفحه خدمات تعمیر لباسشویی سامسونگ', $keyword->page->title);
        $this->assertTrue($keyword->page->keywords->contains('id', $keyword->id));
    }

    public function test_opportunity_score_service_behaves_sanely(): void
    {
        $service = app(OpportunityScoreService::class);

        $low = $service->score(1000, 8.0, 3.0, 0.01, 3);
        $high = $service->score(200000, 8.0, 3.0, 0.01, 5);
        $this->assertGreaterThan($low, $high, 'ایمپرشن/ارزش بیشتر → امتیاز بیشتر');

        $nearTop = $service->score(50000, 3.2, 3.0, 0.09, 3);
        $farAway = $service->score(50000, 15.0, 3.0, 0.001, 3);
        $this->assertGreaterThan($nearTop, $farAway, 'فاصلهٔ رتبه و شکاف CTR بیشتر → پتانسیل بیشتر');

        // وزن‌ها از config قابل تنظیم‌اند.
        config(['seo.arm.opportunity_weights.scale' => 200]);
        $doubled = $service->score(200000, 8.0, 3.0, 0.01, 5);
        $this->assertEqualsWithDelta($high * 2, $doubled, 0.05);
    }

    public function test_reports_respect_selected_period(): void
    {
        $this->seedStageOne();
        $project = SeoProject::current();
        $base = $project->snapshots()->first();

        // snapshot دومِ جدیدتر خارج از بازهٔ انتخابی
        $project->snapshots()->create([
            'snapshot_date' => now()->addDays(40)->toDateString(),
            'period_start' => now()->addDays(10)->toDateString(),
            'period_end' => now()->addDays(40)->toDateString(),
            'clicks' => 999999, 'impressions' => 1, 'ctr' => 1,
            'average_position' => 1, 'source' => 'seed',
        ]);

        $response = $this->actingAs($this->adminUser())
            ->get('/admin/seo/arm/reports/weekly?from='.now()->subDays(40)->toDateString().'&to='.now()->toDateString())
            ->assertOk();

        // با to=امروز، snapshot آینده نباید انتخاب شود.
        $this->assertSame((int) $base->clicks, (int) $response->viewData('snapshot')->clicks);
    }
}
