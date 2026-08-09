<?php

namespace Tests\Feature\CRM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\CRM\Http\Controllers\OrderController;
use Modules\CRM\Models\Order;
use Tests\TestCase;

/**
 * ردیابِ «فاکتورهای از قلم افتاده».
 *
 * دو ادعای اصلی این‌جا قفل می‌شوند:
 *
 *   ۱) fallbackِ پنجرهٔ زمانی status_changed_at است نه updated_at —
 *      سینکِ WP مدام updated_at را تازه می‌کند و با آن fallback،
 *      سفارش‌های ایمپورتیِ قدیمیِ بی‌فاکتور همیشه «تازه» به نظر می‌رسیدند
 *      و لیست را پر می‌کردند.
 *
 *   ۲) تکمیلِ پیش‌نویس و بستنِ قدیمی دیگر بی‌صدا حذف نمی‌شوند — سفارشِ
 *      بی‌فاکتور نباید از چشمِ همان صفحه‌ای که برای پیداکردنش ساخته شده
 *      پنهان بماند.
 *
 * کنترلر مستقیم صدا زده می‌شود؛ زنجیرهٔ auth موضوعِ این تست نیست.
 */
class MissingInvoicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('crm_orders', function ($t) {
            $t->id();
            $t->string('order_code')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('customer_name')->nullable();
            $t->string('customer_mobile')->nullable();
            $t->unsignedBigInteger('technician_id')->nullable();
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->unsignedBigInteger('device_id')->nullable();
            $t->string('status', 30)->default('new');
            $t->timestamp('status_changed_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->boolean('is_lead')->default(false);
            $t->boolean('is_legacy_closed')->default(false);
            $t->boolean('save_as_draft')->default(false);
            $t->bigInteger('price_customer')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_invoices', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id')->nullable();
            // هوکِ creating مدل این را همیشه پر می‌کند.
            $t->string('public_token', 64)->nullable();
            $t->timestamp('superseded_at')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_customers', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('mobile')->nullable();
        });
        Schema::create('crm_technicians', function ($t) {
            $t->id();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('firstname_tech')->nullable();
            $t->string('mobile')->nullable();
        });
        Schema::create('crm_brands', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });
        Schema::create('crm_devices', function ($t) {
            $t->id();
            $t->string('name')->nullable();
        });
    }

    // ───────────────────────── helpers

    private function order(array $attributes = []): Order
    {
        static $seq = 0;
        $seq++;

        return Order::forceCreate(array_merge([
            'order_code' => 'MI-'.$seq,
            'customer_name' => 'مشتری نمونه',
            'status' => 'completed',
            'completed_at' => now()->subDay(),
            'status_changed_at' => now()->subDay(),
        ], $attributes));
    }

    /** @return list<string> کدِ سفارش‌های ردیف‌های صفحه */
    private function listedCodes(int $days = 30): array
    {
        $request = Request::create('/crm/orders/missing-invoices', 'GET', ['days' => $days]);

        $view = app(OrderController::class)->missingInvoices($request);

        return collect($view->getData()['orders']->items())->pluck('order_code')->all();
    }

    // ───────────────────────── پنجرهٔ زمانی

    public function test_a_recently_completed_order_without_invoice_is_listed(): void
    {
        $this->order(['order_code' => 'FRESH']);

        $this->assertContains('FRESH', $this->listedCodes());
    }

    public function test_an_order_with_an_active_invoice_is_not_listed(): void
    {
        $order = $this->order(['order_code' => 'PAID']);
        \Modules\CRM\Models\Invoice::forceCreate(['order_id' => $order->id]);

        $this->assertNotContains('PAID', $this->listedCodes());
    }

    /** فاکتورِ باطل‌شده «فاکتور» نیست — سفارش باید همچنان دیده شود. */
    public function test_a_superseded_invoice_does_not_hide_the_order(): void
    {
        $order = $this->order(['order_code' => 'SUPERSEDED-ONLY']);
        \Modules\CRM\Models\Invoice::forceCreate(['order_id' => $order->id, 'superseded_at' => now()]);

        $this->assertContains('SUPERSEDED-ONLY', $this->listedCodes());
    }

    /**
     * قلبِ گزارشِ خرابی: سفارشِ ایمپورتیِ قدیمی که سینک هفتگی updated_at
     * آن را تازه می‌کند، نباید در لیست بیاید — تاریخِ واقعیِ تکمیلش مالِ
     * خیلی قبل است.
     */
    public function test_an_old_imported_order_touched_by_sync_stays_out(): void
    {
        $old = $this->order([
            'order_code' => 'IMPORTED',
            'completed_at' => null,
            'status_changed_at' => now()->subMonths(8),
        ]);
        // سینکِ WP دیروز به رکورد دست زده.
        Order::withoutTimestamps(fn () => null);
        $old->newQuery()->whereKey($old->id)->update(['updated_at' => now()->subHour()]);

        $this->assertNotContains('IMPORTED', $this->listedCodes());
    }

    public function test_a_wp_completed_order_with_recent_status_change_is_listed(): void
    {
        // completed_at ندارد (از WP «انجام کار» رسیده) ولی وضعیتش دیشب
        // عوض شده — همان سفارشی که گزارش شد ردیاب نمی‌دید.
        $this->order([
            'order_code' => 'WP-DONE',
            'completed_at' => null,
            'status_changed_at' => now()->subHours(20),
        ]);

        $this->assertContains('WP-DONE', $this->listedCodes());
    }

    public function test_the_default_window_is_thirty_days(): void
    {
        $this->order(['order_code' => 'DAY-20', 'completed_at' => now()->subDays(20), 'status_changed_at' => now()->subDays(20)]);
        $this->order(['order_code' => 'DAY-40', 'completed_at' => now()->subDays(40), 'status_changed_at' => now()->subDays(40)]);

        $request = Request::create('/crm/orders/missing-invoices', 'GET');   // بدونِ days
        $codes = collect(app(OrderController::class)->missingInvoices($request)->getData()['orders']->items())
            ->pluck('order_code')->all();

        $this->assertContains('DAY-20', $codes);
        $this->assertNotContains('DAY-40', $codes);
    }

    // ───────────────────────── دیگر بی‌صدا حذف نمی‌شوند

    public function test_a_draft_completion_is_listed_instead_of_hidden(): void
    {
        $this->order(['order_code' => 'DRAFT', 'save_as_draft' => true]);

        $this->assertContains('DRAFT', $this->listedCodes());
    }

    public function test_a_legacy_closed_order_is_listed_instead_of_hidden(): void
    {
        $this->order(['order_code' => 'LEGACY', 'is_legacy_closed' => true]);

        $this->assertContains('LEGACY', $this->listedCodes());
    }

    public function test_leads_stay_out(): void
    {
        $this->order(['order_code' => 'LEAD', 'is_lead' => true]);

        $this->assertNotContains('LEAD', $this->listedCodes());
    }
}
