<?php

namespace Tests\Feature\CRM;

use Modules\CRM\Models\Order;
use Modules\CRM\Services\InvoiceService;
use Tests\TestCase;

/**
 * هر فاکتور باید شرح/اقلامِ خودش را در لحظهٔ صدور snapshot کند تا در سفارشِ
 * بازگشتیِ جمع‌شونده (چند فاکتور) هر کدام شرحِ مستقلِ خودش را نشان دهد و به
 * فیلدِ زندهٔ سفارش (که با تکمیلِ بعدی بازنویسی می‌شود) وابسته نباشد.
 */
class InvoiceDescriptionSnapshotTest extends TestCase
{
    private function snapshotFor(Order $order): array
    {
        $svc = app(InvoiceService::class);
        $m = new \ReflectionMethod($svc, 'snapshotFor');
        $m->setAccessible(true);

        return $m->invoke($svc, $order);
    }

    public function test_snapshot_captures_the_orders_current_description(): void
    {
        $order = new Order;
        $order->forceFill(['invoice_descripotion' => 'کارِ اول — تعویض برد']);

        $snap = $this->snapshotFor($order);

        $this->assertSame('کارِ اول — تعویض برد', $snap['description']);
    }

    public function test_snapshot_captures_pieces_when_no_description(): void
    {
        $order = new Order;
        $order->forceFill([
            'invoice_descripotion' => null,
            'piece_list' => ['شیلنگ', 'واشر'],
            'customer_price_list' => [50000, 20000],
        ]);

        $snap = $this->snapshotFor($order);

        $this->assertNull($snap['description']);
        $this->assertSame(
            [['title' => 'شیلنگ', 'total' => 50000], ['title' => 'واشر', 'total' => 20000]],
            $snap['items_snapshot']
        );
    }

    public function test_snapshot_is_independent_of_later_order_changes(): void
    {
        // شبیه‌سازیِ دو تکمیل: snapshotِ اول گرفته می‌شود، بعد شرحِ سفارش عوض
        // می‌شود؛ snapshotِ اول نباید تغییر کند.
        $order = new Order;
        $order->forceFill(['invoice_descripotion' => 'شرحِ فاکتورِ اول']);
        $first = $this->snapshotFor($order);

        $order->forceFill(['invoice_descripotion' => 'شرحِ فاکتورِ دوم']);
        $second = $this->snapshotFor($order);

        $this->assertSame('شرحِ فاکتورِ اول', $first['description']);
        $this->assertSame('شرحِ فاکتورِ دوم', $second['description']);
        $this->assertNotSame($first['description'], $second['description']);
    }
}
