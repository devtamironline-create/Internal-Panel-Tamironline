<?php

namespace Tests\Feature\CRM;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Enums\OrderStatus;
use Modules\CRM\Models\Order;
use Tests\TestCase;

/**
 * وضعیتِ قدیمی/ناشناختهٔ `repair_started` (که حذف شد) نباید هنگامِ خواندنِ
 * سفارش ۵۰۰ بدهد — کستِ مقاوم آن را به Coordinated نگاشت می‌کند. این تورِ
 * ایمنیِ پنجرهٔ دیپلوی (کد پیش از migrate) است.
 */
class OrderStatusCastResilienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->string('status')->nullable();
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamps();
        });
    }

    public function test_legacy_repair_started_reads_as_coordinated_without_throwing(): void
    {
        // مستقیم در DB می‌نویسیم تا کستِ set دخالت نکند (شبیه‌سازیِ ردیفِ قدیمی).
        $id = DB::table('crm_orders')->insertGetId([
            'order_code' => 'LEG-1', 'status' => 'repair_started',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $order = Order::findOrFail($id);

        // نباید ValueError پرتاب شود؛ باید Coordinated بخواند.
        $this->assertSame(OrderStatus::Coordinated, $order->status);
        $this->assertNotNull($order->status->label());
    }

    public function test_valid_statuses_still_cast_normally(): void
    {
        $order = Order::forceCreate(['order_code' => 'OK-1', 'status' => OrderStatus::Open->value]);

        $this->assertSame(OrderStatus::Open, $order->fresh()->status);
    }
}
