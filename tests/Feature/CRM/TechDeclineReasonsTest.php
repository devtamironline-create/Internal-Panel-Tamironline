<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Http\Controllers\Api\V1\Technician\AppConfigController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Services\TechnicianSuggestionService;
use Tests\TestCase;

/**
 * «ردِ سفارش توسطِ تکنسین» — مفهومی جدا از «کنسلِ» ادمین:
 *   • لیستِ انتخابیِ قابلِ مدیریتِ ادمین (technician_decline_reasons) که از
 *     app-config به اپ می‌رسد.
 *   • هر علت می‌تواند reopen داشته باشد: ردِ سفارش با آن علت، سفارش را از
 *     تکنسین می‌گیرد و برای تخصیصِ مجدد باز می‌کند (تکنسینِ ردکننده کنار
 *     گذاشته می‌شود).
 */
class TechDeclineReasonsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        // hookِ OrderStatusLog عاملِ لاگ را از users می‌خواند (وقتی actor_name
        // ست نشده باشد، مثلِ لاگِ داخلیِ unassign). جدولِ خالی کافی است.
        Schema::create('users', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
            $t->string('status')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedInteger('wp_id')->nullable();
            $t->timestamp('last_assigned_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->unsignedInteger('technician_wp_id')->nullable();
            $t->string('status')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->json('declined_technician_ids')->nullable();
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamp('visit_scheduled_at')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->unsignedTinyInteger('return_type')->nullable();
            $t->boolean('return_review_pending')->default(false);
            $t->boolean('is_locked')->default(false);
            $t->boolean('save_as_draft')->default(false);
            $t->boolean('force_review')->default(false);
            $t->string('customer_name')->nullable();
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
        Schema::create('crm_order_assignment_logs', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->unsignedBigInteger('previous_technician_id')->nullable();
            $t->string('mode', 30)->nullable();
            $t->integer('score')->nullable();
            $t->json('breakdown')->nullable();
            $t->json('reasons')->nullable();
            $t->json('alternatives')->nullable();
            $t->integer('group_size')->nullable();
            $t->integer('covered_count')->nullable();
            $t->json('group_order_ids')->nullable();
            $t->text('note')->nullable();
            $t->unsignedBigInteger('assigned_by')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        // جدول‌هایی که مسیرِ Declinedِ نهایی (نه reopen) در پایانِ updateStatus لمس می‌کند.
        Schema::create('crm_order_items', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->timestamps();
        });
        Schema::create('crm_transfer_receipts', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->timestamps();
        });
        Schema::create('crm_order_objection', function ($t) {
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('objection_id');
            $t->integer('sort_order')->default(0);
        });
        Schema::create('crm_objections', function ($t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('slug')->nullable();
            $t->timestamps();
        });
        Schema::create('crm_invoices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->string('collection_method')->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->timestamps();
        });
    }

    private function tech(): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'تست', 'mobile' => '09120000000', 'status' => 'active', 'user_id' => 1,
        ]);
    }

    private function order(Technician $tech): Order
    {
        return Order::forceCreate([
            'order_code' => 'DR-1', 'technician_id' => $tech->id,
            'status' => OrderStatus::New->value, 'status_changed_at' => now(),
        ]);
    }

    private function decline(Order $order, Technician $tech, array $payload)
    {
        $request = Request::create('/v1/technician/orders/'.$order->id.'/status', 'POST', $payload);
        $request->setUserResolver(fn () => $tech);

        return app(OrderActionController::class)->updateStatus($request, $order->id);
    }

    public function test_app_config_exposes_admin_managed_decline_reasons(): void
    {
        CrmSetting::setJson('technician_decline_reasons', [
            ['label' => 'خارج از تخصص', 'reopen' => true],
            ['label' => 'سایر', 'reopen' => false],
        ]);

        $data = app(AppConfigController::class)->__invoke()->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame([
            ['label' => 'خارج از تخصص', 'reopen' => true],
            ['label' => 'سایر', 'reopen' => false],
        ], $data['data']['decline_reasons']);
    }

    public function test_app_config_falls_back_to_default_reasons(): void
    {
        $data = app(AppConfigController::class)->__invoke()->getData(true);

        $this->assertSame(Order::DECLINE_REASONS, $data['data']['decline_reasons']);
    }

    public function test_decline_with_non_reopen_reason_is_terminal_and_stores_it(): void
    {
        CrmSetting::setJson('technician_decline_reasons', [
            ['label' => 'سایر', 'reopen' => false],
        ]);
        $tech = $this->tech();
        $order = $this->order($tech);

        $res = $this->decline($order, $tech, [
            'status' => OrderStatus::Declined->value,
            'decline_reason' => 'سایر',
        ]);

        $this->assertTrue($res->getData(true)['success']);
        $order->refresh();
        $this->assertSame(OrderStatus::Declined->value, $order->status->value);
        $this->assertSame('سایر', $order->cancel_reason);
        $this->assertSame((int) $tech->id, (int) $order->technician_id); // تکنسین همان می‌ماند
    }

    public function test_decline_with_reopen_reason_unassigns_and_reopens(): void
    {
        CrmSetting::setJson('technician_decline_reasons', [
            ['label' => 'عدم توانایی در انجام کار', 'reopen' => true],
        ]);
        $tech = $this->tech();
        $order = $this->order($tech);

        $res = $this->decline($order, $tech, [
            'status' => OrderStatus::Declined->value,
            'decline_reason' => 'عدم توانایی در انجام کار',
        ])->getData(true);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['data']['reopened']);

        $order->refresh();
        // سفارش «رد شده»ی نهایی نشد؛ برای تخصیصِ مجدد باز شد.
        $this->assertSame(OrderStatus::New->value, $order->status->value);
        $this->assertNull($order->technician_id);
        $this->assertContains((int) $tech->id, array_map('intval', $order->declined_technician_ids ?? []));
    }

    public function test_reopen_accepts_legacy_cancel_reason_field_name(): void
    {
        CrmSetting::setJson('technician_decline_reasons', [
            ['label' => 'عدم توانایی در انجام کار', 'reopen' => true],
        ]);
        $tech = $this->tech();
        $order = $this->order($tech);

        // کلاینتِ قدیمی که فیلد را cancel_reason می‌فرستد هم باید کار کند.
        $res = $this->decline($order, $tech, [
            'status' => OrderStatus::Declined->value,
            'cancel_reason' => 'عدم توانایی در انجام کار',
        ])->getData(true);

        $this->assertTrue($res['data']['reopened']);
        $this->assertNull($order->refresh()->technician_id);
    }

    public function test_decline_rejects_reason_outside_admin_list(): void
    {
        CrmSetting::setJson('technician_decline_reasons', [
            ['label' => 'خارج از تخصص', 'reopen' => true],
        ]);
        $tech = $this->tech();
        $order = $this->order($tech);

        $this->expectException(ValidationException::class);
        $this->decline($order, $tech, [
            'status' => OrderStatus::Declined->value,
            'decline_reason' => 'یک علتِ جعلی',
        ]);
    }

    public function test_decline_without_reason_still_requires_free_text_backward_compat(): void
    {
        CrmSetting::setJson('technician_decline_reasons', [
            ['label' => 'خارج از تخصص', 'reopen' => true],
        ]);
        $tech = $this->tech();
        $order = $this->order($tech);

        $this->expectException(ValidationException::class);
        $this->decline($order, $tech, [
            'status' => OrderStatus::Declined->value,
            'description' => 'کوتاه',
        ]);
    }

    public function test_previously_declined_technician_is_excluded_from_suggestions(): void
    {
        $tech = $this->tech();
        $order = Order::forceCreate([
            'order_code' => 'DR-9',
            'status' => OrderStatus::New->value,
            'declined_technician_ids' => [$tech->id],
        ]);

        $reason = app(TechnicianSuggestionService::class)->rejectionFor($tech, $order, 0);

        $this->assertSame('previously_declined', $reason);
    }
}
