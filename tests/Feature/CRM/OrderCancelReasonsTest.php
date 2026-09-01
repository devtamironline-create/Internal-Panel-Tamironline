<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\OrderOpsSettingsController;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Order;
use Tests\TestCase;

/**
 * دلایلِ کنسل/رد سفارش باید از تنظیماتِ ادمین خوانده شوند و در نبودِ تنظیم،
 * به لیستِ پیش‌فرضِ ثابت برگردند.
 */
class OrderCancelReasonsTest extends TestCase
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
    }

    public function test_defaults_to_the_fixed_list_when_nothing_is_stored(): void
    {
        $this->assertSame(Order::CANCEL_REASONS, Order::cancelReasons());
    }

    public function test_reads_the_admin_managed_list_when_present(): void
    {
        CrmSetting::setJson('order_cancel_reasons', ['دلیل الف', 'دلیل ب']);

        $this->assertSame(['دلیل الف', 'دلیل ب'], Order::cancelReasons());
    }

    public function test_update_trims_dedupes_and_drops_blanks(): void
    {
        $request = Request::create('/admin/crm/order-settings', 'POST', [
            'cancel_reasons' => ['  دلیل الف ', '', 'دلیل ب', 'دلیل ب', '   '],
        ]);

        app(OrderOpsSettingsController::class)->update($request);

        $this->assertSame(['دلیل الف', 'دلیل ب'], Order::cancelReasons());
    }

    public function test_update_rejects_an_empty_list(): void
    {
        CrmSetting::setJson('order_cancel_reasons', ['قبلی']);

        $request = Request::create('/admin/crm/order-settings', 'POST', [
            'cancel_reasons' => ['', '   '],
        ]);

        app(OrderOpsSettingsController::class)->update($request);

        // لیستِ قبلی دست‌نخورده می‌ماند.
        $this->assertSame(['قبلی'], Order::cancelReasons());
    }
}
