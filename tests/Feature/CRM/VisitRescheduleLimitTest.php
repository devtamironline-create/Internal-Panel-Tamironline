<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * تکنسین حداکثر Order::VISIT_RESCHEDULE_LIMIT بار می‌تواند «زمانِ مراجعه» را
 * تغییر دهد؛ بارِ اول شمرده نمی‌شود و پس از سقف، تنظیمِ مجدد قفل (۴۲۳) می‌شود.
 */
class VisitRescheduleLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('last_name')->nullable();
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

    private function schedule(Order $order, Technician $tech, string $date, int $slot = 1)
    {
        $request = Request::create('/v1/technician/orders/'.$order->id.'/schedule-visit', 'POST', [
            'visit_date' => $date, 'visit_slot' => $slot,
        ]);
        $request->setUserResolver(fn () => $tech);

        return app(OrderActionController::class)->scheduleVisit($request, $order->id);
    }

    public function test_first_set_is_free_and_two_changes_are_allowed_then_locked(): void
    {
        $tech = $this->tech();
        $order = Order::forceCreate([
            'order_code' => 'VR-1', 'technician_id' => $tech->id,
            'status' => OrderStatus::New->value, 'status_changed_at' => now(),
        ]);

        // بارِ اول (ثبتِ اولیه) — شمرده نمی‌شود.
        $this->schedule($order, $tech, now()->addDay()->format('Y-m-d'));
        $this->assertSame(0, (int) $order->fresh()->visit_reschedule_count);

        // تغییرِ اول و دوم — مجاز.
        $this->schedule($order, $tech, now()->addDays(2)->format('Y-m-d'));
        $this->assertSame(1, (int) $order->fresh()->visit_reschedule_count);

        $this->schedule($order, $tech, now()->addDays(3)->format('Y-m-d'));
        $this->assertSame(2, (int) $order->fresh()->visit_reschedule_count);

        // تغییرِ سوم — قفل (۴۲۳) و شمارنده دست‌نخورده.
        $res = $this->schedule($order, $tech, now()->addDays(4)->format('Y-m-d'));
        $this->assertSame(423, $res->getStatusCode());
        $this->assertSame(2, (int) $order->fresh()->visit_reschedule_count);
    }

    public function test_admin_reset_reopens_rescheduling(): void
    {
        $tech = $this->tech();
        $order = Order::forceCreate([
            'order_code' => 'VR-2', 'technician_id' => $tech->id,
            'status' => OrderStatus::Coordinated->value, 'status_changed_at' => now(),
            'visit_scheduled_at' => now()->addDay(), 'visit_reschedule_count' => 2,
        ]);

        // قفل است.
        $this->assertSame(423, $this->schedule($order, $tech, now()->addDays(4)->format('Y-m-d'))->getStatusCode());

        // ادمین صفر می‌کند.
        $order->update(['visit_reschedule_count' => 0]);

        // حالا تغییر دوباره ممکن است و شمارنده از ۱ شروع می‌شود.
        $this->schedule($order, $tech, now()->addDays(5)->format('Y-m-d'));
        $this->assertSame(1, (int) $order->fresh()->visit_reschedule_count);
    }
}
