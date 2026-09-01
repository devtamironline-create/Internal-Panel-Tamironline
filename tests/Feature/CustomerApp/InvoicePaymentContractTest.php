<?php

namespace Tests\Feature\CustomerApp;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Invoice;
use Modules\CustomerApp\Support\InvoiceBuilder;
use Tests\TestCase;

/**
 * قراردادِ بلوکِ payment در پاسخِ فاکتورِ اپِ مشتری (درخواستِ تیمِ اپ):
 *
 *   - public_token همیشه هست (حتی برای paid) تا اپ /crm/pay/{token}/status
 *     را poll کند.
 *   - pay_url فقط برای فاکتورِ قابلِ پرداخت (= همان payment_url که صفحهٔ
 *     پرداختِ پنل است، نه URL مستقیمِ درگاه).
 */
class InvoicePaymentContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_invoices', function ($t) {
            $t->id();
            $t->string('invoice_code', 40)->nullable();
            $t->string('public_token', 64)->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->bigInteger('total_amount')->default(0);
            $t->text('description')->nullable();
            $t->text('items_snapshot')->nullable();
            $t->string('status', 20)->default('issued');
            $t->string('collection_method', 10)->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->unsignedBigInteger('superseded_by_id')->nullable();
            $t->timestamps();
        });

        // برای تست‌های other_invoices و ریدایرکتِ شفافِ showByToken.
        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->string('status', 30)->default('completed');
            $t->timestamp('status_changed_at')->nullable();
            $t->string('customer_name')->nullable();
            $t->string('customer_mobile', 20)->nullable();
            $t->text('address')->nullable();
            $t->text('invoice_descripotion')->nullable();
            $t->json('piece_list')->nullable();
            $t->json('customer_price_list')->nullable();
            $t->json('wp_notes')->nullable();
            $t->timestamps();
        });
        Schema::create('crm_order_items', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('order_id')->nullable(),
            $x->string('type', 20)->nullable(), $x->string('title')->nullable(),
            $x->integer('quantity')->default(1), $x->bigInteger('unit_price')->default(0),
            $x->bigInteger('total_price')->default(0), $x->integer('warranty_months')->nullable(),
        ]));
        Schema::create('crm_customers', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('first_name')->nullable(), $x->string('last_name')->nullable(),
            $x->string('mobile', 20)->nullable(), $x->timestamps(), $x->softDeletes(),
        ]));
    }

    /** @return array<string, mixed> */
    private function paymentBlock(Invoice $invoice): array
    {
        $m = new \ReflectionMethod(InvoiceBuilder::class, 'buildPayment');

        return $m->invoke(null, $invoice, $invoice->paid_at);
    }

    public function test_a_payable_invoice_exposes_token_and_the_panel_pay_page(): void
    {
        $invoice = Invoice::forceCreate([
            'invoice_code' => 'INV-1', 'public_token' => str_repeat('a', 40),
            'total_amount' => 500_000, 'status' => 'issued', 'issued_at' => now(),
        ]);

        $payment = $this->paymentBlock($invoice);

        $this->assertSame(str_repeat('a', 40), $payment['public_token']);
        // pay_url = صفحهٔ پرداختِ پنل با public_token — تأییدِ فرضِ اپ.
        $this->assertStringEndsWith('/crm/pay/'.str_repeat('a', 40), $payment['pay_url']);
        $this->assertSame($payment['payment_url'], $payment['pay_url']);
        // یک‌کلیکه — مستقیم به درگاه.
        $this->assertStringEndsWith('/crm/pay/'.str_repeat('a', 40).'/go', $payment['direct_pay_url']);
    }

    public function test_a_paid_invoice_still_exposes_the_token_but_no_pay_url(): void
    {
        $invoice = Invoice::forceCreate([
            'invoice_code' => 'INV-2', 'public_token' => str_repeat('b', 40),
            'total_amount' => 500_000, 'status' => 'paid',
            'issued_at' => now(), 'paid_at' => now(),
        ]);

        $payment = $this->paymentBlock($invoice);

        $this->assertSame(str_repeat('b', 40), $payment['public_token']);
        $this->assertNull($payment['pay_url']);
        $this->assertNull($payment['direct_pay_url']);
        $this->assertTrue($payment['is_paid']);
    }

    // ───────────── فاکتورهای جمع‌شوندهٔ سفارشِ بازگشتی (۱۴۰۵/۰۵/۲۹) ─────────────

    public function test_the_payload_lists_the_other_active_invoices_side_by_side(): void
    {
        $order = \Modules\CRM\Models\Order::forceCreate(['order_code' => 'ORD-RET', 'status' => 'completed']);

        $first = Invoice::forceCreate([
            'invoice_code' => 'INV-J1', 'public_token' => str_repeat('c', 40),
            'order_id' => $order->id, 'total_amount' => 6_000_000, 'status' => 'issued', 'issued_at' => now(),
        ]);
        $second = Invoice::forceCreate([
            'invoice_code' => 'INV-J2', 'public_token' => str_repeat('d', 40),
            'order_id' => $order->id, 'total_amount' => 2_000_000, 'status' => 'issued', 'issued_at' => now(),
        ]);
        // باطل‌شده هرگز به مشتری نمایش داده نمی‌شود.
        Invoice::forceCreate([
            'invoice_code' => 'INV-OLD', 'public_token' => str_repeat('e', 40),
            'order_id' => $order->id, 'total_amount' => 1_000_000, 'status' => 'issued',
            'superseded_at' => now(), 'superseded_by_id' => $second->id,
        ]);

        $payload = InvoiceBuilder::build($order->load('items'), $second);

        $this->assertSame('INV-J2', $payload['invoice_number']);
        $this->assertCount(1, $payload['other_invoices']);
        $this->assertSame('INV-J1', $payload['other_invoices'][0]['invoice_number']);
        $this->assertSame(6_000_000, $payload['other_invoices'][0]['total']);
        $this->assertTrue($payload['other_invoices'][0]['payable']);
        $this->assertStringEndsWith('/crm/pay/'.str_repeat('c', 40), $payload['other_invoices'][0]['pay_url']);
    }

    public function test_show_by_token_serves_the_replacement_transparently(): void
    {
        $customer = \Modules\CRM\Models\Customer::forceCreate(['first_name' => 'مشتری', 'mobile' => '09121112233']);
        $order = \Modules\CRM\Models\Order::forceCreate([
            'order_code' => 'ORD-CORR', 'status' => 'completed', 'customer_id' => $customer->id,
        ]);

        $new = Invoice::forceCreate([
            'invoice_code' => 'INV-NEW', 'public_token' => str_repeat('g', 40),
            'order_id' => $order->id, 'customer_id' => $customer->id,
            'total_amount' => 4_000_000, 'status' => 'issued', 'issued_at' => now(),
        ]);
        $old = Invoice::forceCreate([
            'invoice_code' => 'INV-WRONG', 'public_token' => str_repeat('f', 40),
            'order_id' => $order->id, 'customer_id' => $customer->id,
            'total_amount' => 9_000_000, 'status' => 'issued',
            'superseded_at' => now(), 'superseded_by_id' => $new->id,
        ]);

        $request = \Illuminate\Http\Request::create('/v1/customer/invoices/'.$old->public_token, 'GET');
        $request->setUserResolver(fn () => $customer);

        $json = app(\Modules\CustomerApp\Http\Controllers\Api\V1\InvoiceController::class)
            ->showByToken($request, $old->public_token)
            ->getData(true);

        // مشتری بدونِ هیچ توضیحی فاکتورِ جایگزین را می‌بیند — نه باطل‌شده را.
        $this->assertSame('INV-NEW', $json['data']['invoice_number']);
        $this->assertSame(4_000_000, $json['data']['totals']['total']);
    }
}
