<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Models\Order;
use Tests\TestCase;

/**
 * دورِ تازهٔ سفارشِ بازگشتی (تصمیمِ ۱۴۰۵/۰۶/۰۷):
 *   - بازگشت هیچ محاسبه/برگشتِ پولی ندارد و فاکتورِ قبلی دست‌نخورده می‌ماند.
 *   - با ثبتِ نتیجهٔ بررسیِ برگشتی، فیلدهای قیمت/فاکتورِ سفارش صفر می‌شوند
 *     تا فاکتورِ تکمیلِ مجدد پیش‌فرضِ ۰ داشته باشد (گارانتی) یا تکنسین
 *     عددِ کاملِ جدید وارد کند (غیرگارانتی).
 */
class OrderReturnReworkResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_orders', fn ($t) => tap($t, fn ($x) => [
            $x->id(), $x->string('status', 30)->nullable(),
            $x->bigInteger('price_customer')->nullable(), $x->bigInteger('cost_price')->nullable(),
            $x->bigInteger('hire')->nullable(), $x->bigInteger('transportation')->nullable(),
            $x->bigInteger('discount')->nullable(), $x->bigInteger('total_invoice')->nullable(),
            $x->bigInteger('negative_invoice')->nullable(), $x->text('invoice_descripotion')->nullable(),
            $x->text('piece_list')->nullable(), $x->text('customer_price_list')->nullable(),
            $x->text('buy_price_list')->nullable(), $x->timestamps(),
        ]));
    }

    public function test_reset_fields_contract_is_all_zeroed_or_nulled(): void
    {
        $fields = Order::reworkPriceResetFields();

        $this->assertSame(0, $fields['price_customer']);
        $this->assertSame(0, $fields['cost_price']);
        $this->assertSame(0, $fields['total_invoice']);
        $this->assertSame(0, $fields['negative_invoice']);
        $this->assertNull($fields['invoice_descripotion']);
        $this->assertNull($fields['piece_list']);
        // فیلدهای بررسیِ برگشتی نباید این‌جا باشند (فقط قیمت/فاکتور).
        $this->assertArrayNotHasKey('return_review_approved', $fields);
        $this->assertArrayNotHasKey('status', $fields);
    }

    public function test_applying_the_reset_zeros_the_previous_amounts_on_the_order(): void
    {
        $order = Order::withoutEvents(fn () => Order::forceCreate([
            'status' => 'new',
            'price_customer' => 6000000, 'cost_price' => 1500000,
            'hire' => 200000, 'transportation' => 100000, 'discount' => 50000,
            'total_invoice' => 4500000, 'negative_invoice' => 0,
            'invoice_descripotion' => 'تعمیر قبلی',
            'piece_list' => json_encode(['برد']), 'customer_price_list' => json_encode([1000000]),
            'buy_price_list' => json_encode([700000]),
        ]));

        Order::withoutEvents(fn () => $order->update(Order::reworkPriceResetFields()));

        $row = DB::table('crm_orders')->where('id', $order->id)->first();
        $this->assertSame(0, (int) $row->price_customer);
        $this->assertSame(0, (int) $row->cost_price);
        $this->assertSame(0, (int) $row->total_invoice);
        $this->assertNull($row->invoice_descripotion);
        $this->assertNull($row->piece_list);
        $this->assertNull($row->buy_price_list);
    }
}
