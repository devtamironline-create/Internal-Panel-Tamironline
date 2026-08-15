<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Http\Controllers\OrderController as AdminOrderController;
use Modules\CRM\Http\Controllers\Tech\DashboardController;
use Modules\CRM\Http\Resources\TechOrderDetailResource;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * فلوی حرفه‌ایِ برگشتی — قواعد قفل‌شده:
 *
 *   ۱) کارشناسیِ اپراتور (approveReturn) سفارش را «هماهنگ شده» با
 *      return_review_pending=true به همان تکنسین برمی‌گرداند و فیلدهای
 *      کهنه (زمان مراجعهٔ سرویس قبلی، تخمین، نتیجهٔ بررسی قبلی، return_type)
 *      پاک می‌شوند — وگرنه SLA از لحظهٔ گذشته حساب می‌شد و اپ قفل می‌شد.
 *   ۲) تا وقتی بررسی ثبت نشده: هماهنگی/مراجعه آزاد است ولی بستنِ سفارش
 *      (هر وضعیتِ نهایی) هم در لیست گذارها نیست و هم updateStatus آن را
 *      با پیامِ مشخص رد می‌کند.
 *   ۳) بعد از تأیید (return_type=1) تکمیلِ رایگان فاکتورِ قبلی را
 *      supersede نمی‌کند — سندِ مالیِ کارِ اول فعال می‌ماند.
 *   ۴) بعد از رد، سفارش عادی است؛ تکمیلِ جدید فاکتورِ جدید می‌سازد و
 *      فاکتورِ قبلی superseded ولی قابلِ دسترسی می‌ماند (نه ۴۰۴).
 *
 * کنترلرها مستقیم صدا زده می‌شوند؛ زنجیرهٔ auth موضوعِ این تست نیست.
 */
class ReturnFlowProfessionalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status', 20)->default('active');
            $t->bigInteger('wallet_balance')->default(0);
            $t->integer('percent')->nullable();
            $t->integer('tech_per_of_all')->nullable();
            $t->string('type_of_calc_tech', 20)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_technician_percent_changes', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->integer('percent')->nullable();
            $t->integer('tech_per_of_all')->nullable();
            $t->timestamp('effective_from')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_tech_wallet_transactions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->unsignedBigInteger('wp_id')->nullable();
            $t->string('type', 30);
            $t->bigInteger('amount')->default(0);
            $t->bigInteger('balance_after')->nullable();
            $t->string('note', 500)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_invoices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('wp_id')->nullable();
            $t->string('invoice_code', 40)->nullable();
            $t->string('public_token', 64)->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->bigInteger('total_amount')->default(0);
            $t->bigInteger('tech_share')->default(0);
            $t->bigInteger('company_share')->default(0);
            $t->string('calc_type', 30)->nullable();
            $t->integer('commission_percent')->default(0);
            $t->boolean('in_wallet')->default(false);
            $t->string('status', 20)->default('issued');
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
            $t->unsignedBigInteger('province_id')->nullable();
            $t->unsignedBigInteger('city_id')->nullable();
            $t->unsignedBigInteger('address_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('visit_scheduled_at')->nullable();
            $t->date('estimated_ready_at')->nullable();
            $t->string('qc_status', 20)->nullable();
            $t->tinyInteger('return_type')->nullable();
            $t->text('return_description')->nullable();
            $t->boolean('return_review_pending')->default(false);
            $t->timestamp('return_reviewed_at')->nullable();
            $t->boolean('return_review_approved')->nullable();
            $t->unsignedTinyInteger('return_review_days')->nullable();
            $t->boolean('is_locked')->default(false);
            $t->boolean('is_lead')->default(false);
            $t->boolean('save_as_draft')->default(false);
            $t->timestamp('completed_at')->nullable();
            $t->bigInteger('price_customer')->default(0);
            $t->bigInteger('cost_price')->default(0);
            $t->bigInteger('hire')->default(0);
            $t->bigInteger('transportation')->default(0);
            $t->bigInteger('discount')->default(0);
            $t->bigInteger('total_invoice')->default(0);
            $t->text('invoice_descripotion')->nullable();
            $t->json('piece_list')->nullable();
            $t->json('buy_price_list')->nullable();
            $t->json('customer_price_list')->nullable();
            $t->string('device_img1')->nullable();
            $t->json('wp_notes')->nullable();
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

        // جدول‌هایی که load() انتهای updateStatus لمس می‌کند — خالی کافی است.
        Schema::create('crm_customers', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('first_name')->nullable(), $x->string('last_name')->nullable()]));
        Schema::create('crm_brands', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('name')->nullable()]));
        Schema::create('crm_devices', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('name')->nullable()]));
        Schema::create('crm_provinces', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('name')->nullable()]));
        Schema::create('crm_cities', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('name')->nullable(), $x->unsignedBigInteger('province_id')->nullable()]));
        Schema::create('crm_customer_addresses', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->unsignedBigInteger('province_id')->nullable(), $x->unsignedBigInteger('city_id')->nullable(), $x->unsignedBigInteger('district_id')->nullable()]));
        Schema::create('crm_order_items', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->unsignedBigInteger('order_id')->nullable()]));
        Schema::create('crm_transfer_receipts', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->unsignedBigInteger('order_id')->nullable()]));
        Schema::create('crm_objections', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->string('title')->nullable()]));
        Schema::create('crm_order_objection', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->unsignedBigInteger('order_id')->nullable(), $x->unsignedBigInteger('objection_id')->nullable(), $x->integer('sort_order')->default(0)]));
        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
        });

        // هوکِ actor لاگِ وضعیت به جدول users سر می‌زند.
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);
        \Modules\CRM\Models\OrderStatusLog::flushActorCache();
    }

    // ───────────────────────── helpers

    private function technician(): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'تکنسین تست', 'mobile' => '09120000000',
            'status' => 'active', 'percent' => 30,
        ]);
    }

    /** سفارشِ برگشتیِ ارجاع‌شده — در انتظارِ بررسیِ تکنسین. */
    private function pendingReturnedOrder(Technician $tech, array $extra = []): Order
    {
        return Order::forceCreate(array_merge([
            'order_code' => 'RF-'.uniqid(),
            'technician_id' => $tech->id,
            'status' => OrderStatus::Coordinated->value,
            'status_changed_at' => now(),
            'return_review_pending' => true,
            'device_img1' => 'crm/orders/x/after.jpg',
        ], $extra));
    }

    private function callUpdateStatus(Order $order, Technician $tech, array $payload): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/v1/technician/orders/'.$order->id.'/status', 'POST', $payload);
        $request->setUserResolver(fn () => $tech);

        return app(OrderActionController::class)->updateStatus($request, $order->id);
    }

    private function callReturnReview(Order $order, Technician $tech, array $payload): void
    {
        $request = Request::create('/v1/technician/orders/'.$order->id.'/return-review', 'POST', $payload);
        $request->setUserResolver(fn () => $tech);

        app(OrderActionController::class)->returnReview($request, $order->id);
    }

    // ───────────────────────── ۱) کارشناسیِ اپراتور → ارجاع با پرچمِ بررسی

    public function test_operator_approval_reopens_the_order_with_a_fresh_review_round(): void
    {
        $tech = $this->technician();
        $order = Order::forceCreate([
            'order_code' => 'RF-APPROVE',
            'technician_id' => $tech->id,
            'status' => OrderStatus::Returned->value,
            // باقی‌ماندهٔ سرویسِ قبلی — همه باید پاک شوند.
            'visit_scheduled_at' => now()->subDays(10),
            'estimated_ready_at' => now()->subDays(3)->toDateString(),
            'return_type' => 1,
            'return_reviewed_at' => now()->subDays(9),
            'return_review_approved' => true,
            'return_review_days' => 5,
        ]);

        $request = Request::create('/admin/crm/orders/'.$order->id.'/return/approve', 'POST', ['note' => 'کارشناسی شد']);
        app(AdminOrderController::class)->approveReturn($request, $order);

        $order->refresh();
        $this->assertSame(OrderStatus::Coordinated, $order->status);
        $this->assertSame('approved', $order->qc_status);
        $this->assertTrue((bool) $order->return_review_pending);
        $this->assertNull($order->return_type);
        $this->assertNull($order->return_reviewed_at);
        $this->assertNull($order->return_review_days);
        $this->assertNull($order->visit_scheduled_at);
        $this->assertNull($order->estimated_ready_at);
    }

    public function test_operator_rejection_clears_any_pending_review(): void
    {
        $tech = $this->technician();
        $order = Order::forceCreate([
            'order_code' => 'RF-REJECT', 'technician_id' => $tech->id,
            'status' => OrderStatus::Returned->value, 'return_review_pending' => true,
        ]);

        $request = Request::create('/admin/crm/orders/'.$order->id.'/return/reject', 'POST', ['note' => 'برگشتی وارد نیست — ایراد مصرفی است']);
        app(AdminOrderController::class)->rejectReturn($request, $order);

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertFalse((bool) $order->return_review_pending);
    }

    // ───────────────────────── ۲) گیتِ بستن تا قبل از ثبتِ بررسی

    public function test_closing_is_blocked_while_the_review_is_pending(): void
    {
        $tech = $this->technician();
        $order = $this->pendingReturnedOrder($tech);

        try {
            $this->callUpdateStatus($order, $tech, [
                'status' => OrderStatus::Completed->value,
                'invoice_descripotion' => 'تعویض برد', 'price_customer' => 500_000,
            ]);
            $this->fail('بستنِ سفارشِ در انتظارِ بررسی نباید ممکن باشد.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('بررسی برگشتی', $e->errors()['status'][0]);
        }

        $this->assertSame(OrderStatus::Coordinated, $order->fresh()->status);
    }

    public function test_transit_close_is_blocked_too(): void
    {
        $tech = $this->technician();
        $order = $this->pendingReturnedOrder($tech);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->callUpdateStatus($order, $tech, [
            'status' => OrderStatus::Transit->value,
            'description' => 'مراجعه شد ولی تعمیری انجام نشد؛ فقط ایاب و ذهاب.',
        ]);
    }

    public function test_coordination_transitions_stay_open_but_final_ones_disappear(): void
    {
        $tech = $this->technician();
        $order = $this->pendingReturnedOrder($tech);

        $api = (new \ReflectionMethod(OrderActionController::class, 'allowedStatusesFor'))
            ->invoke(app(OrderActionController::class), $order);
        $pwa = (new \ReflectionMethod(DashboardController::class, 'allowedStatusesFor'))
            ->invoke(app(DashboardController::class), $order);
        $resource = array_column(
            (new \ReflectionMethod(TechOrderDetailResource::class, 'allowedTransitions'))
                ->invoke(new TechOrderDetailResource($order), $order->status),
            'value'
        );

        foreach ([$api, $pwa] as $list) {
            $this->assertNotEmpty($list);
            foreach ($list as $s) {
                $this->assertFalse($s->isFinal(), $s->value.' نباید در گذارهای سفارشِ در انتظارِ بررسی باشد.');
            }
        }
        $this->assertNotEmpty($resource);
        foreach ($resource as $value) {
            $this->assertFalse(OrderStatus::from($value)->isFinal());
        }
    }

    public function test_a_non_final_transition_still_works_while_pending(): void
    {
        $tech = $this->technician();
        $order = $this->pendingReturnedOrder($tech);

        $response = $this->callUpdateStatus($order, $tech, [
            'status' => OrderStatus::RepairStarted->value,
        ]);

        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame(OrderStatus::RepairStarted, $order->fresh()->status);
    }

    // ───────────────────────── ۳) تأیید → تکمیلِ رایگان، فاکتورِ قبلی فعال می‌ماند

    public function test_free_redo_completion_keeps_the_original_invoice_active(): void
    {
        $tech = $this->technician();
        $order = $this->pendingReturnedOrder($tech);
        $original = Invoice::forceCreate([
            'invoice_code' => 'INV-OLD-1', 'order_id' => $order->id,
            'technician_id' => $tech->id, 'total_amount' => 2_000_000,
            'company_share' => 600_000, 'status' => 'issued', 'issued_at' => now()->subDays(20),
        ]);

        $this->callReturnReview($order, $tech, ['approved' => '1', 'days' => 3]);
        $this->assertSame(1, (int) $order->fresh()->return_type);

        $response = $this->callUpdateStatus($order->fresh(), $tech, [
            'status' => OrderStatus::Completed->value,
            'price_customer' => 0,
        ]);

        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
        // فاکتورِ اصلی نه superseded شده نه فاکتورِ جدیدی ساخته شده.
        $this->assertNull($original->fresh()->superseded_at);
        $this->assertSame(1, Invoice::withSuperseded()->where('order_id', $order->id)->count());
    }

    // ───────────────────────── ۴) رد → فلوی عادی؛ فاکتورِ قبلی بایگانی ولی در دسترس

    public function test_rejected_review_completion_supersedes_but_never_loses_the_old_invoice(): void
    {
        $tech = $this->technician();
        $order = $this->pendingReturnedOrder($tech);
        $original = Invoice::forceCreate([
            'invoice_code' => 'INV-OLD-2', 'order_id' => $order->id,
            'technician_id' => $tech->id, 'total_amount' => 2_000_000,
            'company_share' => 600_000, 'status' => 'issued', 'issued_at' => now()->subDays(20),
        ]);

        $this->callReturnReview($order, $tech, ['approved' => '0', 'note' => 'ایراد جدید است']);
        $this->assertNull($order->fresh()->return_type);

        $response = $this->callUpdateStatus($order->fresh(), $tech, [
            'status' => OrderStatus::Completed->value,
            'price_customer' => 900_000,
            'invoice_descripotion' => 'تعویض قطعهٔ جدید — ایراد تازه',
        ]);

        $this->assertTrue($response->getData(true)['success']);
        // فاکتورِ قدیمی superseded شده ولی از DB و از صفحهٔ ادمین حذف نشده.
        $this->assertNotNull($original->fresh()->superseded_at);
        $this->assertSame(2, Invoice::withSuperseded()->where('order_id', $order->id)->count());
        $this->assertSame(1, Invoice::where('order_id', $order->id)->count());

        // صفحهٔ ادمینِ فاکتورِ بایگانی‌شده ۴۰۴ نمی‌شود (view برمی‌گردد).
        $view = app(\Modules\CRM\Http\Controllers\InvoiceController::class)->show($original->id);
        $this->assertInstanceOf(\Illuminate\Contracts\View\View::class, $view);
    }
}
