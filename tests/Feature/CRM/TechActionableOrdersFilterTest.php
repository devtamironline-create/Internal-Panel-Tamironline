<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderController;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * فیلترِ ?actionable=1 روی GET /v1/technician/orders.
 *
 * قاعدهٔ قفل‌شده: مجموعهٔ «تصمیم‌خواه» باید دقیقاً همان مجموعه‌ای باشد که
 * actionable_count در /me/sync می‌شمارد (مرجع مشترک: settledValues) —
 * وگرنه گاردِ SLA اپ و عددِ بج از هم جدا می‌افتند؛ و سقفِ صفحه ۵۰ است تا
 * سفارش‌های مهلت‌گذشتهٔ صفحه‌های بعدی هم دیده شوند.
 *
 * کنترلر مستقیم صدا زده می‌شود؛ زنجیرهٔ auth موضوعِ این تست نیست.
 */
class TechActionableOrdersFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('customer_name')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('visit_scheduled_at')->nullable();
            $t->date('estimated_ready_at')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->timestamps();
        });

        Schema::create('crm_customers', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('first_name')->nullable(), $x->string('last_name')->nullable(), $x->string('mobile')->nullable()]));
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('name')->nullable()]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('name')->nullable(), $x->string('slug')->nullable(), $x->string('icon')->nullable(), $x->string('thumbnail')->nullable()]));
    }

    private function technician(): Technician
    {
        return Technician::forceCreate(['first_name' => 'تکنسین', 'status' => 'active']);
    }

    private function order(Technician $tech, string $status, string $code): Order
    {
        return Order::forceCreate([
            'order_code' => $code,
            'technician_id' => $tech->id,
            'status' => $status,
            'status_changed_at' => now()->subDays(40),   // قدیمی — که در لیستِ پیش‌فرض عقب بیفتد
        ]);
    }

    /** @return array<string, mixed> */
    private function list(Technician $tech, array $params = []): array
    {
        $request = Request::create('/v1/technician/orders', 'GET', $params);
        $request->setUserResolver(fn () => $tech);

        return app(OrderController::class)->index($request)->getData(true);
    }

    public function test_actionable_returns_exactly_the_undecided_set_with_a_50_cap(): void
    {
        $tech = $this->technician();

        $this->order($tech, 'new', 'A-NEW');
        $this->order($tech, 'coordinated', 'A-COORD');
        $this->order($tech, 'awaiting_part', 'A-PART');
        // تسویه‌شده‌ها — نباید بیایند:
        $this->order($tech, 'completed', 'S-DONE');
        $this->order($tech, 'transit', 'S-TRANSIT');
        $this->order($tech, 'cancelled', 'S-CANCEL');

        $json = $this->list($tech, ['actionable' => 1]);
        $codes = array_column($json['data'], 'order_code');

        sort($codes);
        $this->assertSame(['A-COORD', 'A-NEW', 'A-PART'], $codes);
        $this->assertSame(50, $json['meta']['per_page']);
    }

    public function test_the_default_listing_is_untouched(): void
    {
        $tech = $this->technician();
        $this->order($tech, 'completed', 'S-DONE');

        $json = $this->list($tech);

        $this->assertSame(15, $json['meta']['per_page']);
        $this->assertContains('S-DONE', array_column($json['data'], 'order_code'));
    }
}
