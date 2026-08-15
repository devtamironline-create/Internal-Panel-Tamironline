<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\OrderStatusLog;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * POST /v1/technician/orders/{id}/return-review — تصمیمِ تکنسین دربارهٔ
 * سفارشِ برگشتی.
 *
 * قواعد قفل‌شده:
 *   - تأیید (ایراد از خدمات قبلی): return_type=1 (ادامهٔ رایگان)، days اجباری
 *   - رد (ایراد جدید): سفارش عادی ادامه می‌یابد، return_type خالی
 *   - idempotent: ثبتِ دوباره روی بررسی‌شده → 200 بدون اثرِ مجدد (کلاینت
 *     بعد از خطای شبکه دوباره تلاش می‌کند)
 *
 * کنترلر مستقیم صدا زده می‌شود؛ زنجیرهٔ auth موضوعِ این تست نیست.
 */
class TechReturnReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->timestamp('status_changed_at')->nullable();
            $t->string('return_type')->nullable();
            $t->boolean('return_review_pending')->default(false);
            $t->timestamp('return_reviewed_at')->nullable();
            $t->boolean('return_review_approved')->nullable();
            $t->unsignedTinyInteger('return_review_days')->nullable();
            $t->boolean('is_lead')->default(false);
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

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        // هوکِ actor لاگِ وضعیت به جدول users سر می‌زند.
        Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);
        OrderStatusLog::flushActorCache();
    }

    private function technician(): Technician
    {
        return Technician::forceCreate(['first_name' => 'تکنسین', 'status' => 'active']);
    }

    private function pendingOrder(Technician $tech): Order
    {
        return Order::forceCreate([
            'order_code' => 'RR-'.uniqid(),
            'technician_id' => $tech->id,
            'status' => 'new',
            'return_review_pending' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function review(Order $order, Technician $tech, array $payload): array
    {
        $request = Request::create('/v1/technician/orders/'.$order->id.'/return-review', 'POST', $payload);
        $request->setUserResolver(fn () => $tech);

        return app(OrderActionController::class)->returnReview($request, $order->id)->getData(true);
    }

    public function test_approving_sets_the_free_redo_path(): void
    {
        $tech = $this->technician();
        $order = $this->pendingOrder($tech);

        $json = $this->review($order, $tech, ['approved' => '1', 'days' => 3, 'note' => 'پنل تصویر دوباره ایراد داد']);

        $this->assertTrue($json['success']);
        $order->refresh();
        $this->assertFalse((bool) $order->return_review_pending);
        $this->assertTrue((bool) $order->return_review_approved);
        $this->assertSame(3, (int) $order->return_review_days);
        $this->assertSame(1, (int) $order->return_type);
        $this->assertNotNull($order->return_reviewed_at);
        $this->assertStringContainsString('بررسی برگشتی: تأیید', (string) OrderStatusLog::latest('id')->first()->note);
    }

    public function test_rejecting_lets_the_order_continue_as_a_normal_one(): void
    {
        $tech = $this->technician();
        $order = $this->pendingOrder($tech);

        $json = $this->review($order, $tech, ['approved' => '0']);

        $this->assertTrue($json['success']);
        $order->refresh();
        $this->assertFalse((bool) $order->return_review_pending);
        $this->assertFalse((bool) $order->return_review_approved);
        $this->assertNull($order->return_type);
        $this->assertNull($order->return_review_days);
    }

    /** کلاینت بعد از خطای شبکه دوباره POST می‌زند — نباید اثرِ دوباره بگذارد. */
    public function test_a_repeat_submission_is_idempotent(): void
    {
        $tech = $this->technician();
        $order = $this->pendingOrder($tech);

        $this->review($order, $tech, ['approved' => '1', 'days' => 3]);
        $firstReviewedAt = $order->fresh()->return_reviewed_at;

        // تلاشِ دوم — حتی با payload متفاوت — چیزی را عوض نمی‌کند.
        $json = $this->review($order, $tech, ['approved' => '0']);

        $this->assertTrue($json['success']);
        $this->assertTrue($json['data']['approved']);
        $this->assertSame(3, $json['data']['days']);
        $order->refresh();
        $this->assertTrue((bool) $order->return_review_approved);
        $this->assertTrue($firstReviewedAt->equalTo($order->return_reviewed_at));
        $this->assertSame(1, OrderStatusLog::count());
    }

    public function test_an_order_that_is_not_pending_is_rejected_cleanly(): void
    {
        $tech = $this->technician();
        $order = Order::forceCreate([
            'order_code' => 'RR-NP', 'technician_id' => $tech->id,
            'status' => 'new', 'return_review_pending' => false,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->review($order, $tech, ['approved' => '1', 'days' => 2]);
    }

    public function test_approving_without_days_is_a_validation_error(): void
    {
        $tech = $this->technician();
        $order = $this->pendingOrder($tech);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->review($order, $tech, ['approved' => '1']);
    }

    public function test_another_technicians_order_is_forbidden(): void
    {
        $mine = $this->technician();
        $other = $this->technician();
        $order = $this->pendingOrder($other);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->review($order, $mine, ['approved' => '1', 'days' => 2]);
    }
}
