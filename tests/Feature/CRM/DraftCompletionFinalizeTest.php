<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Http\Controllers\Tech\DashboardController;
use Modules\CRM\Http\Resources\TechOrderDetailResource;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Tests\TestCase;

/**
 * نهایی‌سازیِ تکمیلِ پیش‌نویس.
 *
 * باگِ گزارش‌شده: تکنسین سفارش را «پیش‌نویس» تکمیل می‌کرد (status=Completed +
 * save_as_draft) و بعد که برمی‌گشت نهایی کند، allowedStatusesFor چون Completed
 * را «نهایی» می‌دانست هیچ گذاری نمی‌داد — ثبتِ دوباره رد می‌شد و فاکتور و
 * بدهی هرگز ساخته نمی‌شد.
 *
 * قاعدهٔ قفل‌شده: پیش‌نویسِ تکمیل‌شده دقیقاً یک گذارِ مجاز دارد — خودِ
 * «تکمیل شده» (برای ثبتِ نهایی). تکمیلِ غیرپیش‌نویس مثل قبل هیچ گذاری ندارد.
 */
class DraftCompletionFinalizeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // allowedStatusesFor حالتِ freeze را از crm_settings می‌خواند.
        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
    }

    // ───────────────────────── helpers

    private function order(bool $draft): Order
    {
        $order = new Order;
        $order->forceFill(['status' => OrderStatus::Completed->value, 'save_as_draft' => $draft]);

        return $order;
    }

    /** @return array<int, OrderStatus> */
    private function apiAllowed(Order $order): array
    {
        $m = new \ReflectionMethod(OrderActionController::class, 'allowedStatusesFor');

        return $m->invoke(app(OrderActionController::class), $order);
    }

    /** @return array<int, OrderStatus> */
    private function pwaAllowed(Order $order): array
    {
        $m = new \ReflectionMethod(DashboardController::class, 'allowedStatusesFor');

        return $m->invoke(app(DashboardController::class), $order);
    }

    /** @return list<string> مقادیرِ status در خروجی Resource */
    private function resourceTransitions(Order $order): array
    {
        $m = new \ReflectionMethod(TechOrderDetailResource::class, 'allowedTransitions');
        $rows = $m->invoke(new TechOrderDetailResource($order), $order->status);

        return array_column($rows, 'value');
    }

    // ───────────────────────── قلبِ باگ

    public function test_a_draft_completed_order_can_be_finalized_via_api(): void
    {
        $this->assertSame([OrderStatus::Completed], $this->apiAllowed($this->order(draft: true)));
    }

    public function test_a_draft_completed_order_can_be_finalized_via_tech_panel(): void
    {
        $this->assertSame([OrderStatus::Completed], $this->pwaAllowed($this->order(draft: true)));
    }

    public function test_the_app_resource_offers_the_finalize_transition(): void
    {
        $this->assertSame([OrderStatus::Completed->value], $this->resourceTransitions($this->order(draft: true)));
    }

    // ───────────────────────── رفتارِ قبلی دست‌نخورده می‌ماند

    public function test_a_really_completed_order_stays_locked(): void
    {
        $this->assertSame([], $this->apiAllowed($this->order(draft: false)));
        $this->assertSame([], $this->pwaAllowed($this->order(draft: false)));
        $this->assertSame([], $this->resourceTransitions($this->order(draft: false)));
    }

    public function test_freeze_mode_blocks_finalizing_too(): void
    {
        CrmSetting::set('tech_panel_readonly', '1');

        $this->assertSame([], $this->apiAllowed($this->order(draft: true)));
        $this->assertSame([], $this->pwaAllowed($this->order(draft: true)));
        $this->assertSame([], $this->resourceTransitions($this->order(draft: true)));
    }
}
