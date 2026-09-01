<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Http\Controllers\PaymentController;
use Modules\CRM\Models\CrmSetting;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Payment;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Tests\TestCase;

/**
 * پرداخت آنلاین فاکتور توسط مشتری — قواعد قفل‌شده:
 *
 *   ۱) verify موفق: فاکتور paid + «+کل مبلغ» به کیف‌پول تکنسین
 *      (برآیند با −سهم شرکتِ هنگام صدور = +سهم تکنسین).
 *   ۲) callback دوباره (redirect + webhook) هرگز دوبار واریز نمی‌کند.
 *   ۳) عدم تطابق مبلغ تأییدشده = هیچ اثری؛ payment می‌شود failed.
 *   ۴) شارژ کیف‌پول تکنسین (purpose=wallet_charge) مثل قبل کار می‌کند.
 *   ۵) endpoint وضعیت برای اپ مشتری: paid/payable/superseded.
 *   ۶) فاکتور صفر/لغو/جایگزین‌شده اصلاً وارد درگاه نمی‌شود.
 *
 * کنترلر مستقیم صدا زده می‌شود؛ Http فیک است و هیچ درخواستی بیرون نمی‌رود.
 */
class CustomerOnlinePaymentTest extends TestCase
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
            $t->string('collection_method', 10)->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->unsignedBigInteger('superseded_by_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_payments', function ($t) {
            $t->id();
            $t->unsignedBigInteger('wp_id')->nullable();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('gateway', 20)->nullable();
            $t->string('purpose', 30)->nullable();
            $t->bigInteger('amount')->default(0);
            $t->string('track_id')->nullable();
            $t->string('ref_number')->nullable();
            $t->string('card_number')->nullable();
            $t->string('hash_card_number')->nullable();
            $t->string('status', 20)->default('pending');
            $t->integer('result_code')->nullable();
            $t->text('result_message')->nullable();
            $t->text('gateway_response')->nullable();
            $t->string('return_url', 500)->nullable();
            $t->timestamp('requested_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status', 30)->default('completed');
            $t->timestamp('status_changed_at')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->timestamps();
        });

        Schema::create('crm_customers', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('first_name')->nullable(), $x->string('mobile')->nullable(), $x->softDeletes(),
        ]));

        Schema::create('crm_sms_templates', function ($t) {
            $t->id();
            $t->string('trigger_key')->nullable();
            $t->string('title')->nullable();
            $t->string('recipient', 20)->nullable();
            $t->text('body')->nullable();
            $t->string('kavenegar_template')->nullable();
            $t->text('token_vars')->nullable();
            $t->boolean('is_active')->default(false);
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

        // هیچ HTTPای واقعاً بیرون نرود.
        Http::preventStrayRequests();
    }

    // ───────────────────────── helpers

    private function technician(int $balance = 0): Technician
    {
        return Technician::forceCreate([
            'first_name' => 'تکنسین', 'mobile' => '09120000000',
            'status' => 'active', 'wallet_balance' => $balance,
        ]);
    }

    private function invoiceFor(Technician $tech, int $amount, array $extra = []): Invoice
    {
        $order = Order::forceCreate([
            'order_code' => 'PAY-'.uniqid(), 'technician_id' => $tech->id, 'status' => 'completed',
        ]);

        return Invoice::forceCreate(array_merge([
            'invoice_code' => 'INV-T-'.uniqid(),
            'order_id' => $order->id,
            'technician_id' => $tech->id,
            'total_amount' => $amount,
            'company_share' => (int) ($amount * 0.3),
            'status' => 'issued',
            'issued_at' => now(),
        ], $extra));
    }

    private function pendingPayment(Invoice $invoice, string $trackId): Payment
    {
        return Payment::forceCreate([
            'invoice_id' => $invoice->id,
            'order_id' => $invoice->order_id,
            'technician_id' => $invoice->technician_id,
            'gateway' => 'zibal',
            'amount' => (int) $invoice->total_amount,
            'track_id' => $trackId,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }

    private function fakeVerify(int $amountToman, bool $ok = true): void
    {
        Http::fake([
            'gateway.zibal.ir/v1/verify' => Http::response($ok ? [
                'result' => 100, 'amount' => $amountToman * 10, // ریال
                'refNumber' => 'REF-123', 'cardNumber' => '6037****1234',
                'message' => 'success',
            ] : ['result' => 102, 'message' => 'verify failed']),
            '*' => Http::response([], 200),
        ]);
    }

    private function hitCallback(string $trackId)
    {
        $request = Request::create('/crm/payment/callback', 'GET', ['trackId' => $trackId, 'success' => '1']);

        return app(PaymentController::class)->callback($request);
    }

    // ───────────────────────── ۱) اثرِ مالیِ پرداختِ موفق

    public function test_verified_payment_marks_invoice_paid_and_credits_the_technician(): void
    {
        // بعد از صدور فاکتور: −سهم شرکت (۶۰۰هزار) روی کیف‌پول نشسته.
        $tech = $this->technician(balance: -600_000);
        $invoice = $this->invoiceFor($tech, 2_000_000);
        $this->pendingPayment($invoice, 'TRK-1');
        $this->fakeVerify(2_000_000);

        $view = $this->hitCallback('TRK-1');

        $this->assertTrue($view->getData()['ok']);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $tx = WalletTransaction::where('invoice_id', $invoice->id)
            ->where('type', WalletTxType::OnlinePayment->value)->first();
        $this->assertNotNull($tx);
        $this->assertSame(2_000_000, (int) $tx->amount);
        // برآیند: −۶۰۰ + ۲٬۰۰۰ = +۱٬۴۰۰ = دقیقاً سهمِ تکنسین.
        $this->assertSame(1_400_000, (int) $tech->fresh()->wallet_balance);
    }

    public function test_a_second_callback_does_not_double_credit(): void
    {
        $tech = $this->technician();
        $invoice = $this->invoiceFor($tech, 900_000);
        $this->pendingPayment($invoice, 'TRK-2');
        $this->fakeVerify(900_000);

        $this->hitCallback('TRK-2');
        $this->hitCallback('TRK-2'); // webhook یا refresh مشتری

        $this->assertSame(1, WalletTransaction::where('invoice_id', $invoice->id)->count());
        $this->assertSame(900_000, (int) $tech->fresh()->wallet_balance);
    }

    // ───────────────────────── ۲) کنترلِ امنیتیِ مبلغ

    public function test_amount_mismatch_fails_without_any_effect(): void
    {
        $tech = $this->technician();
        $invoice = $this->invoiceFor($tech, 2_000_000);
        $payment = $this->pendingPayment($invoice, 'TRK-3');
        $this->fakeVerify(50_000); // درگاه مبلغ دیگری تأیید کرده

        $view = $this->hitCallback('TRK-3');

        $this->assertFalse($view->getData()['ok']);
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('issued', $invoice->fresh()->status);
        $this->assertSame(0, WalletTransaction::count());
        $this->assertSame(0, (int) $tech->fresh()->wallet_balance);
    }

    // ───────────────────────── ۳) شارژ کیف‌پول مثل قبل

    public function test_wallet_charge_purpose_still_credits_as_wallet_charge(): void
    {
        $tech = $this->technician();
        Payment::forceCreate([
            'technician_id' => $tech->id, 'gateway' => 'zibal', 'purpose' => 'wallet_charge',
            'amount' => 500_000, 'track_id' => 'TRK-W', 'status' => 'pending', 'requested_at' => now(),
        ]);
        $this->fakeVerify(500_000);

        $this->hitCallback('TRK-W');

        $tx = WalletTransaction::where('technician_id', $tech->id)->first();
        $this->assertSame(WalletTxType::WalletCharge, $tx->type);
        $this->assertSame(500_000, (int) $tech->fresh()->wallet_balance);
    }

    // ───────────────────────── ۴) endpoint وضعیت برای اپ مشتری

    public function test_status_endpoint_reports_the_payment_state(): void
    {
        $tech = $this->technician();
        $paid = $this->invoiceFor($tech, 800_000, [
            'public_token' => str_repeat('a', 40), 'status' => 'paid', 'paid_at' => now(),
        ]);

        $json = app(PaymentController::class)->status($paid->public_token)->getData(true);

        $this->assertTrue($json['data']['paid']);
        $this->assertFalse($json['data']['payable']);
        $this->assertSame(800_000, $json['data']['amount']);

        $unpaid = $this->invoiceFor($tech, 500_000, ['public_token' => str_repeat('b', 40)]);
        $json = app(PaymentController::class)->status($unpaid->public_token)->getData(true);
        $this->assertFalse($json['data']['paid']);
        $this->assertTrue($json['data']['payable']);

        $this->assertSame(404, app(PaymentController::class)->status('missing-token')->getStatusCode());
    }

    public function test_a_superseded_invoice_is_reported_and_blocked(): void
    {
        $tech = $this->technician();
        $old = $this->invoiceFor($tech, 700_000, [
            'public_token' => str_repeat('c', 40), 'superseded_at' => now(),
        ]);

        $json = app(PaymentController::class)->status($old->public_token)->getData(true);
        $this->assertTrue($json['data']['superseded']);
        $this->assertFalse($json['data']['payable']);

        // صفحهٔ پرداخت هم راهنمایی می‌کند، نه «یافت نشد» خشک.
        $view = app(PaymentController::class)->pay($old->public_token);
        $this->assertStringContainsString('جایگزین شده', $view->getData()['message']);
    }

    // ───────────────────────── ۵) گاردهای شروعِ پرداخت

    public function test_zero_amount_and_cancelled_invoices_never_reach_the_gateway(): void
    {
        $tech = $this->technician();

        $free = $this->invoiceFor($tech, 0, ['public_token' => str_repeat('d', 40)]);
        $view = app(PaymentController::class)->initiate(Request::create('/', 'POST'), $free->public_token);
        $this->assertStringContainsString('صفر', $view->getData()['message']);

        $cancelled = $this->invoiceFor($tech, 300_000, [
            'public_token' => str_repeat('e', 40), 'status' => 'cancelled',
        ]);
        $view = app(PaymentController::class)->initiate(Request::create('/', 'POST'), $cancelled->public_token);
        $this->assertStringContainsString('لغو شده', $view->getData()['message']);

        $this->assertSame(0, Payment::count());
    }

    // ───────────────────────── سفارشِ بسته‌شده بدونِ انجامِ کار

    /**
     * سفارشی که تکمیل و فاکتور صادر شده بوده ولی بعداً لغو/رد/ایاب و ذهاب
     * شده — درگاه باید همه‌جا خاموش شود (اپ مشتری، رسید، شروع پرداخت).
     */
    public function test_an_order_closed_without_work_turns_the_gateway_off(): void
    {
        $tech = $this->technician();

        foreach (['cancelled' => 'm', 'declined' => 'n', 'transit' => 'o'] as $orderStatus => $letter) {
            $invoice = $this->invoiceFor($tech, 800_000, ['public_token' => str_repeat($letter, 40)]);
            $invoice->order->forceFill(['status' => $orderStatus])->save();
            $invoice->refresh()->load('order');

            $this->assertFalse($invoice->isPayableOnline(), $orderStatus.' نباید درگاه داشته باشد.');

            $json = app(PaymentController::class)->status($invoice->public_token)->getData(true);
            $this->assertFalse($json['data']['payable']);
            $this->assertStringContainsString('بسته شده', (string) $json['data']['payable_reason']);

            $view = app(PaymentController::class)->initiate(Request::create('/', 'POST'), $invoice->public_token);
            $this->assertStringContainsString('بسته شده', $view->getData()['message']);
        }

        $this->assertSame(0, Payment::count(), 'هیچ درخواستی نباید به درگاه رفته باشد.');
    }

    /** سفارشِ «انجام کار» همچنان قابلِ پرداخت است — قاعده فقط بسته‌های بی‌کار را می‌گیرد. */
    public function test_a_completed_order_stays_payable(): void
    {
        $tech = $this->technician();
        $invoice = $this->invoiceFor($tech, 800_000, ['public_token' => str_repeat('p', 40)]);

        $this->assertTrue($invoice->load('order')->isPayableOnline());
        $this->assertNull($invoice->notPayableReason());
        $this->assertTrue(app(PaymentController::class)->status($invoice->public_token)->getData(true)['data']['payable']);
    }

    /** سفارشِ در جریان (هنوز بسته نشده) هم قابلِ پرداخت است. */
    public function test_an_open_order_stays_payable(): void
    {
        $tech = $this->technician();
        $invoice = $this->invoiceFor($tech, 500_000, ['public_token' => str_repeat('q', 40)]);
        $invoice->order->forceFill(['status' => 'open'])->save();

        $this->assertTrue($invoice->refresh()->load('order')->isPayableOnline());
    }

    // ───────────────────────── فاکتورِ نقدی: درگاه خاموش

    public function test_a_cash_collected_invoice_shows_no_gateway_anywhere(): void
    {
        $tech = $this->technician();
        $invoice = $this->invoiceFor($tech, 900_000, [
            'public_token' => str_repeat('k', 40), 'collection_method' => 'cash',
        ]);

        // endpoint وضعیت: قابلِ پرداخت نیست + روشِ دریافت اعلام می‌شود.
        $json = app(PaymentController::class)->status($invoice->public_token)->getData(true);
        $this->assertFalse($json['data']['payable']);
        $this->assertSame('cash', $json['data']['collection_method']);

        // شروعِ پرداخت: بلاک با پیامِ روشن؛ هیچ paymentای ساخته نمی‌شود.
        $view = app(PaymentController::class)->initiate(Request::create('/', 'POST'), $invoice->public_token);
        $this->assertStringContainsString('نقدی', $view->getData()['message']);
        $this->assertSame(0, Payment::count());
    }

    public function test_an_online_collected_invoice_stays_payable(): void
    {
        $tech = $this->technician();
        $invoice = $this->invoiceFor($tech, 900_000, [
            'public_token' => str_repeat('l', 40), 'collection_method' => 'online',
        ]);

        $json = app(PaymentController::class)->status($invoice->public_token)->getData(true);
        $this->assertTrue($json['data']['payable']);
        $this->assertSame('online', $json['data']['collection_method']);
    }

    // ───────────────────────── ۶) لینکِ برگشت به اپ

    public function test_a_whitelisted_return_url_is_stored_with_the_payment(): void
    {
        Http::fake(['gateway.zibal.ir/v1/request' => Http::response(['result' => 100, 'trackId' => 'TRK-R', 'payLink' => 'x']), '*' => Http::response([])]);
        $tech = $this->technician();
        $invoice = $this->invoiceFor($tech, 400_000, ['public_token' => str_repeat('f', 40)]);

        app(PaymentController::class)->initiate(
            Request::create('/', 'POST', ['return' => 'tamironline://payment-result?order=55']),
            $invoice->public_token,
        );

        $this->assertSame('tamironline://payment-result?order=55', Payment::firstOrFail()->return_url);
    }

    /** مسیرِ یک‌کلیکهٔ اپ: GET /crm/pay/{token}/go → مستقیم redirect به درگاه. */
    public function test_the_direct_route_goes_straight_to_the_gateway(): void
    {
        Http::fake([
            'gateway.zibal.ir/v1/request' => Http::response([
                'result' => 100, 'trackId' => 'TRK-GO', 'payLink' => 'x',
            ]),
            '*' => Http::response([]),
        ]);
        $tech = $this->technician();
        $invoice = $this->invoiceFor($tech, 350_000, ['public_token' => str_repeat('h', 40)]);

        $response = app(PaymentController::class)->direct(
            Request::create('/', 'GET', ['return' => 'tamironline://orders/9']),
            $invoice->public_token,
        );

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertStringContainsString('gateway.zibal.ir/start/TRK-GO', $response->getTargetUrl());
        $payment = Payment::firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('tamironline://orders/9', $payment->return_url);
    }

    public function test_a_foreign_return_url_is_silently_dropped(): void
    {
        Http::fake(['gateway.zibal.ir/v1/request' => Http::response(['result' => 100, 'trackId' => 'TRK-R2', 'payLink' => 'x']), '*' => Http::response([])]);
        $tech = $this->technician();
        $invoice = $this->invoiceFor($tech, 400_000, ['public_token' => str_repeat('g', 40)]);

        app(PaymentController::class)->initiate(
            Request::create('/', 'POST', ['return' => 'https://evil.example/phish']),
            $invoice->public_token,
        );

        $this->assertNull(Payment::firstOrFail()->return_url);
    }
}
