<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Support\SlaPolicy;
use Tests\TestCase;

/**
 * سقفِ انتخابِ «زمانِ مراجعه»: سفارشِ عادی ۵ روز، سفارشِ بازگشتی ۳ روز.
 */
class VisitDateCapTest extends TestCase
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
            $t->unsignedTinyInteger('return_type')->nullable();
            $t->boolean('return_review_pending')->default(false);
            $t->boolean('is_locked')->default(false);
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

    private function schedule(Order $order, Technician $tech, string $date)
    {
        $request = Request::create('/v1/technician/orders/'.$order->id.'/schedule-visit', 'POST', [
            'visit_date' => $date, 'visit_slot' => 1,
        ]);
        $request->setUserResolver(fn () => $tech);

        return app(OrderActionController::class)->scheduleVisit($request, $order->id);
    }

    public function test_normal_order_accepts_up_to_five_days_and_rejects_beyond(): void
    {
        $tech = $this->tech();
        $order = Order::forceCreate([
            'order_code' => 'VD-1', 'technician_id' => $tech->id,
            'status' => OrderStatus::New->value, 'status_changed_at' => now(),
        ]);

        $ok = $this->schedule($order, $tech, now()->addDays(SlaPolicy::MAX_VISIT_DAYS)->format('Y-m-d'));
        $this->assertTrue($ok->getData(true)['success']);

        $this->expectException(ValidationException::class);
        $this->schedule($order, $tech, now()->addDays(SlaPolicy::MAX_VISIT_DAYS + 1)->format('Y-m-d'));
    }

    public function test_returned_order_is_capped_at_three_days(): void
    {
        $tech = $this->tech();
        $order = Order::forceCreate([
            'order_code' => 'VD-2', 'technician_id' => $tech->id,
            'status' => OrderStatus::New->value, 'status_changed_at' => now(),
            'return_type' => 1, 'return_review_pending' => true,
        ]);

        $ok = $this->schedule($order, $tech, now()->addDays(SlaPolicy::MAX_RETURN_VISIT_DAYS)->format('Y-m-d'));
        $this->assertTrue($ok->getData(true)['success']);

        $this->expectException(ValidationException::class);
        $this->schedule($order, $tech, now()->addDays(SlaPolicy::MAX_RETURN_VISIT_DAYS + 1)->format('Y-m-d'));
    }
}
