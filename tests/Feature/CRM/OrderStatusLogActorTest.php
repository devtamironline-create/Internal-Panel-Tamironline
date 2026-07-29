<?php

namespace Tests\Feature\CRM;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\OrderAssigner;
use Tests\TestCase;

/**
 * نامِ عاملِ هر رویدادِ تاریخچه باید در لحظهٔ ثبت snapshot شود، تا اگر
 * سفارش بعداً به تکنسین دیگری داده شد باز هم معلوم باشد کارِ قبلی را چه
 * کسی انجام داده.
 */
class OrderStatusLogActorTest extends TestCase
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

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->unsignedBigInteger('wp_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('status', 20)->default('active');
            // نوبتِ چرخشِ پخش خودکار.
            $t->timestamp('last_assigned_at')->nullable();
            $t->boolean('ready_for_delivery')->default(true);
            $t->boolean('exclude_from_suggestions')->default(false);
            $t->bigInteger('wallet_balance')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->unsignedBigInteger('technician_wp_id')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
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

        OrderStatusLog::flushActorCache();
    }

    private function order(): Order
    {
        return Order::create(['order_code' => 'A-1', 'status' => 'new', 'is_lead' => false]);
    }

    private function log(Order $order, ?int $changedBy, array $extra = []): OrderStatusLog
    {
        return OrderStatusLog::create(array_merge([
            'order_id' => $order->id,
            'from_status' => 'new',
            'to_status' => 'coordinated',
            'note' => 'تست',
            'changed_by' => $changedBy,
            'created_at' => now(),
        ], $extra));
    }

    public function test_technician_action_stores_the_technician_name_and_role(): void
    {
        $user = User::create([
            'first_name' => 'علی', 'last_name' => 'رضایی',
            'mobile' => '09120000001', 'password' => bcrypt('x'),
        ]);
        $tech = Technician::forceCreate([
            'user_id' => $user->id, 'first_name' => 'علی رضایی',
            'firstname_tech' => 'علی رضایی', 'status' => 'active',
        ]);

        $log = $this->log($this->order(), $user->id);

        $this->assertSame('علی رضایی', $log->actor_name);
        $this->assertSame('technician', $log->actor_role);
        $this->assertSame($tech->id, $log->actor_technician_id);
        $this->assertSame('تکنسین', $log->actorRoleLabel());
    }

    public function test_someone_who_is_both_operator_and_technician_is_labelled_operator(): void
    {
        $user = User::create([
            'first_name' => 'سعید', 'last_name' => 'نوری',
            'mobile' => '09120000004', 'password' => bcrypt('x'),
            'is_staff' => true,
        ]);
        Technician::forceCreate([
            'user_id' => $user->id, 'first_name' => 'سعید تکنسین',
            'firstname_tech' => 'سعید تکنسین', 'status' => 'active',
        ]);

        $log = $this->log($this->order(), $user->id);

        $this->assertSame('operator', $log->actor_role);
        $this->assertSame('اپراتور', $log->actorRoleLabel());
        // نامِ کاربر باید بیاید، نه نامِ پروفایلِ تکنسین.
        $this->assertSame('سعید نوری', $log->actor_name);
        // در نقشِ اپراتور عمل کرده، پس به رکوردِ تکنسین نسبت داده نمی‌شود.
        $this->assertNull($log->actor_technician_id);
        $this->assertFalse($log->isByTechnician());
    }

    public function test_pure_technician_is_still_labelled_technician(): void
    {
        $user = User::create([
            'first_name' => 'رضا', 'last_name' => 'احمدی',
            'mobile' => '09120000005', 'password' => bcrypt('x'),
            'is_staff' => false,
        ]);
        $tech = Technician::forceCreate([
            'user_id' => $user->id, 'first_name' => 'رضا احمدی',
            'firstname_tech' => 'رضا احمدی', 'status' => 'active',
        ]);

        $log = $this->log($this->order(), $user->id);

        $this->assertSame('technician', $log->actor_role);
        $this->assertSame($tech->id, $log->actor_technician_id);
    }

    public function test_staff_acting_from_the_technician_guard_is_still_operator(): void
    {
        $user = User::create([
            'first_name' => 'نازنین', 'last_name' => 'شریفی',
            'mobile' => '09120000006', 'password' => bcrypt('x'),
            'is_staff' => true,
        ]);
        $tech = Technician::forceCreate([
            'user_id' => $user->id, 'first_name' => 'نازنین تکنسین',
            'firstname_tech' => 'نازنین تکنسین', 'status' => 'active',
        ]);
        Auth::guard('tech')->setUser($tech);

        $log = $this->log($this->order(), null);

        $this->assertSame('operator', $log->actor_role);
        $this->assertSame('نازنین شریفی', $log->actor_name);
    }

    public function test_operator_action_stores_the_user_name(): void
    {
        $user = User::create([
            'first_name' => 'مریم', 'last_name' => 'کریمی',
            'mobile' => '09120000002', 'password' => bcrypt('x'),
        ]);

        $log = $this->log($this->order(), $user->id);

        $this->assertSame('مریم کریمی', $log->actor_name);
        $this->assertSame('operator', $log->actor_role);
        $this->assertNull($log->actor_technician_id);
    }

    public function test_actions_without_an_actor_are_recorded_as_system(): void
    {
        $log = $this->log($this->order(), null);

        $this->assertSame('سیستم', $log->actor_name);
        $this->assertSame('system', $log->actor_role);
    }

    public function test_logged_in_technician_is_detected_without_changed_by(): void
    {
        $tech = Technician::forceCreate([
            'first_name' => 'حسن', 'firstname_tech' => 'حسن مرادی', 'status' => 'active',
        ]);
        Auth::guard('tech')->setUser($tech);

        $log = $this->log($this->order(), null);

        $this->assertSame('حسن مرادی', $log->actor_name);
        $this->assertSame('technician', $log->actor_role);
        $this->assertSame($tech->id, $log->actor_technician_id);
    }

    public function test_explicit_actor_name_is_not_overwritten(): void
    {
        $log = $this->log($this->order(), null, [
            'actor_name' => 'سامانهٔ ووکامرس',
            'actor_role' => 'system',
        ]);

        $this->assertSame('سامانهٔ ووکامرس', $log->actor_name);
    }

    public function test_assignment_writes_the_technician_name_into_the_order_history(): void
    {
        $this->mock(\Modules\CRM\Services\OrderSmsNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->andReturnNull();
        });

        $tech = Technician::forceCreate([
            'first_name' => 'کاظم', 'firstname_tech' => 'کاظم صادقی', 'status' => 'active',
        ]);
        $order = $this->order();

        app(OrderAssigner::class)->assign($order, $tech, 'manual', null);

        $log = OrderStatusLog::where('order_id', $order->id)->latest('id')->first();
        $this->assertStringContainsString('تخصیص تکنسین', $log->note);
        $this->assertStringContainsString('کاظم صادقی', $log->note);
    }

    public function test_reassignment_names_both_technicians(): void
    {
        $this->mock(\Modules\CRM\Services\OrderSmsNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->andReturnNull();
        });

        $first = Technician::forceCreate([
            'first_name' => 'اول', 'firstname_tech' => 'تکنسین اول', 'status' => 'active',
        ]);
        $second = Technician::forceCreate([
            'first_name' => 'دوم', 'firstname_tech' => 'تکنسین دوم', 'status' => 'active',
        ]);

        $order = $this->order();
        $assigner = app(OrderAssigner::class);
        $assigner->assign($order, $first, 'manual', null);
        $assigner->assign($order->fresh(), $second, 'auto', null);

        $log = OrderStatusLog::where('order_id', $order->id)->latest('id')->first();
        $this->assertStringContainsString('تغییر تکنسین', $log->note);
        $this->assertStringContainsString('تکنسین اول', $log->note);
        $this->assertStringContainsString('تکنسین دوم', $log->note);
        // حالتِ غیردستی باید صریح در تاریخچه بیاید.
        $this->assertStringContainsString('پخش خودکار', $log->note);
    }

    public function test_unassignment_names_the_removed_technician(): void
    {
        $this->mock(\Modules\CRM\Services\OrderSmsNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->andReturnNull();
        });

        $tech = Technician::forceCreate([
            'first_name' => 'پویا', 'firstname_tech' => 'پویا کریمی', 'status' => 'active',
        ]);
        $order = $this->order();
        $assigner = app(OrderAssigner::class);
        $assigner->assign($order, $tech, 'manual', null);
        $assigner->unassign($order->fresh(), null);

        $log = OrderStatusLog::where('order_id', $order->id)->latest('id')->first();
        $this->assertStringContainsString('لغو تخصیص تکنسین', $log->note);
        $this->assertStringContainsString('پویا کریمی', $log->note);
    }

    public function test_history_keeps_the_old_technician_after_reassignment(): void
    {
        $this->mock(\Modules\CRM\Services\OrderSmsNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->andReturnNull();
        });

        $firstUser = User::create([
            'first_name' => 'اول', 'last_name' => 'تکنسین',
            'mobile' => '09120000003', 'password' => bcrypt('x'),
        ]);
        $first = Technician::forceCreate([
            'user_id' => $firstUser->id, 'first_name' => 'تکنسین اول',
            'firstname_tech' => 'تکنسین اول', 'status' => 'active',
        ]);
        $second = Technician::forceCreate([
            'first_name' => 'تکنسین دوم', 'firstname_tech' => 'تکنسین دوم', 'status' => 'active',
        ]);

        $order = $this->order();
        $order->forceFill(['technician_id' => $first->id])->save();

        // تکنسین اول یک کاری روی سفارش انجام می‌دهد.
        $this->log($order, $firstUser->id, ['note' => 'قطعه سفارش داده شد']);

        // بعداً سفارش به تکنسین دوم منتقل می‌شود.
        app(OrderAssigner::class)->assign($order->fresh(), $second, 'manual', null);

        $logs = OrderStatusLog::where('order_id', $order->id)->orderBy('id')->get();
        $oldest = $logs->first();

        $this->assertSame('تکنسین اول', $oldest->actor_name);
        $this->assertSame($first->id, $oldest->actor_technician_id);
        $this->assertSame($second->id, $order->fresh()->technician_id);
        // رکورد قدیمی همچنان به تکنسینِ قبلی اشاره می‌کند، نه تکنسینِ فعلی.
        $this->assertNotSame($order->fresh()->technician_id, $oldest->actor_technician_id);
    }
}
