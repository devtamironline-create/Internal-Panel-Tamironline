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
use Tests\TestCase;

/**
 * علت‌های «ردِ سفارش» توسطِ تکنسین: لیستِ انتخابیِ قابلِ مدیریتِ ادمین
 * (order_cancel_reasons) که از app-config به اپ می‌رسد و در endpointِ
 * تغییرِ وضعیت به‌صورتِ فیلدِ cancel_reason پذیرفته می‌شود.
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
            $t->string('cancel_reason')->nullable();
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamp('visit_scheduled_at')->nullable();
            $t->unsignedTinyInteger('return_type')->nullable();
            $t->boolean('return_review_pending')->default(false);
            $t->boolean('is_locked')->default(false);
            $t->boolean('save_as_draft')->default(false);
            $t->boolean('force_review')->default(false);
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
        // جدول‌هایی که $order->load() در پایانِ updateStatus لمس می‌کند.
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
        CrmSetting::setJson('order_cancel_reasons', ['خارج از تخصص', 'آدرس خارج از محدوده']);

        $data = app(AppConfigController::class)->__invoke()->getData(true);

        $this->assertTrue($data['success']);
        $this->assertSame(['خارج از تخصص', 'آدرس خارج از محدوده'], $data['data']['decline_reasons']);
    }

    public function test_app_config_falls_back_to_default_reasons(): void
    {
        $data = app(AppConfigController::class)->__invoke()->getData(true);

        $this->assertSame(Order::CANCEL_REASONS, $data['data']['decline_reasons']);
    }

    public function test_decline_with_selected_reason_stores_it_without_free_text(): void
    {
        CrmSetting::setJson('order_cancel_reasons', ['خارج از تخصص', 'آدرس خارج از محدوده']);
        $tech = $this->tech();
        $order = $this->order($tech);

        $res = $this->decline($order, $tech, [
            'status' => OrderStatus::Declined->value,
            'cancel_reason' => 'خارج از تخصص',
        ]);

        $this->assertTrue($res->getData(true)['success']);
        $order->refresh();
        $this->assertSame(OrderStatus::Declined->value, $order->status->value);
        $this->assertSame('خارج از تخصص', $order->cancel_reason);
    }

    public function test_decline_rejects_reason_outside_admin_list(): void
    {
        CrmSetting::setJson('order_cancel_reasons', ['خارج از تخصص']);
        $tech = $this->tech();
        $order = $this->order($tech);

        $this->expectException(ValidationException::class);
        $this->decline($order, $tech, [
            'status' => OrderStatus::Declined->value,
            'cancel_reason' => 'یک علتِ جعلی',
        ]);
    }

    public function test_decline_without_reason_still_requires_free_text_backward_compat(): void
    {
        CrmSetting::setJson('order_cancel_reasons', ['خارج از تخصص']);
        $tech = $this->tech();
        $order = $this->order($tech);

        // نه علتِ انتخابی، نه متنِ کافی → باید رد شود (رفتارِ قبلی حفظ شده).
        $this->expectException(ValidationException::class);
        $this->decline($order, $tech, [
            'status' => OrderStatus::Declined->value,
            'description' => 'کوتاه',
        ]);
    }

    public function test_decline_with_free_text_only_still_works(): void
    {
        CrmSetting::setJson('order_cancel_reasons', ['خارج از تخصص']);
        $tech = $this->tech();
        $order = $this->order($tech);

        $res = $this->decline($order, $tech, [
            'status' => OrderStatus::Declined->value,
            'description' => 'دستگاه مدلِ خیلی قدیمی بود و قطعه نداشت.',
        ]);

        $this->assertTrue($res->getData(true)['success']);
        $this->assertSame('دستگاه مدلِ خیلی قدیمی بود و قطعه نداشت.', $order->refresh()->cancel_reason);
    }
}
