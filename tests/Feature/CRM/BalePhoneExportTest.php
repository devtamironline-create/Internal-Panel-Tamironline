<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\BaleCampaignController;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Order;
use Tests\TestCase;

/**
 * خروجی شماره‌های کمپین بله — فیلتر بر اساس تعداد سفارش، نوع دستگاه و
 * بازهٔ تاریخ ثبت سفارش. خروجی: هر خط یک شماره با فرمت 98912...، یکتا؛
 * لیدها و سفارش‌های لغو/ردشده شمرده نمی‌شوند و مشتری مسدود در خروجی نیست.
 *
 * کنترلر مستقیم صدا زده می‌شود؛ زنجیرهٔ auth موضوعِ این تست نیست.
 */
class BalePhoneExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_customers', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('mobile')->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_blocked')->default(false);
            $t->string('bale_user_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
            $t->string('status', 30)->default('completed');
            $t->timestamp('status_changed_at')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->timestamps();
        });

        Schema::create('crm_devices', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });
    }

    private function customer(string $mobile, array $attributes = []): Customer
    {
        return Customer::forceCreate(array_merge([
            'first_name' => 'مشتری', 'mobile' => $mobile,
            'is_active' => true, 'is_blocked' => false,
        ], $attributes));
    }

    private function order(Customer $c, array $attributes = []): Order
    {
        return Order::forceCreate(array_merge([
            'customer_id' => $c->id, 'device_id' => 1,
            'status' => 'completed', 'is_lead' => false,
        ], $attributes));
    }

    /** @return list<string> خطوط خروجی */
    private function export(array $params = []): array
    {
        $request = Request::create('/admin/crm/bale-campaigns/export-phones', 'GET', $params);
        $response = app(BaleCampaignController::class)->exportPhones($request);

        $body = trim($response->getContent());

        return $body === '' ? [] : explode("\n", $body);
    }

    public function test_filters_by_min_orders_device_and_date_and_formats_as_intl(): void
    {
        $twoOrders = $this->customer('09121234567');
        $this->order($twoOrders);
        $this->order($twoOrders);

        $oneOrder = $this->customer('09357654321');
        $this->order($oneOrder);

        // سفارشِ دستگاه دیگر — با فیلتر device نباید بیاید.
        $otherDevice = $this->customer('09361235476');
        $this->order($otherDevice, ['device_id' => 2]);

        // سفارش قدیمی — بیرون از بازهٔ تاریخ.
        $old = $this->customer('09901112233');
        $this->order($old)->forceFill(['created_at' => now()->subYear()])->saveQuietly();

        $lines = $this->export(['min_orders' => 2, 'device_id' => 1]);
        $this->assertSame(['989121234567'], $lines);

        $lines = $this->export(['device_id' => 1, 'from' => \App\Support\JalaliDate::toJalali(now()->subDay()->toDateString())]);
        sort($lines);
        $this->assertSame(['989121234567', '989357654321'], $lines);
    }

    public function test_cancelled_orders_leads_and_blocked_customers_do_not_count(): void
    {
        $cancelledOnly = $this->customer('09120000001');
        $this->order($cancelledOnly, ['status' => 'cancelled']);

        $leadOnly = $this->customer('09120000002');
        $this->order($leadOnly, ['is_lead' => true]);

        $blocked = $this->customer('09120000003', ['is_blocked' => true]);
        $this->order($blocked);

        $ok = $this->customer('09120000004');
        $this->order($ok);

        $this->assertSame(['989120000004'], $this->export());
    }

    public function test_duplicate_and_invalid_mobiles_are_cleaned(): void
    {
        // دو مشتری با یک شماره (فرمت‌های متفاوت) + یک شمارهٔ خراب.
        $this->order($this->customer('09121234567'));
        $this->order($this->customer('989121234567'));
        $this->order($this->customer('123'));

        $this->assertSame(['989121234567'], $this->export());
    }
}
