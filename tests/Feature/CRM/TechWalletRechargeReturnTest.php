<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\Api\V1\Technician\WalletController;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Payment;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * شارژ کیف‌پول از اپ تکنسین — مسیرِ درست: API شارژ URL درگاه را
 * برمی‌گرداند و اپ همان را باز می‌کند (نه صفحهٔ وبِ سشن‌محور که مهمان را
 * به لاگین می‌فرستاد). لینکِ برگشتِ اپ روی payment ذخیره می‌شود.
 */
class TechWalletRechargeReturnTest extends TestCase
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
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_payments', function ($t) {
            $t->id();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('gateway', 20)->nullable();
            $t->string('purpose', 30)->nullable();
            $t->bigInteger('amount')->default(0);
            $t->string('track_id')->nullable();
            $t->string('status', 20)->default('pending');
            $t->text('result_message')->nullable();
            $t->text('gateway_response')->nullable();
            $t->string('return_url', 500)->nullable();
            $t->timestamp('requested_at')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_settings', function ($t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        CrmSetting::set('zibal_merchant', 'test-merchant');
        CrmSetting::set('payment_gateway', 'zibal');
        Http::preventStrayRequests();
    }

    private function recharge(array $payload): array
    {
        Http::fake([
            'gateway.zibal.ir/v1/request' => Http::response(['result' => 100, 'trackId' => 'TRK-W1', 'payLink' => 'x']),
            '*' => Http::response([]),
        ]);
        $tech = Technician::forceCreate(['first_name' => 'تکنسین', 'mobile' => '09120000000', 'status' => 'active']);
        $request = Request::create('/v1/technician/wallet/recharge', 'POST', $payload);
        $request->setUserResolver(fn () => $tech);

        return app(WalletController::class)
            ->recharge($request, app(\Modules\CRM\Services\ZibalService::class), app(\Modules\CRM\Services\MellatService::class))
            ->getData(true);
    }

    public function test_the_api_returns_the_gateway_url_and_stores_the_app_return_link(): void
    {
        $json = $this->recharge(['amount' => 500_000, 'return_url' => 'karbalad://wallet?refresh=1']);

        $this->assertTrue($json['success']);
        $this->assertStringContainsString('gateway.zibal.ir/start/TRK-W1', $json['data']['payment_url']);
        $this->assertSame('karbalad://wallet?refresh=1', Payment::firstOrFail()->return_url);
    }

    public function test_a_foreign_return_link_is_dropped_but_recharge_still_works(): void
    {
        $json = $this->recharge(['amount' => 500_000, 'return_url' => 'https://evil.example/x']);

        $this->assertTrue($json['success']);
        $this->assertNull(Payment::firstOrFail()->return_url);
    }

    /** مهمانِ مسیرهای tech/* به لاگینِ تکنسین می‌رود، نه لاگینِ ادمین. */
    public function test_a_guest_on_tech_routes_is_redirected_to_the_tech_login(): void
    {
        $this->get('/tech/wallet')->assertRedirect(route('tech.login'));
    }
}
