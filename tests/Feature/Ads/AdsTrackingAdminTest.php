<?php

namespace Tests\Feature\Ads;

use App\Http\Controllers\Admin\AdsTrackingController;
use App\Models\AdsAttribution;
use App\Models\AdsCallClickEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * داشبوردِ Marketing → Ads Tracking — دسترسی و متریک‌ها.
 */
class AdsTrackingAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);
        Schema::table('users', function ($t) {
            foreach (['first_name', 'last_name', 'mobile'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    $t->string($column)->nullable();
                }
            }
        });
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2025_12_19_195120_create_permission_tables.php',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_08_16_210000_create_ads_tracking_tables.php',
            '--force' => true,
        ]);
    }

    private function seedEvents(): void
    {
        $attribution = AdsAttribution::create([
            'client_source' => 'website', 'gclid' => 'GCLID-1', 'keyword' => 'تعمیر یخچال',
            'first_seen_at' => now(), 'last_seen_at' => now(), 'expires_at' => now()->addDays(90),
        ]);

        // امروز: دو وب‌سایت (یکی attributed) + یک PWA بدونِ attribution.
        AdsCallClickEvent::create(['event_id' => 'e1', 'client_source' => 'website', 'gclid' => 'GCLID-1',
            'ads_attribution_id' => $attribution->id, 'attribution_id' => $attribution->attribution_id,
            'placement' => 'header', 'event_time' => now(), 'google_status' => 'pending']);
        AdsCallClickEvent::create(['event_id' => 'e2', 'client_source' => 'website',
            'placement' => 'header', 'event_time' => now(), 'google_status' => 'not_ready']);
        AdsCallClickEvent::create(['event_id' => 'e3', 'client_source' => 'pwa',
            'placement' => 'pwa_home', 'event_time' => now(), 'google_status' => 'not_ready']);
        // دیروز — نباید در متریکِ «امروز» بیاید.
        AdsCallClickEvent::create(['event_id' => 'e4', 'client_source' => 'website',
            'event_time' => now()->subDay(), 'google_status' => 'not_ready']);
    }

    /** @return array<string, mixed> */
    private function dashboard(array $query = []): array
    {
        $request = Request::create('/admin/marketing/ads-tracking', 'GET', $query);

        return app(AdsTrackingController::class)->index($request)->getData();
    }

    public function test_the_dashboard_is_forbidden_without_the_permission(): void
    {
        $user = User::forceCreate([
            'first_name' => 'کاربر', 'email' => 'no-access@example.test',
            'mobile' => '09120000001', 'mobile_verified_at' => now(), 'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user)->get('/admin/marketing/ads-tracking')->assertForbidden();
    }

    public function test_the_migration_creates_the_permission(): void
    {
        $this->assertTrue(
            \Spatie\Permission\Models\Permission::where('name', 'view-ads-tracking')->exists()
        );
    }

    public function test_today_metrics_and_source_breakdown(): void
    {
        $this->seedEvents();

        $kpis = $this->dashboard(['range' => 'today'])['kpis'];

        $this->assertSame(3, $kpis['total']);          // دیروز شمرده نشده
        $this->assertSame(1, $kpis['attributed']);
        $this->assertSame(2, $kpis['unattributed']);
        $this->assertSame(2, $kpis['website']);
        $this->assertSame(1, $kpis['pwa']);
        $this->assertSame(1, $kpis['pending_upload']);
        $this->assertSame(0, $kpis['failed_upload']);
    }

    public function test_source_and_attribution_filters_narrow_the_data(): void
    {
        $this->seedEvents();

        $this->assertSame(1, $this->dashboard(['range' => 'today', 'source' => 'pwa'])['kpis']['total']);
        $this->assertSame(1, $this->dashboard(['range' => 'today', 'attributed' => 'attributed'])['kpis']['total']);
        $this->assertSame(2, $this->dashboard(['range' => 'today', 'attributed' => 'unattributed'])['kpis']['total']);
    }

    public function test_date_filtering_includes_older_events(): void
    {
        $this->seedEvents();

        $this->assertSame(4, $this->dashboard(['range' => '7d'])['kpis']['total']);
    }

    public function test_keyword_and_placement_views_aggregate_correctly(): void
    {
        $this->seedEvents();

        $data = $this->dashboard(['range' => 'today']);

        $keyword = collect($data['topKeywords'])->firstWhere('keyword', 'تعمیر یخچال');
        $this->assertSame(1, (int) $keyword->calls);

        $placements = collect($data['placements'])->keyBy('placement');
        $this->assertSame(2, (int) $placements['header']->calls);
        $this->assertSame(1, (int) $placements['pwa_home']->calls);
    }
}
