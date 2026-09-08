<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\CRM\Http\Controllers\Api\V1\Technician\OrderActionController;
use Modules\CRM\Models\Order;
use Tests\TestCase;

/**
 * کف/سقفِ مبلغِ بستنِ فاکتور توسطِ تکنسین: زیر ۲۰۰٬۰۰۰ و بالای ۵۰٬۰۰۰٬۰۰۰
 * تومان مجاز نیست. مستقیماً بلاکِ تکمیل (applyCompletionBlock) آزموده می‌شود.
 */
class TechInvoiceBoundsTest extends TestCase
{
    private function apply(int $priceCustomer): array
    {
        $controller = app(OrderActionController::class);
        $m = new \ReflectionMethod(OrderActionController::class, 'applyCompletionBlock');
        $m->setAccessible(true);

        // سفارشِ عادیِ غیربرگشتی با عکسِ موجود (تا شرطِ عکس سبز باشد).
        $order = new Order;
        $order->setRawAttributes(['return_type' => null, 'device_img1' => 'existing.jpg', 'cost_price' => 0]);

        $request = Request::create('/x', 'POST');
        $validated = [
            'save_as_draft' => false,
            'invoice_descripotion' => 'توضیحِ معتبرِ فاکتور برای بستنِ سفارش.',
            'price_customer' => $priceCustomer,
            'pieces' => [],
        ];

        return $m->invoke($controller, $request, $order, $validated, ['status' => 'completed']);
    }

    public function test_rejects_amount_below_minimum(): void
    {
        $this->expectException(ValidationException::class);
        $this->apply(199_000);
    }

    public function test_rejects_amount_above_maximum(): void
    {
        $this->expectException(ValidationException::class);
        $this->apply(50_000_001);
    }

    public function test_accepts_amount_within_bounds(): void
    {
        $updates = $this->apply(2_500_000);

        $this->assertSame(2_500_000, $updates['price_customer']);
        $this->assertSame(2_500_000, $updates['total_invoice']);
    }
}
