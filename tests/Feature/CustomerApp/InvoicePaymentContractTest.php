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
            $t->bigInteger('total_amount')->default(0);
            $t->string('status', 20)->default('issued');
            $t->timestamp('issued_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->timestamps();
        });
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
}
