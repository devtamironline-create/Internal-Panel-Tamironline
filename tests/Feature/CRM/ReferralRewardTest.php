<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Customer;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Services\ReferralService;
use Tests\TestCase;

/**
 * پاداشِ معرف: فقط وقتی سفارشِ کاربرِ معرفی‌شده هم تکمیل و هم پرداخت شده باشد،
 * یک‌بار به معرف پرداخت می‌شود.
 */
class ReferralRewardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_customers', function ($t) {
            $t->id();
            $t->string('mobile')->unique();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->boolean('is_active')->default(true);
            $t->bigInteger('wallet_balance')->default(0);
            $t->unsignedBigInteger('referred_by')->nullable();
            $t->timestamp('referral_rewarded_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('crm_customer_wallet_transactions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->string('type', 30);
            $t->bigInteger('amount');
            $t->bigInteger('balance_after');
            $t->text('note')->nullable();
            $t->json('meta')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('status', 40)->nullable();
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('crm_invoices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->string('status', 30)->default('unpaid');
            $t->string('public_token', 64)->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->timestamps();
        });
    }

    private function customer(string $mobile, array $extra = []): Customer
    {
        return Customer::create($extra + ['mobile' => $mobile, 'is_active' => true, 'wallet_balance' => 0]);
    }

    public function test_referrer_is_rewarded_once_when_order_completed_and_paid(): void
    {
        $referrer = $this->customer('09120000001');
        $referred = $this->customer('09120000002', ['referred_by' => $referrer->id]);

        $order = Order::forceCreate(['customer_id' => $referred->id, 'status' => OrderStatus::Completed]);
        Invoice::forceCreate(['order_id' => $order->id, 'status' => 'paid']);

        $svc = app(ReferralService::class);
        $svc->rewardReferrerIfEligible($order);

        $this->assertSame($svc->rewardAmount(), (int) $referrer->fresh()->wallet_balance);
        $this->assertNotNull($referred->fresh()->referral_rewarded_at);

        // idempotent — فراخوانیِ دوباره پاداشِ دوم نمی‌دهد.
        $svc->rewardReferrerIfEligible($order);
        $this->assertSame($svc->rewardAmount(), (int) $referrer->fresh()->wallet_balance);
    }

    public function test_no_reward_before_order_is_paid(): void
    {
        $referrer = $this->customer('09120000003');
        $referred = $this->customer('09120000004', ['referred_by' => $referrer->id]);

        // تکمیل‌شده ولی فاکتور پرداخت‌نشده.
        $order = Order::forceCreate(['customer_id' => $referred->id, 'status' => OrderStatus::Completed]);
        Invoice::forceCreate(['order_id' => $order->id, 'status' => 'unpaid']);

        app(ReferralService::class)->rewardReferrerIfEligible($order);

        $this->assertSame(0, (int) $referrer->fresh()->wallet_balance);
        $this->assertNull($referred->fresh()->referral_rewarded_at);
    }

    public function test_no_reward_when_customer_has_no_referrer(): void
    {
        $customer = $this->customer('09120000005'); // referred_by = null
        $order = Order::forceCreate(['customer_id' => $customer->id, 'status' => OrderStatus::Completed]);
        Invoice::forceCreate(['order_id' => $order->id, 'status' => 'paid']);

        app(ReferralService::class)->rewardReferrerIfEligible($order);

        $this->assertSame(0, \Modules\CRM\Models\CustomerWalletTransaction::query()->count());
    }
}
