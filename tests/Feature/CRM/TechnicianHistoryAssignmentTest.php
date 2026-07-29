<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderAssignmentLog;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\AutoAssignService;
use Modules\CRM\Services\TechnicianGroupPlanner;
use Modules\CRM\Services\TechnicianHistoryService;
use Modules\CRM\Support\AssignmentSettings;
use Tests\TestCase;

/**
 * قاعدهٔ «تکنسین قبلیِ همان دستگاه»: اگر مشتری همان دستگاه را دوباره
 * ثبت کند و تعمیر قبلی در بازهٔ اعتبار انجام شده باشد، همان تکنسین.
 */
class TechnicianHistoryAssignmentTest extends TestCase
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
            $t->string('customer_mobile')->nullable();
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
            $t->timestamp('completed_at')->nullable();
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
            Schema::create($table, function ($t) use ($column, $table) {
                $t->unsignedBigInteger('technician_id');
                $t->unsignedBigInteger($column);
                // اولویتِ تخصص — فقط روی جدولِ دستگاه‌ها.
                if ($table === 'crm_technician_devices') {
                    $t->unsignedTinyInteger('priority')->default(0);
                }
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

        DB::table('crm_cities')->insert([['id' => 1, 'name' => 'تهران', 'parent_city_id' => null, 'is_active' => true]]);
        DB::table('crm_devices')->insert(
            collect(range(1, 5))->map(fn ($i) => ['id' => $i, 'name' => 'دستگاه '.$i])->all()
        );
        DB::table('crm_brands')->insert([['id' => 1, 'name' => 'برند ۱']]);

        $this->mock(\Modules\CRM\Services\OrderSmsNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->andReturnNull();
        });

        AssignmentSettings::save(['assign_mode' => 'auto', 'assign_min_score' => 0]);
    }

    // ───────────────────────── helpers

    private function technician(string $name, array $devices, array $attributes = []): Technician
    {
        $t = Technician::forceCreate(array_merge([
            'first_name' => $name,
            'firstname_tech' => $name,
            'status' => 'active',
            'ready_for_delivery' => true,
            'exclude_from_suggestions' => false,
            'wallet_balance' => 0,
            'service_types' => ['repair'],
        ], $attributes));

        foreach ($devices as $id) {
            DB::table('crm_technician_devices')->insert(['technician_id' => $t->id, 'device_id' => $id]);
        }

        return $t;
    }

    /** سفارشِ گذشتهٔ انجام‌شده. */
    private function pastOrder(Technician $tech, int $deviceId, int $monthsAgo, string $status = 'completed'): Order
    {
        $at = now()->subMonths($monthsAgo);

        $order = Order::create([
            'order_code' => 'OLD-'.uniqid(),
            'customer_id' => 1,
            'customer_mobile' => '09120000000',
            'device_id' => $deviceId,
            'city_id' => 1,
            'technician_id' => $tech->id,
            'status' => $status,
            'order_type' => 'repair',
            'is_lead' => false,
            'completed_at' => $at,
        ]);
        $order->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        return $order;
    }

    /** سفارشِ جدیدِ بدون تکنسین، آمادهٔ پخش. */
    private function newOrder(int $deviceId, array $attributes = []): Order
    {
        static $seq = 0;
        $seq++;

        $order = Order::create(array_merge([
            'order_code' => 'NEW-'.$seq,
            'customer_id' => 1,
            'customer_mobile' => '09120000000',
            'device_id' => $deviceId,
            'city_id' => 1,
            'address' => 'تهران خیابان ولیعصر پلاک ۱۲',
            'order_type' => 'repair',
            'status' => 'new',
            'is_lead' => false,
        ], $attributes));
        $order->forceFill(['created_at' => now()->subHour()])->save();

        return $order;
    }

    // ───────────────────────── تشخیص سابقه

    public function test_previous_technician_of_the_same_device_is_detected(): void
    {
        $tech = $this->technician('قدیمی', [1]);
        $old = $this->pastOrder($tech, 1, 4);
        $new = $this->newOrder(1);

        $hit = app(TechnicianHistoryService::class)->previousTechnicianFor($new);

        $this->assertNotNull($hit);
        $this->assertSame($tech->id, $hit['technician_id']);
        $this->assertSame($old->id, $hit['order_id']);
        $this->assertSame(4, $hit['months']);
    }

    public function test_history_outside_the_window_is_ignored(): void
    {
        $tech = $this->technician('خیلی‌قدیمی', [1]);
        $this->pastOrder($tech, 1, 9);

        $this->assertNull(
            app(TechnicianHistoryService::class)->previousTechnicianFor($this->newOrder(1))
        );
    }

    public function test_history_of_a_different_device_does_not_count(): void
    {
        $tech = $this->technician('یخچالی', [1, 2]);
        $this->pastOrder($tech, 2, 2);

        $this->assertNull(
            app(TechnicianHistoryService::class)->previousTechnicianFor($this->newOrder(1))
        );
    }

    public function test_unfinished_past_order_does_not_count(): void
    {
        $tech = $this->technician('ناتمام', [1]);
        $this->pastOrder($tech, 1, 2, status: 'coordinated');

        $this->assertNull(
            app(TechnicianHistoryService::class)->previousTechnicianFor($this->newOrder(1))
        );
    }

    public function test_warranty_return_on_that_device_disables_the_rule(): void
    {
        $tech = $this->technician('برگشتی', [1]);
        $this->pastOrder($tech, 1, 4);            // تعمیر موفق
        $this->pastOrder($tech, 1, 2, status: 'returned'); // ولی بعداً برگشتی گارانتی

        $this->assertNull(
            app(TechnicianHistoryService::class)->previousTechnicianFor($this->newOrder(1))
        );
    }

    public function test_inactive_technician_is_dropped(): void
    {
        $tech = $this->technician('بازنشسته', [1], ['status' => 'inactive']);
        $this->pastOrder($tech, 1, 3);

        $this->assertNull(
            app(TechnicianHistoryService::class)->previousTechnicianFor($this->newOrder(1))
        );
    }

    public function test_guest_orders_are_matched_by_mobile(): void
    {
        $tech = $this->technician('مهمان‌شناس', [1]);
        $old = $this->pastOrder($tech, 1, 2);
        $old->forceFill(['customer_id' => null])->save();

        $new = $this->newOrder(1, ['customer_id' => null]);

        $hit = app(TechnicianHistoryService::class)->previousTechnicianFor($new);

        $this->assertNotNull($hit);
        $this->assertSame($tech->id, $hit['technician_id']);
    }

    public function test_rule_can_be_switched_off(): void
    {
        AssignmentSettings::save(['assign_history_enabled' => 0]);

        $tech = $this->technician('قدیمی', [1]);
        $this->pastOrder($tech, 1, 2);

        $this->assertNull(
            app(TechnicianHistoryService::class)->previousTechnicianFor($this->newOrder(1))
        );
    }

    // ───────────────────────── اثر روی نقشهٔ تخصیص

    public function test_previous_technician_wins_over_a_higher_scoring_stranger(): void
    {
        // تکنسین سابق بدهکار است و امتیاز کمتری دارد، ولی باید برنده شود.
        $previous = $this->technician('سابق', [1], ['wallet_balance' => -4_000_000]);
        $this->pastOrder($previous, 1, 3);
        $stranger = $this->technician('غریبه', [1]);

        $new = $this->newOrder(1);
        $plan = app(TechnicianGroupPlanner::class)->plan(collect([$new]));

        $this->assertSame($previous->id, $plan->steps->first()->technician->id);
        $this->assertNotNull($plan->steps->first()->history);
        $this->assertNotSame($stranger->id, $plan->steps->first()->technician->id);
    }

    public function test_previous_technician_is_skipped_when_capacity_is_full(): void
    {
        $previous = $this->technician('سابقِ پر', [1], ['max_order' => 1]);
        $this->pastOrder($previous, 1, 3);
        // یک سفارش باز دارد → ظرفیتش تمام است
        $this->newOrder(1, ['technician_id' => $previous->id, 'status' => 'coordinated']);

        $backup = $this->technician('جایگزین', [1]);

        $plan = app(TechnicianGroupPlanner::class)->plan(collect([$this->newOrder(1)]));

        $this->assertSame($backup->id, $plan->steps->first()->technician->id);
        $this->assertNull($plan->steps->first()->history);
    }

    public function test_previous_technician_is_skipped_when_not_ready_for_delivery(): void
    {
        $previous = $this->technician('مرخصی', [1], ['ready_for_delivery' => false]);
        $this->pastOrder($previous, 1, 3);
        $backup = $this->technician('جایگزین', [1]);

        $plan = app(TechnicianGroupPlanner::class)->plan(collect([$this->newOrder(1)]));

        $this->assertSame($backup->id, $plan->steps->first()->technician->id);
    }

    public function test_reducing_visits_still_beats_history_in_a_multi_order_group(): void
    {
        // سابقِ لباسشویی فقط همان یکی را پوشش می‌دهد؛ نفرِ دیگر هر سه را.
        $previous = $this->technician('سابق', [1]);
        $this->pastOrder($previous, 1, 3);
        $wide = $this->technician('همه‌کاره', [1, 2, 3]);

        $orders = collect([$this->newOrder(1), $this->newOrder(2), $this->newOrder(3)]);
        $plan = app(TechnicianGroupPlanner::class)->plan($orders);

        $this->assertCount(1, $plan->steps);
        $this->assertSame($wide->id, $plan->steps->first()->technician->id);
    }

    // ───────────────────────── پخش خودکار و لاگ

    public function test_auto_assign_uses_history_mode_and_explains_it(): void
    {
        $previous = $this->technician('سابق', [1]);
        $old = $this->pastOrder($previous, 1, 3);
        $new = $this->newOrder(1);

        $stats = app(AutoAssignService::class)->run();

        $this->assertSame(1, $stats['assigned']);
        $this->assertSame($previous->id, $new->fresh()->technician_id);

        $log = OrderAssignmentLog::where('order_id', $new->id)->first();
        $this->assertSame('history', $log->mode);
        $this->assertStringContainsString('همین دستگاه را قبلاً برای این مشتری تعمیر کرده', $log->note);
        $this->assertStringContainsString($old->order_code, $log->note);
    }

    public function test_history_match_is_exempt_from_the_minimum_score_gate(): void
    {
        AssignmentSettings::save(['assign_min_score' => 95]);

        $previous = $this->technician('سابقِ کم‌امتیاز', [1], ['wallet_balance' => -5_000_000]);
        $this->pastOrder($previous, 1, 3);
        $new = $this->newOrder(1);

        $stats = app(AutoAssignService::class)->run();

        $this->assertSame(1, $stats['assigned']);
        $this->assertSame($previous->id, $new->fresh()->technician_id);
    }
}
