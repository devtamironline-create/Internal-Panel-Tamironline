<?php

namespace Tests\Feature\CRM;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Services\CustomerClubAnalytics;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * باشگاه مشتریان — صحتِ تجمیعِ اکتساب/تبدیل/تقاضا/درآمد/نگه‌داشت و گیتِ دسترسی.
 * جدول‌ها حداقلی ساخته می‌شوند (migrationهای CRM روی sqlite اجرا نمی‌شوند).
 */
class CustomerClubAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2025_12_19_195120_create_permission_tables.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        Schema::create('crm_customers', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('mobile', 20)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('name')->nullable()]));
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('name')->nullable()]));
        Schema::create('crm_cities', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('name')->nullable()]));
        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->unsignedBigInteger('city_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->string('source', 30)->nullable();
            $t->unsignedBigInteger('final_price')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function customer(string $name, string $mobile, ?string $createdAt = null): int
    {
        return DB::table('crm_customers')->insertGetId([
            'first_name' => $name, 'mobile' => $mobile,
            'created_at' => $createdAt ?? now(), 'updated_at' => now(),
        ]);
    }

    private function order(int $customerId, array $attrs = []): void
    {
        DB::table('crm_orders')->insert(array_merge([
            'customer_id' => $customerId, 'status' => OrderStatus::Completed->value,
            'source' => 'app', 'final_price' => 1_000_000,
            'created_at' => now(), 'updated_at' => now(),
        ], $attrs));
    }

    public function test_conversion_counts_customers_with_and_without_orders(): void
    {
        $a = $this->customer('الف', '09120000001');
        $b = $this->customer('ب', '09120000002');
        $this->customer('ج', '09120000003'); // بدون سفارش

        $this->order($a);
        $this->order($b);

        $conv = app(CustomerClubAnalytics::class)->build()['conversion'];

        $this->assertSame(3, $conv['total']);
        $this->assertSame(2, $conv['with_orders']);
        $this->assertSame(1, $conv['without_orders']);
        $this->assertEqualsWithDelta(66.7, $conv['rate'], 0.1);
    }

    public function test_demand_top_devices_ranks_by_order_count(): void
    {
        $washer = DB::table('crm_devices')->insertGetId(['name' => 'لباسشویی']);
        $fridge = DB::table('crm_devices')->insertGetId(['name' => 'یخچال']);
        $c = $this->customer('الف', '09120000001');

        $this->order($c, ['device_id' => $washer]);
        $this->order($c, ['device_id' => $washer]);
        $this->order($c, ['device_id' => $fridge]);

        $top = app(CustomerClubAnalytics::class)->build()['demand']['top_devices'];

        $this->assertSame('لباسشویی', $top[0]['name']);
        $this->assertSame(2, $top[0]['count']);
    }

    public function test_revenue_sums_completed_orders_only(): void
    {
        $c = $this->customer('الف', '09120000001');
        $this->order($c, ['status' => OrderStatus::Completed->value, 'final_price' => 2_000_000]);
        $this->order($c, ['status' => OrderStatus::Completed->value, 'final_price' => 1_000_000]);
        $this->order($c, ['status' => OrderStatus::Cancelled->value, 'final_price' => 9_000_000]);

        $rev = app(CustomerClubAnalytics::class)->build()['revenue'];

        $this->assertSame(3_000_000, $rev['total_revenue'], 'کنسل نباید در درآمد بیاید.');
        $this->assertSame(2, $rev['completed_orders']);
        $this->assertSame(1_500_000, $rev['aov']);
    }

    public function test_retention_repeat_vs_one_time_and_distribution(): void
    {
        $repeat = $this->customer('تکراری', '09120000001');
        $once = $this->customer('یک‌باره', '09120000002');

        $this->order($repeat);
        $this->order($repeat);
        $this->order($once);

        $ret = app(CustomerClubAnalytics::class)->build()['retention'];

        $this->assertSame(2, $ret['with_orders']);
        $this->assertSame(1, $ret['repeat']);
        $this->assertSame(1, $ret['one_time']);
        $this->assertSame(1, $ret['distribution']['2']);
        $this->assertSame(1, $ret['distribution']['1']);
    }

    public function test_segment_counts_and_no_order_query(): void
    {
        $a = $this->customer('الف', '09120000001');
        $this->customer('بدون‌سفارش', '09120000002');
        $this->order($a);

        $service = app(CustomerClubAnalytics::class);
        $segments = $service->build()['segments'];

        $this->assertSame(1, $segments['no_order']);
        $this->assertSame(1, $service->noOrderQuery()->count());
    }

    public function test_at_risk_segment_picks_stale_customers(): void
    {
        $stale = $this->customer('کهنه', '09120000001');
        $fresh = $this->customer('تازه', '09120000002');

        $this->order($stale, ['created_at' => now()->subDays(200), 'completed_at' => now()->subDays(200)]);
        $this->order($fresh, ['created_at' => now()->subDays(5)]);

        $atRisk = app(CustomerClubAnalytics::class)->atRiskQuery()->get();

        $this->assertCount(1, $atRisk);
        $this->assertSame('کهنه', $atRisk->first()->first_name);
    }

    public function test_route_requires_permission(): void
    {
        Permission::firstOrCreate(['name' => 'view-customer-club', 'guard_name' => 'web']);

        // مهمان → ریدایرکت به لاگین.
        $this->get('/admin/crm/customer-club/analytics')->assertRedirect();

        // کاربرِ بدونِ دسترسی → ۴۰۳.
        $plain = User::forceCreate([
            'first_name' => 'بی‌دسترسی', 'last_name' => 'ت', 'mobile' => '09121110001',
            'mobile_verified_at' => now(), 'password' => bcrypt('secret'),
        ]);
        $this->actingAs($plain)->get('/admin/crm/customer-club/analytics')->assertForbidden();
    }

    public function test_controller_returns_the_analytics_view_with_data(): void
    {
        $c = $this->customer('الف', '09120000001');
        $this->order($c);

        $controller = app(\Modules\CRM\Http\Controllers\CustomerClubController::class);
        $view = $controller->analytics(request(), app(CustomerClubAnalytics::class));

        $this->assertSame('crm::customer-club.analytics', $view->name());
        $data = $view->getData();
        $this->assertArrayHasKey('acquisition', $data);
        $this->assertArrayHasKey('conversion', $data);
        $this->assertArrayHasKey('retention', $data);
        $this->assertSame(1, $data['conversion']['with_orders']);
    }
}
