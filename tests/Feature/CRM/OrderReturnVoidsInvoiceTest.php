<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\WalletTxType;
use Modules\CRM\Http\Controllers\OrderController;
use Modules\CRM\Models\Invoice;
use Modules\CRM\Models\Order;
use Modules\CRM\Models\Technician;
use Modules\CRM\Models\WalletTransaction;
use Tests\TestCase;

/**
 * بازگشتِ سفارش، فاکتورِ قبلی را «باطل» می‌کند (تصمیمِ ۱۴۰۵/۰۶/۰۷):
 *   - فاکتورِ فعالِ پرداخت‌نشده superseded می‌شود (از محاسبات خارج).
 *   - سهمِ شرکت با تراکنشِ معکوس به کیف‌پولِ تکنسین برمی‌گردد.
 *   - فاکتورِ پرداخت‌شده باطل نمی‌شود (پولِ مشتری پشتش است).
 */
class OrderReturnVoidsInvoiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_technicians', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('first_name')->nullable(), $x->string('firstname_tech')->nullable(),
            $x->string('mobile')->nullable(), $x->string('status', 20)->default('active'),
            $x->bigInteger('wallet_balance')->default(0), $x->timestamps(), $x->softDeletes(),
        ]));
        Schema::create('crm_invoices', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('invoice_code')->nullable(), $x->string('public_token')->nullable(),
            $x->unsignedBigInteger('order_id')->nullable(), $x->unsignedBigInteger('customer_id')->nullable(),
            $x->unsignedBigInteger('technician_id')->nullable(),
            $x->bigInteger('total_amount')->default(0), $x->bigInteger('tech_share')->default(0),
            $x->bigInteger('company_share')->default(0), $x->string('calc_type')->nullable(),
            $x->integer('commission_percent')->default(0), $x->boolean('in_wallet')->default(false),
            $x->string('status', 20)->default('issued'), $x->string('collection_method')->nullable(),
            $x->timestamp('issued_at')->nullable(), $x->timestamp('paid_at')->nullable(),
            $x->timestamp('superseded_at')->nullable(), $x->unsignedBigInteger('superseded_by_id')->nullable(),
            $x->unsignedBigInteger('created_by')->nullable(), $x->timestamps(),
        ]));
        Schema::create('crm_tech_wallet_transactions', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->unsignedBigInteger('wp_id')->nullable(), $x->unsignedBigInteger('technician_id'),
            $x->unsignedBigInteger('order_id')->nullable(), $x->unsignedBigInteger('invoice_id')->nullable(),
            $x->string('type')->nullable(), $x->bigInteger('amount')->default(0),
            $x->bigInteger('balance_after')->default(0), $x->text('note')->nullable(),
            $x->unsignedBigInteger('created_by')->nullable(), $x->timestamps(),
        ]));
    }

    private function invoke(Order $order): array
    {
        $controller = app(OrderController::class);
        $ref = new \ReflectionMethod($controller, 'voidInvoicesOnReturn');
        $ref->setAccessible(true);

        return $ref->invoke($controller, $order);
    }

    private function orderStub(int $id): Order
    {
        $o = new Order;
        $o->setAttribute('id', $id);

        return $o;
    }

    public function test_return_voids_the_active_invoice_and_refunds_company_share_to_wallet(): void
    {
        $tech = Technician::forceCreate([
            'firstname_tech' => 'رضا', 'mobile' => '09120000001', 'status' => 'active', 'wallet_balance' => -1800000,
        ]);
        $invoice = Invoice::create([
            'invoice_code' => 'INV-1', 'order_id' => 500, 'technician_id' => $tech->id,
            'total_amount' => 6000000, 'tech_share' => 4200000, 'company_share' => 1800000,
            'commission_percent' => 30, 'status' => 'issued', 'in_wallet' => true, 'issued_at' => now(),
        ]);
        // تراکنشِ سهمِ شرکت که موقعِ صدور ثبت شده بود.
        WalletTransaction::create([
            'technician_id' => $tech->id, 'order_id' => 500, 'invoice_id' => $invoice->id,
            'type' => WalletTxType::Commission->value, 'amount' => -1800000, 'balance_after' => -1800000,
            'note' => 'سهم شرکت از فاکتور INV-1',
        ]);

        [$voided, $skipped] = $this->invoke($this->orderStub(500));

        $this->assertSame(['INV-1'], $voided);
        $this->assertSame([], $skipped);

        // فاکتور باطل (superseded) شده و از scope فعال خارج است.
        $this->assertNotNull(Invoice::withSuperseded()->find($invoice->id)->superseded_at);
        $this->assertSame(0, Invoice::where('order_id', 500)->count());

        // سهمِ شرکت با تراکنشِ معکوس (+۱٬۸۰۰٬۰۰۰) برگشته و مانده صفر شده.
        $this->assertSame(0, (int) $tech->fresh()->wallet_balance);
        $this->assertTrue(
            WalletTransaction::where('invoice_id', $invoice->id)
                ->where('amount', 1800000)->exists()
        );
    }

    public function test_a_paid_invoice_is_not_voided(): void
    {
        $tech = Technician::forceCreate([
            'firstname_tech' => 'علی', 'mobile' => '09120000002', 'status' => 'active', 'wallet_balance' => 0,
        ]);
        $paid = Invoice::create([
            'invoice_code' => 'INV-PAID', 'order_id' => 501, 'technician_id' => $tech->id,
            'total_amount' => 5000000, 'company_share' => 1500000, 'status' => 'paid',
            'in_wallet' => true, 'issued_at' => now(), 'paid_at' => now(),
        ]);

        [$voided, $skipped] = $this->invoke($this->orderStub(501));

        $this->assertSame([], $voided);
        $this->assertSame(['INV-PAID'], $skipped);
        // فاکتورِ پرداخت‌شده دست‌نخورده و همچنان فعال است.
        $this->assertNull($paid->fresh()->superseded_at);
        $this->assertSame(1, Invoice::where('order_id', 501)->count());
    }
}
