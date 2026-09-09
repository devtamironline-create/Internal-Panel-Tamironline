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
 * باشگاه مشتریان — نمای کلی (واقعی/فعال)، VIP بر پایهٔ مبلغ، ریزشِ ۹۰ روز،
 * RFM و گیتِ دسترسی. جدول‌ها حداقلی ساخته می‌شوند (sqlite).
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
            $t->boolean('is_blocked')->default(false);
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
            $t->unsignedBigInteger('final_price')->nullable();
            $t->timestamps();
        });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function customer(string $name, string $mobile, array $attrs = []): int
    {
        return DB::table('crm_customers')->insertGetId(array_merge([
            'first_name' => $name, 'mobile' => $mobile, 'is_blocked' => false,
            'created_at' => now(), 'updated_at' => now(),
        ], $attrs));
    }

    private function order(int $customerId, array $attrs = []): void
    {
        DB::table('crm_orders')->insert(array_merge([
            'customer_id' => $customerId, 'status' => OrderStatus::Completed->value,
            'final_price' => 1_000_000, 'created_at' => now(), 'updated_at' => now(),
        ], $attrs));
    }

    public function test_overview_counts_real_customers_conversion_and_repeat(): void
    {
        $a = $this->customer('الف', '09120000001');
        $b = $this->customer('ب', '09120000002');
        $this->customer('ج بدون‌سفارش', '09120000003');
        $this->customer('بلاک‌شده', '09120000004', ['is_blocked' => true]); // نباید شمرده شود

        $this->order($a);
        $this->order($a); // تکراری
        $this->order($b);

        $ov = app(CustomerClubAnalytics::class)->build()['overview'];

        $this->assertSame(3, $ov['total_customers'], 'بلاک‌شده حذف می‌شود.');
        $this->assertSame(2, $ov['with_orders']);
        $this->assertSame(1, $ov['without_orders']);
        $this->assertEqualsWithDelta(66.7, $ov['conversion_rate'], 0.1);
        $this->assertSame(1, $ov['repeat'], 'الف با ۲ سفارش = تکراری');
        $this->assertSame(2, $ov['active_customers'], 'هر دو در ۹۰ روزِ اخیر فعال‌اند');
    }

    public function test_vip_is_top_spenders_by_total_amount(): void
    {
        $big = $this->customer('پُرخرید', '09120000001');
        $small = $this->customer('کم‌خرید', '09120000002');

        $this->order($big, ['final_price' => 5_000_000]);
        $this->order($big, ['final_price' => 5_000_000]);
        $this->order($small, ['final_price' => 300_000]);

        $vip = app(CustomerClubAnalytics::class)->vipQuery()->get();

        $this->assertSame('پُرخرید', $vip->first()->first_name);
        $this->assertSame(10_000_000, (int) $vip->first()->monetary);
    }

    public function test_at_risk_is_customers_silent_for_over_90_days(): void
    {
        $stale = $this->customer('کهنه', '09120000001');
        $fresh = $this->customer('تازه', '09120000002');

        $this->order($stale, ['created_at' => now()->subDays(120)]);
        $this->order($fresh, ['created_at' => now()->subDays(10)]);

        $atRisk = app(CustomerClubAnalytics::class)->atRiskQuery()->get();

        $this->assertCount(1, $atRisk);
        $this->assertSame('کهنه', $atRisk->first()->first_name);
    }

    public function test_demand_top_devices_and_brand_unspecified_split(): void
    {
        $washer = DB::table('crm_devices')->insertGetId(['name' => 'لباسشویی']);
        $realBrand = DB::table('crm_brands')->insertGetId(['name' => 'سامسونگ']);
        $otherBrand = DB::table('crm_brands')->insertGetId(['name' => 'سایر']);
        $c = $this->customer('الف', '09120000001');

        $this->order($c, ['device_id' => $washer, 'brand_id' => $realBrand]);
        $this->order($c, ['device_id' => $washer, 'brand_id' => $otherBrand]); // «سایر» → نامشخص
        $this->order($c, ['device_id' => $washer, 'brand_id' => null]);        // بدون برند → نامشخص

        $demand = app(CustomerClubAnalytics::class)->build()['demand'];

        $this->assertSame('لباسشویی', $demand['top_devices'][0]['name']);
        $this->assertSame(3, $demand['top_devices'][0]['count']);

        $brandNames = collect($demand['top_brands']['items'])->pluck('name')->all();
        $this->assertContains('سامسونگ', $brandNames);
        $this->assertNotContains('سایر', $brandNames, '«سایر» نباید برندِ واقعی شمرده شود.');
        $this->assertSame(2, $demand['top_brands']['unspecified'], 'null + «سایر»');
    }

    public function test_rfm_builds_a_five_by_five_matrix(): void
    {
        foreach (range(1, 6) as $i) {
            $c = $this->customer("م{$i}", '0912000000'.$i);
            $this->order($c, ['final_price' => $i * 100000]);
        }

        $rfm = app(CustomerClubAnalytics::class)->build()['rfm'];

        $this->assertSame(6, $rfm['count']);
        $this->assertCount(5, $rfm['matrix']);          // R 1..5
        $this->assertCount(5, $rfm['matrix'][5]);       // F 1..5
        $total = 0;
        foreach ($rfm['matrix'] as $fr) {
            $total += array_sum($fr);
        }
        $this->assertSame(6, $total, 'مجموعِ خانه‌های ماتریس = تعدادِ مشتریان');
    }

    public function test_route_requires_permission(): void
    {
        Permission::firstOrCreate(['name' => 'view-customer-club', 'guard_name' => 'web']);

        $this->get('/admin/crm/customer-club/analytics')->assertRedirect();

        $plain = User::forceCreate([
            'first_name' => 'بی‌دسترسی', 'last_name' => 'ت', 'mobile' => '09121110001',
            'mobile_verified_at' => now(), 'password' => bcrypt('secret'),
        ]);
        $this->actingAs($plain)->get('/admin/crm/customer-club/analytics')->assertForbidden();
    }

    public function test_controller_returns_the_view_with_new_data_shape(): void
    {
        $c = $this->customer('الف', '09120000001');
        $this->order($c);

        $controller = app(\Modules\CRM\Http\Controllers\CustomerClubController::class);
        $view = $controller->analytics(request(), app(CustomerClubAnalytics::class));

        $this->assertSame('crm::customer-club.analytics', $view->name());
        foreach (['overview', 'acquisition_trend', 'order_trend', 'demand', 'heatmap', 'rfm', 'segments', 'windows'] as $key) {
            $this->assertArrayHasKey($key, $view->getData());
        }
        $this->assertSame(1, $view->getData()['overview']['with_orders']);
    }
}
