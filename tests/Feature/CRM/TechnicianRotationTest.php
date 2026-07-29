<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\AutoAssignService;
use Modules\CRM\Support\AssignmentSettings;
use Tests\TestCase;

/**
 * چرخشِ پخش خودکار: سفارش‌ها یکی‌یکی بین تکنسین‌های واجد شرایط می‌چرخند،
 * کسی که به «سقفِ کارِ فعال در هر دور» رسیده تا وقتی دیگری زیرِ سقف است
 * نوبت نمی‌گیرد، و وقتی همه پر شدند دور از اول شروع می‌شود.
 *
 * جدول‌ها حداقلی ساخته می‌شوند — زنجیرهٔ کاملِ migration های CRM
 * MySQL-محور است و روی sqlite اجرا نمی‌شود.
 */
class TechnicianRotationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--path' => 'Modules/CRM/Database/Migrations/2026_07_28_200000_create_crm_order_assignment_logs_table.php',
            '--force' => true,
        ]);

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->unsignedBigInteger('wp_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->text('service_types')->nullable();
            $t->decimal('satisfaction_score', 3, 2)->nullable();
            $t->unsignedInteger('max_order')->nullable();
            $t->unsignedBigInteger('max_price')->nullable();
            $t->bigInteger('wallet_balance')->default(0);
            $t->string('status', 20)->default('active');
            // نوبتِ چرخشِ پخش خودکار.
            $t->timestamp('last_assigned_at')->nullable();
            $t->boolean('ready_for_delivery')->default(true);
            $t->boolean('exclude_from_suggestions')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->unsignedBigInteger('technician_wp_id')->nullable();
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
            $t->unsignedBigInteger('city_id')->nullable();
            $t->unsignedBigInteger('district_id')->nullable();
            $t->unsignedBigInteger('address_id')->nullable();
            $t->text('address')->nullable();
            $t->string('order_type')->nullable();
            $t->string('status', 30)->default('new');
            // هوکِ مدل روی هر تغییر وضعیت این را می‌نویسد.
            $t->timestamp('status_changed_at')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->timestamp('assigned_at')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_order_status_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('from_status', 40)->nullable();
            $t->string('to_status', 40)->nullable();
            $t->text('note')->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->string('actor_name', 120)->nullable();
            $t->string('actor_role', 20)->nullable();
            $t->unsignedBigInteger('actor_technician_id')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        foreach (['crm_technician_cities' => 'city_id', 'crm_technician_brands' => 'brand_id', 'crm_technician_devices' => 'device_id'] as $table => $column) {
            Schema::create($table, function ($t) use ($column) {
                $t->unsignedBigInteger('technician_id');
                $t->unsignedBigInteger($column);
            });
        }
        Schema::create('crm_technician_districts', function ($t) {
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('district_id');
        });
        Schema::create('crm_cities', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->unsignedBigInteger('parent_city_id')->nullable();
            $t->boolean('is_active')->default(true);
        });
        Schema::create('crm_brands', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });
        Schema::create('crm_devices', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });

        // ردیف‌های مرجع لازم‌اند: رابطهٔ belongsToMany بدون ردیف در جدولِ
        // مقصد خالی برمی‌گردد و «تگ خالی = پوشش کامل» تفسیر می‌شود.
        DB::table('crm_cities')->insert([
            ['id' => 1, 'name' => 'تهران', 'parent_city_id' => null, 'is_active' => true],
        ]);
        DB::table('crm_devices')->insert(
            collect(range(1, 9))->map(fn ($i) => ['id' => $i, 'name' => 'دستگاه '.$i])->all()
        );
        DB::table('crm_brands')->insert(
            collect(range(1, 3))->map(fn ($i) => ['id' => $i, 'name' => 'برند '.$i])->all()
        );

        // پیامک نباید در تست شبکه بزند.
        $this->mock(\Modules\CRM\Services\OrderSmsNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->andReturnNull();
        });
    }

    // ───────────────────────── helpers

    private function technician(string $name, array $attributes = []): Technician
    {
        return Technician::forceCreate(array_merge([
            'first_name' => $name,
            'firstname_tech' => $name,
            'status' => 'active',
            'ready_for_delivery' => true,
            'exclude_from_suggestions' => false,
            'wallet_balance' => 0,
            'service_types' => ['repair'],
        ], $attributes));
    }

    private function coverDevices(Technician $t, array $deviceIds): void
    {
        foreach ($deviceIds as $id) {
            DB::table('crm_technician_devices')->insert([
                'technician_id' => $t->id,
                'device_id' => $id,
            ]);
        }
    }

    /** سفارشِ آمادهٔ پخش — قدیمی‌تر از مهلتِ انتظار. */
    private function order(int $customerId, int $deviceId = 1, array $attributes = []): Order
    {
        static $seq = 0;
        $seq++;

        $order = Order::create(array_merge([
            'order_code' => 'R-'.$seq,
            'customer_id' => $customerId,
            'device_id' => $deviceId,
            'city_id' => 1,
            'address' => 'تهران خیابان ولیعصر پلاک '.$customerId,
            'order_type' => 'repair',
            'status' => 'new',
            'is_lead' => false,
        ], $attributes));

        if (! isset($attributes['technician_id'])) {
            $order->forceFill(['created_at' => now()->subHour()])->save();
        }

        return $order;
    }

    /** تعداد سفارش‌هایی که در این اجرا به این تکنسین رسیده. */
    private function assignedTo(Technician $t, array $orders): int
    {
        return collect($orders)->filter(
            fn (Order $o) => $o->fresh()->technician_id === $t->id
        )->count();
    }

    private function auto(array $extra = []): void
    {
        AssignmentSettings::save(array_merge([
            'assign_mode' => 'auto',
            'assign_min_score' => 0,
        ], $extra));
    }

    // ───────────────────────── چرخشِ یکی‌یکی

    public function test_orders_are_handed_out_one_by_one_between_eligible_technicians(): void
    {
        $this->auto();

        $techs = collect(['اول', 'دوم', 'سوم'])->map(function ($name) {
            $t = $this->technician($name);
            $this->coverDevices($t, [1]);

            return $t;
        });

        // سه سفارش از سه مشتریِ متفاوت ⇒ سه گروهِ جدا.
        $orders = [$this->order(1), $this->order(2), $this->order(3)];

        app(AutoAssignService::class)->run();

        foreach ($techs as $t) {
            $this->assertSame(1, $this->assignedTo($t, $orders),
                'تکنسین «'.$t->first_name.'» باید دقیقاً یک سفارش بگیرد');
        }
    }

    public function test_rotation_continues_where_it_left_off_in_the_next_run(): void
    {
        $this->auto();

        $a = $this->technician('الف');
        $this->coverDevices($a, [1]);
        $b = $this->technician('ب');
        $this->coverDevices($b, [1]);

        $first = $this->order(1);
        app(AutoAssignService::class)->run();
        $firstWinner = $first->fresh()->technician_id;
        $this->assertNotNull($firstWinner);

        // اجرای بعدی — نوبت باید به نفرِ دیگر برسد، نه دوباره همان.
        $second = $this->order(2);
        app(AutoAssignService::class)->run();

        $this->assertNotSame($firstWinner, $second->fresh()->technician_id);

        // و دورِ سوم دوباره به نفرِ اول.
        $third = $this->order(3);
        app(AutoAssignService::class)->run();

        $this->assertSame($firstWinner, $third->fresh()->technician_id);
    }

    // ───────────────────────── سقفِ دور

    public function test_technician_at_the_cap_waits_until_everyone_else_is_full(): void
    {
        $this->auto(['assign_balance_cap' => 2]);

        $loaded = $this->technician('پرکار');
        $this->coverDevices($loaded, [1]);
        // از قبل به سقفِ دور رسیده.
        for ($i = 0; $i < 2; $i++) {
            $this->order(90 + $i, 1, ['technician_id' => $loaded->id, 'status' => 'coordinated']);
        }

        $fresh = $this->technician('تازه‌نفس');
        $this->coverDevices($fresh, [1]);

        $orders = [$this->order(1), $this->order(2), $this->order(3)];

        app(AutoAssignService::class)->run();

        // دو تای اول به «تازه‌نفس» می‌رسد چون «پرکار» به سقف رسیده؛
        // سومی وقتی هر دو پر شده‌اند، یعنی دور از نو شروع شده.
        $this->assertSame(2, $this->assignedTo($fresh, $orders));
        $this->assertSame(1, $this->assignedTo($loaded, $orders));
    }

    public function test_when_everyone_is_full_the_lap_restarts_instead_of_stalling(): void
    {
        $this->auto(['assign_balance_cap' => 1]);

        $a = $this->technician('الف');
        $this->coverDevices($a, [1]);
        $b = $this->technician('ب');
        $this->coverDevices($b, [1]);

        // هر دو از قبل به سقف رسیده‌اند — هیچ‌کس زیرِ سقف نیست.
        $this->order(91, 1, ['technician_id' => $a->id, 'status' => 'coordinated']);
        $this->order(92, 1, ['technician_id' => $b->id, 'status' => 'coordinated']);

        $orders = [$this->order(1), $this->order(2)];

        app(AutoAssignService::class)->run();

        // هیچ سفارشی نباید بی‌صاحب بماند و هر کدام به یک نفر می‌رسد.
        foreach ($orders as $o) {
            $this->assertNotNull($o->fresh()->technician_id);
        }
        $this->assertSame(1, $this->assignedTo($a, $orders));
        $this->assertSame(1, $this->assignedTo($b, $orders));
    }

    // ───────────────────────── یکپارچگیِ گروه

    public function test_a_single_customer_group_is_never_split_by_the_cap(): void
    {
        $this->auto(['assign_balance_cap' => 2]);

        $all = $this->technician('همه‌کاره');
        $this->coverDevices($all, [1, 2, 3, 4]);

        // رقیبی که فقط یک دستگاه را پوشش می‌دهد — نباید برنده شود.
        $narrow = $this->technician('تک‌کاره');
        $this->coverDevices($narrow, [1]);

        // چهار سفارشِ یک مشتری، یک آدرس، یک روز ⇒ یک گروه.
        $orders = [];
        foreach ([1, 2, 3, 4] as $device) {
            $orders[] = $this->order(7, $device);
        }

        app(AutoAssignService::class)->run();

        $this->assertSame(4, $this->assignedTo($all, $orders),
            'سقفِ دور نباید گروهِ یک مشتری را بشکند');
    }

    public function test_a_late_sibling_sticks_to_the_same_technician_even_past_the_cap(): void
    {
        $this->auto(['assign_balance_cap' => 1]);

        $a = $this->technician('الف');
        $this->coverDevices($a, [1, 2]);
        $b = $this->technician('ب');
        $this->coverDevices($b, [1, 2]);

        $first = $this->order(5, 1);
        app(AutoAssignService::class)->run();
        $owner = $first->fresh()->technician_id;
        $this->assertNotNull($owner);

        // سفارشِ دومِ همان مشتری و همان آدرس، چند دقیقه دیرتر. صاحبِ گروه
        // حالا به سقفِ دور رسیده ولی چسبندگی مقدم است.
        $late = $this->order(5, 2);
        app(AutoAssignService::class)->run();

        $this->assertSame($owner, $late->fresh()->technician_id);
    }

    // ───────────────────────── نوبت روی مدل

    public function test_assignment_stamps_the_rotation_turn(): void
    {
        $this->auto();

        $t = $this->technician('تنها');
        $this->coverDevices($t, [1]);
        $this->assertNull($t->last_assigned_at);

        $this->order(1);
        app(AutoAssignService::class)->run();

        $this->assertNotNull($t->fresh()->last_assigned_at);
    }
}
