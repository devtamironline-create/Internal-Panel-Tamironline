<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Http\Controllers\Api\V1\Technician\ProformaController;
use Modules\CRM\Http\Middleware\RequireTrainingCompleted;
use Modules\CRM\Http\Resources\TechOrderListResource;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Proforma;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\TrainingVideo;
use Tests\TestCase;

/**
 * دو قرارداد اپِ تکنسین (۱۴۰۵/۰۵/۲۷):
 *
 *   الف) پیش‌فاکتور فقط از داخلِ سفارش و فقط پس از هماهنگی تا پیش از بسته‌شدن.
 *   ب)  تکنسینِ آموزش‌ندیده حتی با توکنِ معتبر هم به مسیرهای کاری نمی‌رسد.
 *
 * کنترلر/میدل‌ور مستقیم صدا زده می‌شوند (نه از طریق روت) — همان سبکِ بقیهٔ
 * تست‌های اپ در این ریپو.
 */
class TechProformaAndTrainingGateTest extends TestCase
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
            $t->integer('percent')->default(0);
            $t->bigInteger('wallet_balance')->default(0);
            $t->timestamp('training_completed_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->string('status')->nullable();
            $t->string('customer_name')->nullable();
            $t->string('customer_mobile')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamp('visit_scheduled_at')->nullable();
            $t->unsignedTinyInteger('return_type')->nullable();
            $t->boolean('return_review_pending')->default(false);
            $t->timestamps();
        });

        Schema::create('crm_proformas', function ($t) {
            $t->id();
            $t->string('proforma_code', 40);
            $t->string('public_token', 64);
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('customer_name', 150)->nullable();
            $t->string('customer_mobile', 20)->nullable();
            $t->string('device_name', 120)->nullable();
            $t->string('brand_name', 120)->nullable();
            $t->json('items');
            $t->unsignedBigInteger('subtotal')->default(0);
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('total')->default(0);
            $t->text('description')->nullable();
            $t->date('valid_until')->nullable();
            $t->string('status', 20)->default('draft');
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->unsignedBigInteger('created_by_user_id')->nullable();
            $t->unsignedBigInteger('created_by_tech_id')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_training_videos', function ($t) {
            $t->id();
            $t->string('title');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('crm_technician_watched_videos', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('video_id');
            $t->timestamps();
        });

        CrmSetting::set('tech_proforma_enabled', '1');
    }

    private function tech(bool $trained = true): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'تکنسین تست',
            'mobile' => '09120000000',
            'status' => 'active',
            'percent' => 30,
            'training_completed_at' => $trained ? now() : null,
        ]);
    }

    private function order(Technician $tech, OrderStatus $status): Order
    {
        return Order::forceCreate([
            'order_code' => 'PF-'.uniqid(),
            'technician_id' => $tech->id,
            'status' => $status->value,
            'customer_name' => 'رضا مشتری',
            'customer_mobile' => '09121112233',
        ]);
    }

    private function items(): array
    {
        return [['title' => 'تعویض برد', 'quantity' => 1, 'unit_price' => 1_500_000]];
    }

    private function callStore(Technician $tech, array $payload)
    {
        $request = Request::create('/v1/technician/proformas', 'POST', $payload);
        $request->setUserResolver(fn () => $tech);

        return app(ProformaController::class)->store($request);
    }

    // ───────────────────────── الف) قاعدهٔ وضعیت (تنها مرجع)

    public function test_the_status_rule_opens_only_between_coordination_and_closing(): void
    {
        // فازِ تماس/هماهنگی — هنوز نه.
        foreach ([OrderStatus::New, OrderStatus::AwaitingCoordination, OrderStatus::NoAnswer, OrderStatus::Coordinated] as $s) {
            $this->assertFalse($s->allowsProforma(), $s->value.' نباید پیش‌فاکتور بدهد.');
        }

        // فازِ کار — بله.
        foreach ([OrderStatus::RepairStarted, OrderStatus::Open, OrderStatus::AwaitingPart, OrderStatus::AwaitingCustomerApproval, OrderStatus::Suspended] as $s) {
            $this->assertTrue($s->allowsProforma(), $s->value.' باید پیش‌فاکتور بدهد.');
        }

        // بسته — دیگر نه.
        foreach ([OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Transit, OrderStatus::Declined] as $s) {
            $this->assertFalse($s->allowsProforma(), $s->value.' بسته است.');
        }
    }

    // ───────────────────────── الف) ساخت از روی سفارش

    public function test_creating_from_an_order_fills_customer_and_device_from_the_order(): void
    {
        $tech = $this->tech();
        $order = $this->order($tech, OrderStatus::RepairStarted);

        $response = $this->callStore($tech, ['order_id' => $order->id, 'items' => $this->items()]);

        $this->assertTrue($response->getData(true)['success']);

        $pf = Proforma::firstOrFail();
        $this->assertSame($order->id, (int) $pf->order_id);
        $this->assertSame('رضا مشتری', $pf->customer_name, 'نام مشتری باید از سفارش پر شود.');
        $this->assertSame('09121112233', $pf->customer_mobile);
        $this->assertSame(1_500_000, (int) $pf->total);
        // بلافاصله در اپِ مشتری دیده می‌شود (بدونِ پیامک).
        $this->assertSame('sent', $pf->status);
    }

    public function test_creating_without_an_order_is_rejected(): void
    {
        $tech = $this->tech();

        try {
            $this->callStore($tech, ['items' => $this->items()]);
            $this->fail('پیش‌فاکتورِ مستقل نباید ساخته شود.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('order_id', $e->errors());
        }
    }

    public function test_creating_in_the_coordination_phase_is_rejected_with_a_persian_message(): void
    {
        $tech = $this->tech();
        $order = $this->order($tech, OrderStatus::Coordinated);

        try {
            $this->callStore($tech, ['order_id' => $order->id, 'items' => $this->items()]);
            $this->fail('در فازِ هماهنگی نباید پیش‌فاکتور ساخته شود.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('پس از هماهنگی', $e->errors()['order_id'][0]);
        }

        $this->assertSame(0, Proforma::count());
    }

    public function test_creating_on_a_closed_order_is_rejected(): void
    {
        $tech = $this->tech();
        $order = $this->order($tech, OrderStatus::Completed);

        try {
            $this->callStore($tech, ['order_id' => $order->id, 'items' => $this->items()]);
            $this->fail('سفارشِ بسته نباید پیش‌فاکتور بپذیرد.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('بسته شده', $e->errors()['order_id'][0]);
        }
    }

    public function test_another_technicians_order_is_forbidden(): void
    {
        $mine = $this->tech();
        $other = Technician::forceCreate([
            'first_name' => 'دیگری', 'mobile' => '09129999999',
            'status' => 'active', 'training_completed_at' => now(),
        ]);
        $order = $this->order($other, OrderStatus::RepairStarted);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->callStore($mine, ['order_id' => $order->id, 'items' => $this->items()]);
    }

    // ───────────────────────── الف) فیلدِ ریسورس هم‌مرجع با گیت

    public function test_the_list_resource_flag_matches_the_server_rule(): void
    {
        $tech = $this->tech();
        $request = Request::create('/v1/technician/orders');

        $open = new TechOrderListResource($this->order($tech, OrderStatus::RepairStarted));
        $this->assertTrue($open->toArray($request)['can_create_proforma']);

        $coordinating = new TechOrderListResource($this->order($tech, OrderStatus::Coordinated));
        $this->assertFalse($coordinating->toArray($request)['can_create_proforma']);

        $closed = new TechOrderListResource($this->order($tech, OrderStatus::Completed));
        $this->assertFalse($closed->toArray($request)['can_create_proforma']);
    }

    public function test_turning_the_feature_off_closes_the_flag(): void
    {
        CrmSetting::set('tech_proforma_enabled', '0');
        $tech = $this->tech();

        $resource = new TechOrderListResource($this->order($tech, OrderStatus::RepairStarted));

        $this->assertFalse($resource->toArray(Request::create('/v1/technician/orders'))['can_create_proforma']);
    }

    // ───────────────────────── ب) گیتِ آموزش روی API

    /** درخواستِ JSON با نامِ روتِ داده‌شده از میدل‌ور عبور داده می‌شود. */
    private function passThroughGate(Technician $tech, string $routeName, string $uri = '/v1/technician/orders')
    {
        $request = Request::create($uri, 'GET');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $tech);
        $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route('GET', ltrim($uri, '/'), []))->name($routeName));

        return (new RequireTrainingCompleted)->handle($request, fn () => response()->json(['success' => true]));
    }

    public function test_an_untrained_technician_is_blocked_from_working_endpoints(): void
    {
        TrainingVideo::forceCreate(['title' => 'ویدیو ۱', 'is_active' => true]);
        $tech = $this->tech(trained: false);

        foreach ([
            'api.tech.orders.index' => '/v1/technician/orders',
            'api.tech.dashboard' => '/v1/technician/dashboard',
            'api.tech.wallet' => '/v1/technician/wallet',
            'api.tech.proformas.store' => '/v1/technician/proformas',
            'api.tech.invoices' => '/v1/technician/invoices',
        ] as $name => $uri) {
            $response = $this->passThroughGate($tech, $name, $uri);

            $this->assertSame(403, $response->getStatusCode(), $name.' باید بسته باشد.');
            $body = $response->getData(true);
            $this->assertTrue($body['training_required']);
            $this->assertSame('/training', $body['redirect']);
        }
    }

    public function test_an_untrained_technician_can_still_reach_training_and_identity(): void
    {
        TrainingVideo::forceCreate(['title' => 'ویدیو ۱', 'is_active' => true]);
        $tech = $this->tech(trained: false);

        foreach ([
            'api.tech.training.index',
            'api.tech.training.watched',
            'api.tech.me',
            'api.tech.sync',
            'api.tech.app-config',
            'api.tech.auth.logout',
            'api.tech.profile.password',
        ] as $name) {
            $response = $this->passThroughGate($tech, $name);

            $this->assertSame(200, $response->getStatusCode(), $name.' باید باز بماند.');
        }
    }

    public function test_a_trained_technician_passes_through(): void
    {
        $tech = $this->tech(trained: true);

        $response = $this->passThroughGate($tech, 'api.tech.orders.index');

        $this->assertSame(200, $response->getStatusCode());
    }
}
