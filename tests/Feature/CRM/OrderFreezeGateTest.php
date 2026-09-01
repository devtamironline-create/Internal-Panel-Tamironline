<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * سفارشِ «فریز/قفل‌شده» توسطِ ادمین نباید هیچ تغییرِ سمتِ تکنسین را بپذیرد
 * (۴۲۳)؛ خواندن آزاد است.
 */
class OrderFreezeGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status')->nullable();
            $t->timestamp('visit_scheduled_at')->nullable();
            $t->unsignedTinyInteger('visit_reschedule_count')->default(0);
            $t->timestamp('status_changed_at')->nullable();
            $t->boolean('is_locked')->default(false);
            $t->string('lock_reason', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('crm_order_status_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->text('note')->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->string('actor_name')->nullable();
            $t->string('actor_role')->nullable();
            $t->unsignedBigInteger('actor_technician_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    private function tech(): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'تست', 'mobile' => '09120000000', 'status' => 'active', 'user_id' => 1,
        ]);
    }

    public function test_a_frozen_order_blocks_visit_scheduling_with_423(): void
    {
        $tech = $this->tech();
        $order = Order::forceCreate([
            'order_code' => 'FZ-1', 'technician_id' => $tech->id,
            'status' => OrderStatus::New->value, 'status_changed_at' => now(),
            'is_locked' => true, 'lock_reason' => 'بررسیِ مالی',
        ]);

        $request = Request::create('/v1/technician/orders/'.$order->id.'/schedule-visit', 'POST', [
            'visit_date' => now()->addDay()->format('Y-m-d'), 'visit_slot' => 1,
        ]);
        $request->setUserResolver(fn () => $tech);

        try {
            app(OrderActionController::class)->scheduleVisit($request, $order->id);
            $this->fail('سفارشِ قفل‌شده نباید زمان‌بندی را بپذیرد.');
        } catch (HttpException $e) {
            $this->assertSame(423, $e->getStatusCode());
        }
    }

    public function test_an_unlocked_order_is_not_blocked_by_the_freeze_gate(): void
    {
        $tech = $this->tech();
        $order = Order::forceCreate([
            'order_code' => 'FZ-2', 'technician_id' => $tech->id,
            'status' => OrderStatus::New->value, 'status_changed_at' => now(),
            'is_locked' => false,
        ]);

        $request = Request::create('/v1/technician/orders/'.$order->id.'/schedule-visit', 'POST', [
            'visit_date' => now()->addDay()->format('Y-m-d'), 'visit_slot' => 1,
        ]);
        $request->setUserResolver(fn () => $tech);

        $res = app(OrderActionController::class)->scheduleVisit($request, $order->id);
        $this->assertTrue($res->getData(true)['success']);
    }
}
