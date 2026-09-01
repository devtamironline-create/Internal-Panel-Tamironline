<?php

namespace Tests\Feature\Technician;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\Technician\Services\TechnicianDashboardService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * داشبورد مدیریتی تکنسین‌ها: صحت تجمیع آمار، رتبه‌بندی و دسترسی.
 *
 * جدول‌ها به‌صورت حداقلی ساخته می‌شوند (زنجیرهٔ کاملِ migration های CRM
 * MySQL-محور است و روی sqlite اجرا نمی‌شود) — دقیقاً همان ستون‌هایی که
 * سرویس و ویو استفاده می‌کنند.
 */
class TechnicianDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2025_12_19_195120_create_permission_tables.php',
            'database/migrations/2026_01_04_100000_create_chat_tables.php',
            'database/migrations/2026_02_03_140854_create_settings_table.php',
        ] as $path) {
            Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        }

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->unsignedBigInteger('wp_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('technician_id')->nullable();
            $t->string('national_code')->nullable();
            $t->string('mobile')->nullable();
            $t->string('province')->nullable();
            $t->text('address')->nullable();
            $t->string('specialty')->nullable();
            $t->string('type_tech')->nullable();
            $t->string('img_personal')->nullable();
            $t->unsignedTinyInteger('percent')->nullable();
            $t->string('status', 20)->default('active');
            $t->boolean('ready_for_delivery')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });

        // سایدبار پنل تعداد درخواست‌های در انتظار را می‌خواند.
        Schema::create('technician_registrations', function ($t) {
            $t->id();
            $t->string('status', 30)->default('pending');
            $t->timestamps();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status', 30)->default('new');
            // هوکِ مدل روی هر تغییر وضعیت این را می‌نویسد.
            $t->timestamp('status_changed_at')->nullable();
            $t->unsignedBigInteger('price_customer')->nullable();
            $t->unsignedBigInteger('total_invoice')->nullable();
            $t->unsignedBigInteger('final_price')->nullable();
            $t->timestamps();
        });

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_technicians_are_ranked_by_order_count_descending(): void
    {
        $busy = $this->technician('پرکار', '09120000101');
        $quiet = $this->technician('کم‌کار', '09120000102');
        $idle = $this->technician('بی‌سفارش', '09120000103');

        $this->orders($busy, OrderStatus::Completed, 5);
        $this->orders($quiet, OrderStatus::Completed, 2);

        $rows = app(TechnicianDashboardService::class)->build(['sort' => 'orders'])['rows'];

        $this->assertSame(
            [$busy->id, $quiet->id, $idle->id],
            $rows->pluck('technician.id')->all(),
            'ترتیب باید از بیشترین سفارش به کمترین باشد.'
        );
        $this->assertSame(5, $rows[0]['total']);
        $this->assertSame(0, $rows[2]['total']);
    }

    public function test_default_order_is_by_active_load_not_by_total_orders(): void
    {
        // «پرسابقه» سفارش بیشتری دارد ولی همه تمام شده‌اند؛
        // «پرمشغله» کمتر دارد ولی همه‌شان روی دستش مانده.
        $veteran = $this->technician('پرسابقه', '09120000111');
        $busyNow = $this->technician('پرمشغله', '09120000112');

        $this->orders($veteran, OrderStatus::Completed, 20);
        $this->orders($busyNow, OrderStatus::AwaitingPart, 3);   // waiting
        $this->orders($busyNow, OrderStatus::Coordinated, 2);  // in_progress

        $rows = app(TechnicianDashboardService::class)->build()['rows'];

        $this->assertSame($busyNow->id, $rows[0]['technician']->id, 'کارِ فعالِ بیشتر باید اول بیاید.');
        $this->assertSame(5, $rows[0]['open'], 'کار فعال = در انتظار + در جریان');
        $this->assertSame(0, $rows[1]['open']);
    }

    public function test_active_load_ties_are_broken_by_in_progress_work(): void
    {
        $waiting = $this->technician('همه در انتظار', '09120000113');
        $working = $this->technician('در حال کار', '09120000114');

        $this->orders($waiting, OrderStatus::AwaitingPart, 4);
        $this->orders($working, OrderStatus::AwaitingPart, 1);
        $this->orders($working, OrderStatus::Coordinated, 3);

        $rows = app(TechnicianDashboardService::class)->build()['rows'];

        $this->assertSame(4, $rows[0]['open']);
        $this->assertSame(4, $rows[1]['open']);
        $this->assertSame($working->id, $rows[0]['technician']->id, 'در تساوی، کارِ در جریانِ بیشتر جلوتر.');
    }

    public function test_orders_are_grouped_by_status_correctly(): void
    {
        $tech = $this->technician('تفکیک', '09120000104');

        $this->orders($tech, OrderStatus::Coordinated, 2);   // in_progress
        $this->orders($tech, OrderStatus::Coordinated, 1);     // in_progress
        $this->orders($tech, OrderStatus::AwaitingPart, 3);    // waiting
        $this->orders($tech, OrderStatus::Completed, 4);       // finished
        $this->orders($tech, OrderStatus::Cancelled, 1);       // finished
        $this->orders($tech, OrderStatus::Declined, 1);        // finished

        $row = app(TechnicianDashboardService::class)->build()['rows'][0];

        $this->assertSame(12, $row['total']);
        $this->assertSame(3, $row['groups']['in_progress']);
        $this->assertSame(3, $row['groups']['waiting']);
        $this->assertSame(6, $row['groups']['finished']);
        $this->assertSame(4, $row['completed']);
        $this->assertSame(2, $row['cancelled'], 'لغو + رد شده');
        $this->assertSame(33, $row['completion_rate'], '4 از 12');

        // هر وضعیت با برچسب فارسی و تعداد خودش برمی‌گردد.
        $labels = collect($row['statuses'])->pluck('count', 'label');
        $this->assertSame(4, $labels['انجام کار']);
        $this->assertSame(3, $labels['در انتظار قطعه']);
    }

    public function test_revenue_follows_commission_amount_precedence(): void
    {
        $tech = $this->technician('مالی', '09120000105');

        // اولویت: price_customer ← total_invoice ← final_price
        DB::table('crm_orders')->insert([
            ['technician_id' => $tech->id, 'status' => 'completed', 'price_customer' => 1000, 'total_invoice' => 999, 'final_price' => 888, 'created_at' => now(), 'updated_at' => now()],
            ['technician_id' => $tech->id, 'status' => 'completed', 'price_customer' => 0, 'total_invoice' => 500, 'final_price' => 400, 'created_at' => now(), 'updated_at' => now()],
            ['technician_id' => $tech->id, 'status' => 'completed', 'price_customer' => null, 'total_invoice' => null, 'final_price' => 250, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $row = app(TechnicianDashboardService::class)->build()['rows'][0];

        $this->assertSame(1750, $row['revenue'], '1000 + 500 + 250');
    }

    public function test_date_range_filter_limits_orders(): void
    {
        $tech = $this->technician('بازه', '09120000106');

        $this->orders($tech, OrderStatus::Completed, 2);
        DB::table('crm_orders')->insert([
            'technician_id' => $tech->id, 'status' => 'completed',
            'created_at' => now()->subDays(120), 'updated_at' => now()->subDays(120),
        ]);

        $service = app(TechnicianDashboardService::class);

        $this->assertSame(3, $service->build(['range' => 'all'])['rows'][0]['total']);
        $this->assertSame(2, $service->build(['range' => '30'])['rows'][0]['total']);
        $this->assertSame(3, $service->build(['range' => '365'])['rows'][0]['total']);
    }

    public function test_summary_and_unassigned_orders_are_computed(): void
    {
        $active = $this->technician('فعال', '09120000107', ['ready_for_delivery' => true]);
        $this->technician('غیرفعال', '09120000108', ['status' => 'inactive']);

        $this->orders($active, OrderStatus::Completed, 3);
        $this->orders($active, OrderStatus::Coordinated, 1);

        // سفارش بدون تکنسین
        DB::table('crm_orders')->insert([
            'technician_id' => null, 'status' => 'new',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $data = app(TechnicianDashboardService::class)->build();

        $this->assertSame(2, $data['summary']['technicians']);
        $this->assertSame(1, $data['summary']['active']);
        $this->assertSame(1, $data['summary']['ready']);
        $this->assertSame(1, $data['summary']['with_orders']);
        $this->assertSame(1, $data['summary']['idle']);
        $this->assertSame(4, $data['summary']['orders']);
        $this->assertSame(3, $data['summary']['completed']);
        $this->assertSame(1, $data['unassignedOrders'], 'سفارش بدون تکنسین باید جدا شمرده شود.');
    }

    public function test_profile_completeness_lists_missing_fields(): void
    {
        $complete = $this->technician('کامل', '09120000109', [
            'national_code' => '0012345678',
            'province' => 'تهران',
            'address' => 'خیابان آزادی',
            'specialty' => 'لباسشویی',
            'percent' => 40,
            'img_personal' => 'photo.jpg',
            'user_id' => 1,
        ]);
        $incomplete = $this->technician('ناقص', '09120000110');

        $rows = app(TechnicianDashboardService::class)->build(['sort' => 'profile'])['rows'];
        $byId = $rows->keyBy(fn ($r) => $r['technician']->id);

        $this->assertSame(100, $byId[$complete->id]['profile']['percent']);
        $this->assertSame([], $byId[$complete->id]['profile']['missing']);

        $this->assertLessThan(100, $byId[$incomplete->id]['profile']['percent']);
        $this->assertContains('کد ملی', $byId[$incomplete->id]['profile']['missing']);
    }

    public function test_filters_by_status_and_only_with_orders(): void
    {
        $withOrders = $this->technician('دارای سفارش', '09120000111');
        $this->technician('بدون سفارش', '09120000112');
        $this->technician('غیرفعال', '09120000113', ['status' => 'inactive']);

        $this->orders($withOrders, OrderStatus::Completed, 1);

        $service = app(TechnicianDashboardService::class);

        $this->assertSame(1, $service->build(['only_with_orders' => true])['rows']->count());
        $this->assertSame(2, $service->build(['status' => 'active'])['rows']->count());
        $this->assertSame(1, $service->build(['status' => 'inactive'])['rows']->count());
    }

    public function test_aggregation_does_not_scale_queries_with_technicians(): void
    {
        foreach (range(1, 12) as $i) {
            $tech = $this->technician("تکنسین {$i}", '0912000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT));
            $this->orders($tech, OrderStatus::Completed, 2);
            $this->orders($tech, OrderStatus::AwaitingPart, 1);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(TechnicianDashboardService::class)->build();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // انتظار: سفارش‌ها + تکنسین‌ها + استان‌ها + سفارش بدون تکنسین ≈ ۴ کوئری،
        // مستقل از تعداد تکنسین‌ها (بدون N+1).
        $this->assertLessThanOrEqual(6, $queries, "تعداد کوئری نباید با تعداد تکنسین‌ها رشد کند (اجرا شد: {$queries})");
    }

    public function test_dashboard_route_requires_permission_and_renders(): void
    {
        Permission::firstOrCreate(['name' => 'manage-technicians', 'guard_name' => 'web']);

        $tech = $this->technician('نمایشی', '09120000114');
        $this->orders($tech, OrderStatus::Coordinated, 2);

        $this->get('/admin/technician')->assertRedirect();

        $plain = $this->user('09121110001');
        $this->actingAs($plain)->get('/admin/technician')->assertForbidden();

        $admin = $this->user('09121110002');
        $admin->givePermissionTo('manage-technicians');

        $this->actingAs($admin)
            ->get('/admin/technician')
            ->assertOk()
            ->assertSee('داشبورد مدیریتی تکنسین‌ها')
            ->assertSee('نمایشی')
            ->assertSee('شروع تعمیر');
    }

    private function technician(string $name, string $mobile, array $attributes = []): object
    {
        $id = DB::table('crm_technicians')->insertGetId($attributes + [
            'first_name' => $name,
            'mobile' => $mobile,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['id' => $id];
    }

    private function orders(object $tech, OrderStatus $status, int $count): void
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'technician_id' => $tech->id,
                'status' => $status->value,
                'final_price' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('crm_orders')->insert($rows);
    }

    private function user(string $mobile): User
    {
        return User::forceCreate([
            'first_name' => 'مدیر',
            'last_name' => 'تست',
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
            'password' => bcrypt('secret'),
        ]);
    }
}
