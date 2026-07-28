<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderAssignmentLog;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\AutoAssignService;
use Modules\CRM\Services\OrderGroupResolver;
use Modules\CRM\Services\TechnicianGroupPlanner;
use Modules\CRM\Support\AssignmentSettings;
use Tests\TestCase;

/**
 * تخصیص گروهی تکنسین: سفارش‌های یک مشتری با یک آدرس در یک روز باید تا
 * حد ممکن به یک تکنسین برسند، نه به چند نفر.
 *
 * جدول‌ها حداقلی ساخته می‌شوند — زنجیرهٔ کاملِ migration های CRM
 * MySQL-محور است و روی sqlite اجرا نمی‌شود.
 */
class TechnicianGroupAssignmentTest extends TestCase
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
            // نوع خدمات باید پر باشد؛ پروفایل ناقص از پیشنهاد و پخش کنار می‌رود.
            'service_types' => ['repair'],
        ], $attributes));
    }

    private function order(int $deviceId, array $attributes = []): Order
    {
        static $seq = 0;
        $seq++;

        return Order::create(array_merge([
            'order_code' => 'T-'.$seq,
            'customer_id' => 1,
            'device_id' => $deviceId,
            'city_id' => 1,
            'address' => 'تهران خیابان ولیعصر پلاک ۱۲',
            'order_type' => 'repair',
            'status' => 'new',
            'is_lead' => false,
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

    // ───────────────────────── گروه‌بندی

    public function test_orders_of_same_customer_address_and_day_are_one_group(): void
    {
        $a = $this->order(1);
        $this->order(2);
        $this->order(3);

        $group = app(OrderGroupResolver::class)->siblingsOf($a);

        $this->assertCount(3, $group);
    }

    public function test_different_address_is_a_different_group(): void
    {
        $a = $this->order(1);
        $this->order(2, ['address' => 'کرج بلوار طالقانی پلاک ۹']);

        $this->assertCount(1, app(OrderGroupResolver::class)->siblingsOf($a));
    }

    public function test_address_normalization_treats_persian_digits_and_spacing_as_equal(): void
    {
        $a = $this->order(1, ['address' => 'تهران، خیابان ولیعصر، پلاک ۱۲']);
        $this->order(2, ['address' => 'تهران خيابان   ولیعصر پلاک 12']);

        $this->assertCount(2, app(OrderGroupResolver::class)->siblingsOf($a));
    }

    public function test_yesterdays_order_is_not_in_todays_group(): void
    {
        $a = $this->order(1);
        $old = $this->order(2);
        $old->forceFill(['created_at' => now()->subDay()])->save();

        $this->assertCount(1, app(OrderGroupResolver::class)->siblingsOf($a));
    }

    // ───────────────────────── نقشهٔ تخصیص

    public function test_one_technician_covering_all_three_wins(): void
    {
        $all = $this->technician('همه‌کاره');
        $this->coverDevices($all, [1, 2, 3]);

        $partial = $this->technician('نصفه');
        $this->coverDevices($partial, [1]);

        $orders = collect([$this->order(1), $this->order(2), $this->order(3)]);
        $plan = app(TechnicianGroupPlanner::class)->plan($orders);

        $this->assertCount(1, $plan->steps);
        $this->assertSame($all->id, $plan->steps->first()->technician->id);
        $this->assertCount(3, $plan->steps->first()->orders);
        $this->assertTrue($plan->unassignable->isEmpty());
    }

    public function test_falls_back_to_two_plus_one_when_nobody_covers_everything(): void
    {
        $two = $this->technician('دوتایی');
        $this->coverDevices($two, [1, 2]);

        $one = $this->technician('تکی');
        $this->coverDevices($one, [3]);

        $orders = collect([$this->order(1), $this->order(2), $this->order(3)]);
        $plan = app(TechnicianGroupPlanner::class)->plan($orders);

        $this->assertCount(2, $plan->steps);
        $this->assertSame($two->id, $plan->steps[0]->technician->id);
        $this->assertCount(2, $plan->steps[0]->orders);
        $this->assertSame($one->id, $plan->steps[1]->technician->id);
        $this->assertCount(1, $plan->steps[1]->orders);
    }

    public function test_capacity_limit_caps_how_many_orders_one_technician_takes(): void
    {
        // تنها کسی که هر سه را پوشش می‌دهد سقف ۲ سفارش دارد؛ سومی باید
        // به نفر بعدی برسد، نه اینکه سقف نادیده گرفته شود.
        $limited = $this->technician('سقف‌دار', ['max_order' => 2]);
        $this->coverDevices($limited, [1, 2, 3]);

        $backup = $this->technician('پشتیبان');
        $this->coverDevices($backup, [3]);

        $orders = collect([$this->order(1), $this->order(2), $this->order(3)]);
        $plan = app(TechnicianGroupPlanner::class)->plan($orders);

        $counts = $plan->steps->map(fn ($s) => $s->orders->count())->sort()->values()->all();
        $this->assertSame([1, 2], $counts);
        $this->assertCount(2, $plan->steps->firstWhere('technician.id', $limited->id)->orders);
        $this->assertCount(1, $plan->steps->firstWhere('technician.id', $backup->id)->orders);
        $this->assertTrue($plan->unassignable->isEmpty());
    }

    public function test_orders_nobody_covers_are_reported_as_unassignable(): void
    {
        $t = $this->technician('محدود');
        $this->coverDevices($t, [1]);

        $orders = collect([$this->order(1), $this->order(9)]);
        $plan = app(TechnicianGroupPlanner::class)->plan($orders);

        $this->assertCount(1, $plan->steps);
        $this->assertCount(1, $plan->unassignable);
        $this->assertSame(9, (int) $plan->unassignable->first()->device_id);
    }

    public function test_sticky_technician_of_the_same_address_wins_over_higher_score(): void
    {
        $sticky = $this->technician('چسبنده', ['wallet_balance' => -4_000_000]);
        $this->coverDevices($sticky, [1, 2]);

        $fresh = $this->technician('تازه‌نفس');
        $this->coverDevices($fresh, [1, 2]);

        $orders = collect([$this->order(1), $this->order(2)]);
        $plan = app(TechnicianGroupPlanner::class)->plan($orders, [$sticky->id]);

        $this->assertSame($sticky->id, $plan->steps->first()->technician->id);
        $this->assertTrue($plan->steps->first()->sticky);
    }

    // ───────────────────────── پخش خودکار

    public function test_auto_assign_does_nothing_while_mode_is_suggest(): void
    {
        $t = $this->technician('آماده');
        $this->coverDevices($t, [1]);
        $order = $this->order(1);
        $order->forceFill(['created_at' => now()->subHour()])->save();

        app(AutoAssignService::class)->run();

        $this->assertNull($order->fresh()->technician_id);
        $this->assertDatabaseCount('crm_order_assignment_logs', 0);
    }

    public function test_auto_assign_gives_every_order_of_one_address_to_one_technician(): void
    {
        AssignmentSettings::save(['assign_mode' => 'auto', 'assign_min_score' => 0]);

        $all = $this->technician('همه‌کاره');
        $this->coverDevices($all, [1, 2, 3]);

        $orders = collect([$this->order(1), $this->order(2), $this->order(3)]);
        foreach ($orders as $o) {
            $o->forceFill(['created_at' => now()->subHour()])->save();
        }

        $stats = app(AutoAssignService::class)->run();

        $this->assertSame(3, $stats['assigned']);
        foreach ($orders as $o) {
            $this->assertSame($all->id, $o->fresh()->technician_id);
        }
    }

    public function test_auto_assign_respects_the_grace_period(): void
    {
        AssignmentSettings::save(['assign_mode' => 'auto', 'assign_grace_minutes' => 10, 'assign_min_score' => 0]);

        $t = $this->technician('آماده');
        $this->coverDevices($t, [1]);
        $order = $this->order(1); // همین الان ثبت شده

        app(AutoAssignService::class)->run();

        $this->assertNull($order->fresh()->technician_id);
    }

    public function test_auto_assign_skips_candidates_below_minimum_score(): void
    {
        AssignmentSettings::save(['assign_mode' => 'auto', 'assign_min_score' => 95]);

        $t = $this->technician('کم‌امتیاز', ['wallet_balance' => -5_000_000]);
        $this->coverDevices($t, [1]);
        $order = $this->order(1);
        $order->forceFill(['created_at' => now()->subHour()])->save();

        $stats = app(AutoAssignService::class)->run();

        $this->assertSame(0, $stats['assigned']);
        $this->assertSame(1, $stats['skipped_low_score']);
        $this->assertNull($order->fresh()->technician_id);
    }

    public function test_capacity_used_in_one_group_is_not_reused_in_the_next(): void
    {
        AssignmentSettings::save(['assign_mode' => 'auto', 'assign_min_score' => 0]);

        // یک تکنسین با سقف ۲ و دو گروهِ جدا (دو مشتری). نباید در گروه دوم
        // دوباره انتخاب شود، چون ظرفیتش در گروه اول مصرف شده.
        $limited = $this->technician('سقف‌دار', ['max_order' => 2]);
        $this->coverDevices($limited, [1]);

        $orders = collect([
            $this->order(1, ['customer_id' => 1, 'address' => 'آدرس یک']),
            $this->order(1, ['customer_id' => 1, 'address' => 'آدرس یک']),
            $this->order(1, ['customer_id' => 2, 'address' => 'آدرس دو']),
        ]);
        foreach ($orders as $o) {
            $o->forceFill(['created_at' => now()->subHour()])->save();
        }

        $stats = app(AutoAssignService::class)->run();

        $this->assertSame(2, $stats['assigned']);
        $this->assertSame($limited->id, $orders[0]->fresh()->technician_id);
        $this->assertSame($limited->id, $orders[1]->fresh()->technician_id);
        $this->assertNull($orders[2]->fresh()->technician_id);
    }

    // ───────────────────────── نوع خدمت

    public function test_technician_without_service_types_is_excluded(): void
    {
        $incomplete = $this->technician('پروفایل ناقص', ['service_types' => null]);
        $this->coverDevices($incomplete, [1]);

        $plan = app(TechnicianGroupPlanner::class)->plan(collect([$this->order(1)]));

        $this->assertCount(0, $plan->steps);
        $this->assertCount(1, $plan->unassignable);
    }

    public function test_technician_who_does_not_offer_that_service_type_is_excluded(): void
    {
        $installer = $this->technician('فقط نصاب', ['service_types' => ['install']]);
        $this->coverDevices($installer, [1]);
        $repairer = $this->technician('تعمیرکار', ['service_types' => ['repair']]);
        $this->coverDevices($repairer, [1]);

        $plan = app(TechnicianGroupPlanner::class)->plan(collect([$this->order(1, ['order_type' => 'repair'])]));

        $this->assertCount(1, $plan->steps);
        $this->assertSame($repairer->id, $plan->steps->first()->technician->id);
    }

    public function test_diagnosis_explains_the_missing_service_types(): void
    {
        $incomplete = $this->technician('پروفایل ناقص', ['service_types' => []]);
        $this->coverDevices($incomplete, [1]);

        $diagnosis = app(\Modules\CRM\Services\TechnicianSuggestionService::class)
            ->diagnoseForOrder($this->order(1));

        $this->assertSame(0, $diagnosis['accepted']);
        $this->assertSame(1, $diagnosis['rejections']['no_service_types'] ?? 0);
    }

    public function test_auto_assign_skips_orders_without_a_service_type(): void
    {
        AssignmentSettings::save(['assign_mode' => 'auto', 'assign_min_score' => 0]);

        $t = $this->technician('آماده');
        $this->coverDevices($t, [1]);

        $untyped = $this->order(1, ['order_type' => null]);
        $untyped->forceFill(['created_at' => now()->subHour()])->save();

        $stats = app(AutoAssignService::class)->run();

        $this->assertSame(0, $stats['assigned']);
        $this->assertSame(1, $stats['skipped_no_type']);
        $this->assertNull($untyped->fresh()->technician_id);
    }

    // ───────────────────────── وزن‌های داینامیک

    public function test_weights_are_normalised_to_one_hundred(): void
    {
        AssignmentSettings::saveWeights([
            'open_orders' => 60,
            'debt' => 60,
            'satisfaction' => 30,
            'cancel_rate' => 0,
            'recent_activity' => 0,
            'response_speed' => 0,
        ]);

        $weights = AssignmentSettings::weights();

        $this->assertSame(100, array_sum($weights));
        $this->assertSame(0, $weights['cancel_rate']);
        $this->assertGreaterThan($weights['satisfaction'], $weights['open_orders']);
    }

    public function test_all_zero_weights_fall_back_to_defaults(): void
    {
        AssignmentSettings::saveWeights(array_fill_keys(
            array_keys(\Modules\CRM\Services\TechnicianSuggestionService::WEIGHTS), 0
        ));

        $this->assertSame(
            \Modules\CRM\Services\TechnicianSuggestionService::WEIGHTS,
            AssignmentSettings::weights()
        );
    }

    public function test_changing_weights_changes_who_is_suggested(): void
    {
        // «بدهکارِ خلوت» بدهی دارد ولی سفارش باز ندارد.
        // «بی‌بدهیِ شلوغ» بدهی ندارد ولی ۸ سفارش باز دارد.
        $indebted = $this->technician('بدهکار خلوت', ['wallet_balance' => -4_000_000]);
        $this->coverDevices($indebted, [1]);

        $busy = $this->technician('بی‌بدهی شلوغ');
        $this->coverDevices($busy, [1]);
        for ($i = 0; $i < 8; $i++) {
            $this->order(1, ['technician_id' => $busy->id, 'status' => 'coordinated']);
        }

        // وزن همه روی «سفارش باز» → بدهکارِ خلوت باید برنده شود.
        AssignmentSettings::saveWeights([
            'open_orders' => 100, 'debt' => 0, 'satisfaction' => 0,
            'cancel_rate' => 0, 'recent_activity' => 0, 'response_speed' => 0,
        ]);
        $winner = app(TechnicianGroupPlanner::class)->plan(collect([$this->order(1)]))->steps->first();
        $this->assertSame($indebted->id, $winner->technician->id);

        // وزن همه روی «بدهی» → بی‌بدهیِ شلوغ باید برنده شود.
        AssignmentSettings::saveWeights([
            'open_orders' => 0, 'debt' => 100, 'satisfaction' => 0,
            'cancel_rate' => 0, 'recent_activity' => 0, 'response_speed' => 0,
        ]);
        $winner = app(TechnicianGroupPlanner::class)->plan(collect([$this->order(1)]))->steps->first();
        $this->assertSame($busy->id, $winner->technician->id);
    }

    // ───────────────────────── لاگ دلیل

    public function test_assignment_writes_a_reason_log_with_score_and_group_size(): void
    {
        AssignmentSettings::save(['assign_mode' => 'auto', 'assign_min_score' => 0]);

        $all = $this->technician('همه‌کاره');
        $this->coverDevices($all, [1, 2]);

        $orders = collect([$this->order(1), $this->order(2)]);
        foreach ($orders as $o) {
            $o->forceFill(['created_at' => now()->subHour()])->save();
        }

        app(AutoAssignService::class)->run();

        $log = OrderAssignmentLog::where('order_id', $orders[0]->id)->first();

        $this->assertNotNull($log);
        $this->assertSame('auto', $log->mode);
        $this->assertSame($all->id, $log->technician_id);
        $this->assertSame(2, $log->group_size);
        $this->assertSame(2, $log->covered_count);
        $this->assertNotNull($log->score);
        $this->assertStringContainsString('پوشش 2 سفارش از 2', $log->note);
    }

    public function test_unassign_is_also_recorded(): void
    {
        AssignmentSettings::save(['assign_mode' => 'auto', 'assign_min_score' => 0]);

        $t = $this->technician('آماده');
        $this->coverDevices($t, [1]);
        $order = $this->order(1);
        $order->forceFill(['created_at' => now()->subHour()])->save();

        app(AutoAssignService::class)->run();
        app(\Modules\CRM\Services\OrderAssigner::class)->unassign($order->fresh(), null);

        $this->assertDatabaseHas('crm_order_assignment_logs', [
            'order_id' => $order->id,
            'mode' => 'unassign',
            'previous_technician_id' => $t->id,
        ]);
    }
}
