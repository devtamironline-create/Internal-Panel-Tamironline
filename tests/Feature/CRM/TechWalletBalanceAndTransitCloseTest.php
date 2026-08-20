<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Http\Controllers\Api\V1\Technician\WalletController;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Tests\TestCase;

/**
 * دو درخواستِ بازِ تیم فرانت (اپ تکنسین):
 *
 *   ۱) GET /wallet باید «اعتبار» را برگرداند — balance همان ستونِ
 *      wallet_balance است که پنل ادمین نشان می‌دهد؛ true_balance همان منهای
 *      بدهی فاکتوری.
 *   ۲) بستنِ سفارش با «ایاب و ذهاب» (transit) دیگر estimated_ready_at
 *      نمی‌خواهد؛ توضیح ≥ ۱۵ کاراکتر می‌ماند و هزینهٔ ایاب و ذهاب اختیاری
 *      (transportation، تومان) روی سفارش ذخیره می‌شود.
 *
 * کنترلرها مستقیم صدا زده می‌شوند؛ زنجیرهٔ auth موضوعِ این تست نیست.
 */
class TechWalletBalanceAndTransitCloseTest extends TestCase
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
            $t->boolean('ready_for_delivery')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('crm_tech_wallet_transactions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('technician_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->string('type', 30);
            $t->bigInteger('amount')->default(0);
            $t->bigInteger('balance_after')->nullable();
            $t->string('description', 500)->nullable();
            $t->timestamps();
        });

        Schema::create('crm_invoices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->bigInteger('company_share')->default(0);
            $t->boolean('in_wallet')->default(false);
            $t->string('public_token', 64)->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->unsignedBigInteger('superseded_by_id')->nullable();
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
            $t->tinyInteger('return_type')->nullable();
            $t->text('return_description')->nullable();
            $t->bigInteger('transportation')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->boolean('save_as_draft')->default(false);
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
        // TechnicianResource پیشرفتِ آموزش را می‌خواند.
        Schema::create('crm_training_videos', fn ($t) => tap($t, fn ($x) => [$x->id(), $x->boolean('is_active')->default(true)]));

        // هوکِ actor لاگِ وضعیت به جدول users سر می‌زند.
        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
            '--force' => true,
        ]);
        \Modules\CRM\Models\OrderStatusLog::flushActorCache();
    }

    private function technician(int $balance = 0): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'تکنسین تست',
            'mobile' => '09120000000',
            'status' => 'active',
            'wallet_balance' => $balance,
        ]);
    }

    // ───────────────────────── کیف‌پول

    public function test_wallet_returns_balance_and_true_balance(): void
    {
        $tech = $this->technician(balance: 2_000_000);
        \Modules\CRM\Models\Invoice::forceCreate([
            'technician_id' => $tech->id, 'company_share' => 500_000, 'in_wallet' => false,
        ]);

        $request = Request::create('/v1/technician/wallet', 'GET');
        $request->setUserResolver(fn () => $tech);

        $json = app(WalletController::class)->index($request)->getData(true);

        $this->assertSame(2_000_000, $json['balance']);
        $this->assertSame(500_000, $json['invoice_debt']);
        $this->assertSame(1_500_000, $json['true_balance']);
    }

    public function test_me_resource_exposes_the_balance_too(): void
    {
        $tech = $this->technician(balance: 750_000);

        $payload = (new \Modules\CRM\Http\Resources\TechnicianResource($tech))
            ->toArray(Request::create('/v1/technician/me'));

        $this->assertSame(750_000, $payload['balance']);
    }

    // ───────────────────────── بستن با «ایاب و ذهاب»

    private function closeAsTransit(Order $order, Technician $tech, array $payload): \Illuminate\Http\JsonResponse
    {
        $request = Request::create('/v1/technician/orders/'.$order->id.'/status', 'POST', array_merge([
            'status' => 'transit',
            'description' => 'مراجعه انجام شد؛ تعمیری صورت نگرفت و فقط ایاب و ذهاب منظور شد.',
        ], $payload));
        $request->setUserResolver(fn () => $tech);

        return app(OrderActionController::class)->updateStatus($request, $order->id);
    }

    public function test_transit_close_no_longer_requires_an_estimate(): void
    {
        $tech = $this->technician();
        $order = Order::forceCreate([
            'order_code' => 'TR-1', 'technician_id' => $tech->id, 'status' => 'coordinated',
        ]);

        $response = $this->closeAsTransit($order, $tech, ['transportation' => 350_000]);

        $this->assertTrue($response->getData(true)['success']);
        $order->refresh();
        $this->assertSame('transit', $order->status->value);
        $this->assertSame(350_000, (int) $order->transportation);
        $this->assertStringContainsString('ایاب و ذهاب', (string) $order->return_description);
    }

    public function test_transit_close_still_demands_a_real_description(): void
    {
        $tech = $this->technician();
        $order = Order::forceCreate([
            'order_code' => 'TR-2', 'technician_id' => $tech->id, 'status' => 'coordinated',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->closeAsTransit($order, $tech, ['description' => 'کوتاه']);
    }
}
